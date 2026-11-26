// Módulo de Caja, Pagos, Propinas, Apertura/Cierre y Facturación DIAN conectado 100% a PostgreSQL
(function () {
    let cajasCache = [];
    let cajaAbierta = null;
    let mesasCache = [];
    let pedidosCache = [];
    let facturasCache = [];
    let descuentosCache = [];
    let pedidoSeleccionado = null;
    let modoClienteCaja = false;

    function esc(v) {
        return window.RequestyAPI.escaparHTML(v);
    }

    function formatearDinero(valor) {
        return `$${Number(valor || 0).toLocaleString("es-CO")}`;
    }

    function normalizarTexto(v) {
        return String(v || "").trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
    }

    function mostrarMensaje(texto, tipo = "success") {
        const el = document.getElementById("mensaje-venta");
        if (!el) return;
        el.textContent = texto;
        el.className = `mensaje-venta ${tipo === "error" ? "error" : ""}`.trim();
    }

    function actualizarTotales(pedido) {
        const sub = Number(pedido?.subtotal || 0);
        const desc = Number(pedido?.descuento_total || 0);
        const imp = Number(pedido?.impuesto_total || 0);
        const totalBase = Number(pedido?.total || 0);
        const propina = Math.max(0, Number(document.getElementById("propina")?.value || 0));

        document.getElementById("txt-subtotal").textContent = formatearDinero(sub);
        document.getElementById("txt-descuento").textContent = desc > 0 ? `-${formatearDinero(desc)}` : "$0";
        document.getElementById("txt-iva").textContent = formatearDinero(imp);
        document.getElementById("txt-propina").textContent = formatearDinero(propina);
        document.getElementById("txt-total").textContent = formatearDinero(totalBase + propina);
    }

    async function cargarDatos() {
        const sesion = window.RequestyAPI.leerSesion() || await window.RequestyAPI.cargarSesion();
        const rol = String(sesion?.rol || "").toUpperCase();
        modoClienteCaja = rol === "CLIENTE";

        if (modoClienteCaja) {
            document.querySelector("main > header h2").textContent = "Pago presencial en caja";
            document.querySelector("main > header nav").hidden = true;
            document.getElementById("vista-caja-cliente").hidden = false;
            document.getElementById("ventas").hidden = true;
            document.getElementById("facturas").hidden = true;
            document.getElementById("cierre").hidden = true;
            await renderizarPedidosClienteCaja();
            return;
        }

        try {
            const [resCaja, resPed, resMesas, resFac, resDesc] = await Promise.all([
                window.RequestyAPI.get("api/caja"),
                window.RequestyAPI.get("api/pedidos"),
                window.RequestyAPI.get("api/mesas"),
                window.RequestyAPI.get("api/facturas"),
                window.RequestyAPI.get("api/descuentos")
            ]);
            cajasCache = Array.isArray(resCaja?.cajas) ? resCaja.cajas : [];
            cajaAbierta = cajasCache.find((c) => c.estado === "ABIERTA") || null;
            pedidosCache = Array.isArray(resPed?.pedidos) ? resPed.pedidos : [];
            mesasCache = Array.isArray(resMesas?.mesas) ? resMesas.mesas : [];
            facturasCache = Array.isArray(resFac?.facturas) ? resFac.facturas : [];
            descuentosCache = (resDesc?.descuentos || []).filter((d) => d.activo !== false);

            const descSelect = document.getElementById("discount-select");
            if (descSelect) {
                descSelect.innerHTML = '<option value="">Sin descuento</option>' +
                    descuentosCache.map((d) => `<option value="${Number(d.id_descuento)}">${esc(d.nombre)} (${Number(d.porcentaje)}%)</option>`).join("");
            }

            renderizarFacturas();
            actualizarPanelTurnoCaja();
            filtrarPedidosPendientes();
        } catch (err) {
            mostrarMensaje(err.message || "Error al cargar los datos de caja.", "error");
        }
    }

    function pedidosPendientesCobro() {
        return pedidosCache.filter((p) => p.estado === "ENTREGADO" && Number(p.saldo_pendiente || p.total || 0) > 0);
    }

    function filtrarPedidosPendientes() {
        const selector = document.getElementById("order-select");
        const nameFilter = normalizarTexto(document.getElementById("client-name")?.value);
        const emailFilter = normalizarTexto(document.getElementById("client-email")?.value);

        let pendientes = pedidosPendientesCobro();
        if (nameFilter || emailFilter) {
            const filtrados = pendientes.filter((p) => {
                const nomOk = !nameFilter || normalizarTexto(p.cliente_nombre).includes(nameFilter);
                const mailOk = !emailFilter || normalizarTexto(p.cliente_correo).includes(emailFilter);
                return nomOk && mailOk;
            });
            pendientes = filtrados;
        }

        if (!pendientes.length) {
            selector.innerHTML = '<option value="">No hay pedidos entregados pendientes de cobro</option>';
            selector.disabled = true;
            pedidoSeleccionado = null;
            document.getElementById("mesa-venta").value = "";
            document.getElementById("pedido-resumen").innerHTML = "<p>No hay pedidos en estado ENTREGADO pendientes de cobro.</p>";
            actualizarTotales(null);
            return;
        }

        selector.innerHTML = pendientes.map((p) =>
            `<option value="${Number(p.id_pedido)}">Pedido #${Number(p.id_pedido)} · Mesa ${p.numero_mesa || "—"} · ${formatearDinero(p.total)}</option>`
        ).join("");
        selector.disabled = false;
        const actualVal = pedidoSeleccionado ? String(pedidoSeleccionado.id_pedido) : String(pendientes[0].id_pedido);
        selector.value = pendientes.some((p) => String(p.id_pedido) === actualVal) ? actualVal : String(pendientes[0].id_pedido);
        seleccionarPedido(selector.value);
    }

    function seleccionarPedido(idPedido) {
        pedidoSeleccionado = pedidosCache.find((p) => Number(p.id_pedido) === Number(idPedido)) || null;
        if (!pedidoSeleccionado) {
            actualizarTotales(null);
            return;
        }
        document.getElementById("mesa-venta").value = pedidoSeleccionado.numero_mesa ? `Mesa ${pedidoSeleccionado.numero_mesa}` : "Mostrador";
        const items = Array.isArray(pedidoSeleccionado.items) ? pedidoSeleccionado.items : [];
        const filas = items.map((i) => `<tr><td>${esc(i.producto_nombre)}</td><td>${Number(i.cantidad)}</td><td>${formatearDinero(i.precio_unitario)}</td><td>${formatearDinero(i.subtotal)}</td></tr>`).join("");
        document.getElementById("pedido-resumen").innerHTML = `
            <p><strong>Pedido:</strong> #${Number(pedidoSeleccionado.id_pedido)}</p>
            <p><strong>Cliente:</strong> ${esc(pedidoSeleccionado.cliente_nombre || "Sin cliente")}</p>
            <p><strong>Mesa:</strong> ${esc(pedidoSeleccionado.numero_mesa || "Mostrador")}</p>
            <div class="tabla-pedido-scroll"><table><thead><tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Subtotal</th></tr></thead><tbody>${filas}</tbody></table></div>`;
        actualizarTotales(pedidoSeleccionado);
    }

    async function aplicarDescuento() {
        if (!pedidoSeleccionado) {
            mostrarMensaje("Selecciona un pedido entregado primero.", "error");
            return;
        }
        const idDesc = Number(document.getElementById("discount-select")?.value || 0);
        if (!idDesc) return;
        try {
            const res = await window.RequestyAPI.post("api/pedidos/descuento", {
                id_pedido: Number(pedidoSeleccionado.id_pedido),
                id_descuento: idDesc
            });
            pedidoSeleccionado = res.pedido;
            seleccionarPedido(pedidoSeleccionado.id_pedido);
            mostrarMensaje("Descuento aplicado y totales recalculados en PostgreSQL.");
            await cargarDatos();
        } catch (err) {
            mostrarMensaje(err.message || "No se pudo aplicar el descuento.", "error");
        }
    }

    async function procesarCobro() {
        if (modoClienteCaja) return;
        if (!pedidoSeleccionado) {
            mostrarMensaje("Selecciona un pedido entregado antes de cobrar.", "error");
            return;
        }
        if (!cajaAbierta) {
            mostrarMensaje("Debes abrir un turno de caja en la pestaña Turno y Cierre antes de cobrar.", "error");
            return;
        }
        const propina = Math.max(0, Number(document.getElementById("propina")?.value || 0));
        const metodo = document.getElementById("payment-method")?.value || "EFECTIVO";
        const saldo = Number(pedidoSeleccionado.saldo_pendiente ?? pedidoSeleccionado.total);

        try {
            // 1. Registrar Pago (+ propina y distribucion al mesero)
            await window.RequestyAPI.post("api/pagos", {
                id_pedido: Number(pedidoSeleccionado.id_pedido),
                id_caja: Number(cajaAbierta.id_caja),
                metodo_pago: metodo,
                valor: saldo,
                estado: "COMPLETADO",
                referencia: `POS-${Date.now()}`,
                propina,
                distribuir_mesero: propina > 0
            });

            // 2. Transicionar pedido a PAGADO
            await window.RequestyAPI.post("api/pedidos", {
                id_pedido: Number(pedidoSeleccionado.id_pedido),
                estado: "PAGADO"
            });

            // 3. Emitir y aceptar Factura Electronica DIAN segura
            const numFac = `FE-${Date.now().toString().slice(-6)}`;
            const cufe = `CUFE-${pedidoSeleccionado.id_pedido}-${Date.now()}`;
            await window.RequestyAPI.post("api/facturas", {
                id_pedido: Number(pedidoSeleccionado.id_pedido),
                numero_factura: numFac,
                xml: `<Invoice><ID>${numFac}</ID><Pedido>${Number(pedidoSeleccionado.id_pedido)}</Pedido></Invoice>`,
                aceptar: true,
                cufe
            });

            mostrarMensaje(`Pago, propina y factura ${numFac} (ACEPTADA) registrados en PostgreSQL.`);
            document.getElementById("propina").value = "0";
            pedidoSeleccionado = null;
            await cargarDatos();
        } catch (err) {
            mostrarMensaje(err.message || "No se pudo completar el cobro.", "error");
        }
    }

    function renderizarFacturas() {
        const tbody = document.getElementById("facturas-historial");
        if (!tbody) return;
        tbody.innerHTML = facturasCache.length ? facturasCache.map((f) => {
            const anularBtn = f.estado_dian === "ACEPTADA"
                ? `<button type="button" class="btn-secondary" data-anular-factura="${Number(f.id_factura)}">Anular</button>`
                : "";
            return `<tr>
                <td><strong>${esc(f.numero_factura)}</strong></td>
                <td>#${Number(f.id_pedido)}</td>
                <td>${esc(f.cliente_nombre || "Mostrador")}<br><small>${esc(f.cliente_correo || "")}</small></td>
                <td>${esc(f.numero_mesa ? `Mesa ${f.numero_mesa}` : "Mostrador")}</td>
                <td>${formatearDinero(f.total)}</td>
                <td>${esc(f.metodo_pago || "—")}</td>
                <td>${esc(f.estado_dian)}</td>
                <td><details><summary>Ver factura</summary>
                    <p>Mesero: ${esc(f.mesero_nombre || "N/D")}</p>
                    <p>Fecha: ${esc(new Date(f.fecha).toLocaleString("es-CO"))}</p>
                    <p>Subtotal: ${formatearDinero(f.subtotal)} · Descuento: ${formatearDinero(f.descuento_total)} · Impuesto: ${formatearDinero(f.impuesto_total)}</p>
                    <p>Propina: ${formatearDinero(f.propina_total)}</p>
                    ${f.cufe ? `<p>CUFE: <code>${esc(f.cufe)}</code></p>` : ""}
                    ${anularBtn}
                </details></td>
            </tr>`;
        }).join("") : '<tr><td colspan="8">Todavía no hay facturas registradas.</td></tr>';
    }

    function actualizarPanelTurnoCaja() {
        const estadoEl = document.getElementById("estado-turno-caja");
        const panelAbrir = document.getElementById("panel-abrir-caja");
        const panelCerrar = document.getElementById("panel-cerrar-caja");

        if (!cajaAbierta) {
            if (estadoEl) estadoEl.textContent = "No tienes una caja ABIERTA actualmente.";
            if (panelAbrir) panelAbrir.hidden = false;
            if (panelCerrar) panelCerrar.hidden = true;
            return;
        }

        if (estadoEl) estadoEl.textContent = `Caja #${cajaAbierta.id_caja} ABIERTA desde ${new Date(cajaAbierta.fecha_apertura).toLocaleString("es-CO")}.`;
        if (panelAbrir) panelAbrir.hidden = true;
        if (panelCerrar) panelCerrar.hidden = false;

        const digitales = Number(cajaAbierta.total_ventas_digitales || 0) + Number(cajaAbierta.total_propinas_digitales || 0);
        document.getElementById("cierre-apertura-sistema").textContent = formatearDinero(cajaAbierta.monto_apertura);
        document.getElementById("cierre-efectivo-sistema").textContent = formatearDinero(cajaAbierta.efectivo_esperado);
        document.getElementById("cierre-electronico-sistema").textContent = formatearDinero(digitales);
    }

    async function abrirTurnoCaja() {
        const monto = Number(document.getElementById("monto-apertura")?.value || 0);
        try {
            await window.RequestyAPI.post("api/caja/abrir", {
                monto_apertura: monto,
                observaciones: "Apertura desde panel web"
            });
            await cargarDatos();
            document.getElementById("resultado-cierre-box").textContent = "Turno de caja abierto correctamente.";
        } catch (err) {
            document.getElementById("resultado-cierre-box").textContent = err.message || "No se pudo abrir la caja.";
        }
    }

    async function cerrarTurnoCaja() {
        if (!cajaAbierta) return;
        const raw = document.getElementById("efectivo-manual")?.value;
        const efectivo = Number(raw);
        if (raw === "" || !Number.isFinite(efectivo) || efectivo < 0) {
            document.getElementById("resultado-cierre-box").textContent = "Ingresa el monto de efectivo contado válido.";
            return;
        }
        try {
            const res = await window.RequestyAPI.post("api/caja/cerrar", {
                id_caja: Number(cajaAbierta.id_caja),
                efectivo_contado: efectivo,
                observaciones: "Arqueo desde panel web"
            });
            const closed = res.caja;
            const dif = Number(closed.diferencia || 0);
            const txtDif = dif === 0 ? "Cuadrada exacto" : dif < 0 ? `Faltante ${formatearDinero(Math.abs(dif))}` : `Sobrante ${formatearDinero(dif)}`;
            document.getElementById("resultado-cierre-box").innerHTML = `
                <h4>CIERRE CONFIRMADO (#${Number(closed.id_caja)})</h4>
                <p>Efectivo esperado: ${formatearDinero(closed.efectivo_esperado)}</p>
                <p>Efectivo contado: ${formatearDinero(closed.efectivo_contado)}</p>
                <p><strong>Resultado: ${esc(txtDif)}</strong></p>`;
            await cargarDatos();
        } catch (err) {
            document.getElementById("resultado-cierre-box").textContent = err.message || "No se pudo cerrar la caja.";
        }
    }

    async function renderizarPedidosClienteCaja() {
        const contenedor = document.getElementById("pedidos-cliente-caja");
        if (!contenedor) return;
        try {
            const res = await window.RequestyAPI.get("api/pedidos");
            const pendientes = (res?.pedidos || []).filter((p) => p.estado === "ENTREGADO" && Number(p.saldo_pendiente || p.total || 0) > 0);
            if (!pendientes.length) {
                contenedor.textContent = "No tienes pedidos entregados pendientes de pago.";
                return;
            }
            contenedor.innerHTML = pendientes.map((p) => `
                <article class="pedido-caja-cliente">
                    <h4>Pedido #${Number(p.id_pedido)} · Mesa ${esc(p.numero_mesa || "—")}</h4>
                    <p><strong>Total pendiente:</strong> ${formatearDinero(p.saldo_pendiente || p.total)}</p>
                    <p>Estado: Entregado · pendiente de pago presencial en caja</p>
                </article>`).join("");
        } catch (err) {
            contenedor.textContent = err.message || "No se pudo consultar tus pedidos.";
        }
    }

    document.getElementById("caja-tabs-nav")?.addEventListener("click", (e) => {
        const btn = e.target.closest("button[data-tab]");
        if (!btn || modoClienteCaja) return;
        const tabId = btn.dataset.tab;
        ["ventas", "facturas", "cierre"].forEach((id) => {
            document.getElementById(id).style.display = id === tabId ? "block" : "none";
        });
    });

    document.getElementById("client-name")?.addEventListener("input", filtrarPedidosPendientes);
    document.getElementById("client-email")?.addEventListener("input", filtrarPedidosPendientes);
    document.getElementById("order-select")?.addEventListener("change", (e) => seleccionarPedido(e.target.value));
    document.getElementById("propina")?.addEventListener("input", () => actualizarTotales(pedidoSeleccionado));
    document.getElementById("btn-aplicar-descuento")?.addEventListener("click", aplicarDescuento);
    document.getElementById("btn-procesar-cobro")?.addEventListener("click", procesarCobro);
    document.getElementById("btn-abrir-caja")?.addEventListener("click", abrirTurnoCaja);
    document.getElementById("btn-confirmar-cierre")?.addEventListener("click", cerrarTurnoCaja);

    document.getElementById("facturas-historial")?.addEventListener("click", async (e) => {
        const btn = e.target.closest("button[data-anular-factura]");
        if (!btn) return;
        if (!window.confirm("¿Anular esta factura DIAN?")) return;
        try {
            await window.RequestyAPI.post("api/facturas/anular", { id_factura: Number(btn.dataset.anularFactura) });
            await cargarDatos();
        } catch (err) {
            mostrarMensaje(err.message || "No se pudo anular la factura.", "error");
        }
    });

    if (window.RequestyAPI.leerSesion()) {
        cargarDatos();
    } else {
        window.addEventListener("requesty-sesion-lista", cargarDatos, { once: true });
    }
})();
