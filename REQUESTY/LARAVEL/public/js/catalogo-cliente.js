// Catálogo digital conectado 100% a PostgreSQL (api/productos, api/mesas, api/pedidos)
(function () {
    const contenedorCatalogo = document.getElementById("catalogo-menu");
    const filtrosCatalogo = document.getElementById("filtros-menu");
    const campoBusquedaMenu = document.getElementById("buscar-menu");
    const dialogoProducto = document.getElementById("detalle-producto");
    if (!contenedorCatalogo || !filtrosCatalogo) return;

    let productosCache = [];
    let mesaClienteActual = null;
    let filtroActual = "Todas";
    let busquedaActual = "";
    let esCliente = false;
    let puedeCrearPedido = false;

    function esc(v) {
        return window.RequestyAPI.escaparHTML(v);
    }

    function formatearPrecio(precio) {
        return new Intl.NumberFormat("es-CO", { maximumFractionDigits: 0 }).format(Number(precio || 0));
    }

    function normalizarEstadoSeguimiento(estado) {
        const st = String(estado || "").toUpperCase();
        if (st === "PENDIENTE") return "En cola";
        if (st === "EN_PREPARACION") return "En preparación";
        if (st === "LISTO") return "Listo para servir";
        if (st === "ENTREGADO") return "Entregado";
        if (st === "PAGADO") return "Pagado";
        if (st === "CANCELADO") return "Cancelado";
        return st;
    }

    function esImagenSegura(valor) {
        const v = String(valor || "");
        return /^img\/tarjeta\/[a-z0-9-]+\.(?:jpg|jpeg|png|webp)$/i.test(v)
            || /^https?:\/\/.+/i.test(v)
            || /^data:image\/(?:webp|png|jpeg);base64,/i.test(v);
    }

    function renderizarFiltros() {
        const categorias = [...new Set(productosCache.map((p) => p.categoria).filter(Boolean))];
        const opciones = ["Todas", ...categorias];
        if (!opciones.includes(filtroActual)) filtroActual = "Todas";
        filtrosCatalogo.innerHTML = opciones.map((cat) =>
            `<button type="button" data-categoria="${esc(cat)}" aria-pressed="${cat === filtroActual}">${esc(cat)}</button>`
        ).join("");
    }

    function renderizarCatalogo() {
        renderizarFiltros();
        const visibles = productosCache.filter((producto) => {
            const catOk = filtroActual === "Todas" || producto.categoria === filtroActual;
            const ingTxt = (producto.ingredientes || []).join(" ");
            const txt = `${producto.nombre} ${producto.categoria} ${ingTxt}`.toLocaleLowerCase("es");
            return catOk && txt.includes(busquedaActual);
        });

        const disponiblesCount = visibles.filter((p) => p.disponible_stock).length;
        document.getElementById("conteo-menu").textContent =
            `${visibles.length} ${visibles.length === 1 ? "plato" : "platos"} en el menú · ${disponiblesCount} ${disponiblesCount === 1 ? "disponible" : "disponibles"}`;

        if (!visibles.length) {
            contenedorCatalogo.innerHTML = '<p class="catalogo-vacio">No encontramos productos con esos filtros.</p>';
            return;
        }

        contenedorCatalogo.innerHTML = visibles.map((producto) => {
            const disponible = Boolean(producto.disponible_stock);
            const agotados = Array.isArray(producto.ingredientes_agotados) ? producto.ingredientes_agotados : [];
            const msgDisp = disponible
                ? "Disponible"
                : agotados.length
                    ? `No Disponible · Falta stock de: ${agotados.join(", ")}`
                    : "No Disponible";
            const imgHtml = esImagenSegura(producto.imagen)
                ? `<img class="catalogo-imagen" src="${esc(producto.imagen)}" alt="${esc(producto.nombre)}" loading="lazy">`
                : "";
            const btnPedido = puedeCrearPedido
                ? `<button class="agregar-carrito ${disponible ? "" : "sin-stock"}" type="button" data-agregar-pedido="${Number(producto.id_producto)}" ${disponible ? "" : "disabled"}>${disponible ? "Pedir este plato" : "Falta de stock"}</button>`
                : "";
            return `
                <article class="plato ${disponible ? "" : "agotado"}">
                    ${imgHtml}
                    <div class="plato-info">
                        <span class="plato-categoria">${esc(producto.categoria || "General")}</span>
                        <span class="estado-disponibilidad ${disponible ? "" : "agotado"}" role="status">${esc(msgDisp)}</span>
                        <div class="plato-titulo">
                            <h2 class="${disponible ? "" : "agotado"}">${esc(producto.nombre)}</h2>
                            <span class="plato-precio ${disponible ? "" : "agotado"}">$${formatearPrecio(producto.precio)}</span>
                        </div>
                        <p class="plato-ingredientes"><strong>Ingredientes:</strong> ${esc((producto.ingredientes || []).join(", ") || "Según preparación")}</p>
                        <button class="plato-detalle" type="button" data-producto="${Number(producto.id_producto)}">Ver detalle</button>
                        ${btnPedido}
                    </div>
                </article>`;
        }).join("");
    }

    function mostrarDetalle(id) {
        const producto = productosCache.find((p) => Number(p.id_producto) === Number(id));
        if (!producto || !dialogoProducto) return;

        document.getElementById("categoria-detalle-producto").textContent = producto.categoria || "General";
        const nombreEl = document.getElementById("nombre-detalle-producto");
        nombreEl.textContent = producto.nombre;
        nombreEl.classList.toggle("agotado", !producto.disponible_stock);
        const precioEl = document.getElementById("precio-detalle-producto");
        precioEl.textContent = `$${formatearPrecio(producto.precio)}`;
        precioEl.classList.toggle("agotado", !producto.disponible_stock);
        const estEl = document.getElementById("estado-detalle-producto");
        estEl.textContent = producto.disponible_stock
            ? "Disponible"
            : `No Disponible${producto.ingredientes_agotados?.length ? ` · Falta stock: ${producto.ingredientes_agotados.join(", ")}` : ""}`;
        estEl.classList.toggle("agotado", !producto.disponible_stock);
        document.getElementById("ingredientes-detalle-producto").textContent = (producto.ingredientes || []).join(", ") || "Sin detalle";
        dialogoProducto.showModal();
    }

    async function cargarTodo() {
        const sesion = window.RequestyAPI.leerSesion() || await window.RequestyAPI.cargarSesion();
        const rol = String(sesion?.rol || "").toUpperCase();
        esCliente = rol === "CLIENTE";
        puedeCrearPedido = ["MESERO", "ADMIN", "CAJERO"].includes(rol);

        try {
            const [resProd, resMesas] = await Promise.all([
                window.RequestyAPI.get("api/productos"),
                window.RequestyAPI.get("api/mesas")
            ]);
            productosCache = Array.isArray(resProd?.productos) ? resProd.productos : [];
            const mesas = Array.isArray(resMesas?.mesas) ? resMesas.mesas : [];
            mesaClienteActual = esCliente ? (mesas[0] || null) : null;

            const asignada = !esCliente || Boolean(mesaClienteActual);
            const bloqueoEl = document.getElementById("bloqueo-cliente-mesa");
            if (esCliente && mesaClienteActual) {
                bloqueoEl.hidden = false;
                bloqueoEl.textContent = `Mesa ${mesaClienteActual.numero_mesa} asignada${mesaClienteActual.cliente_nombre ? ` a ${mesaClienteActual.cliente_nombre}` : ""}. El mesero registra tu pedido desde su terminal.`;
            } else {
                bloqueoEl.hidden = asignada;
            }
            document.querySelector(".catalogo-controles").hidden = !asignada;
            document.getElementById("conteo-menu").hidden = !asignada;
            contenedorCatalogo.hidden = !asignada;

            renderizarCatalogo();

            const segSection = document.getElementById("seguimiento-pedidos");
            if (esCliente) {
                segSection.hidden = false;
                const resPed = await window.RequestyAPI.get("api/pedidos");
                const pedidos = Array.isArray(resPed?.pedidos) ? resPed.pedidos : [];
                document.getElementById("lista-seguimiento-pedidos").innerHTML = pedidos.length
                    ? pedidos.map((ped) => {
                        const est = normalizarEstadoSeguimiento(ped.estado);
                        const pendientePago = ped.estado === "ENTREGADO" && Number(ped.saldo_pendiente || 0) > 0;
                        const irCaja = pendientePago
                            ? `<a class="seguimiento-ir-caja" href="/caja?pedido=${Number(ped.id_pedido)}">Ir a caja para pagar</a>`
                            : "";
                        return `<article class="seguimiento-pedido"><strong>Pedido #${Number(ped.id_pedido)}</strong><span>Mesa ${esc(ped.numero_mesa || "—")} · $${formatearPrecio(ped.total)}</span><span class="seguimiento-estado">${esc(est)}</span>${irCaja}</article>`;
                    }).join("")
                    : "<p>Aún no tienes pedidos registrados.</p>";
            } else {
                segSection.hidden = true;
            }
        } catch (err) {
            const msgEl = document.getElementById("mensaje-menu-inventario");
            if (msgEl) msgEl.textContent = err.message || "No se pudo cargar el menú desde PostgreSQL.";
        }
    }

    filtrosCatalogo.addEventListener("click", (e) => {
        const btn = e.target.closest("button[data-categoria]");
        if (!btn) return;
        filtroActual = btn.dataset.categoria;
        renderizarCatalogo();
    });

    campoBusquedaMenu?.addEventListener("input", () => {
        busquedaActual = campoBusquedaMenu.value.trim().toLocaleLowerCase("es");
        renderizarCatalogo();
    });

    contenedorCatalogo.addEventListener("click", (e) => {
        const agregar = e.target.closest("button[data-agregar-pedido]");
        if (agregar) {
            window.location.href = `/pedidos?agregar=${encodeURIComponent(agregar.dataset.agregarPedido)}`;
            return;
        }
        const btn = e.target.closest("button[data-producto]");
        if (btn) mostrarDetalle(btn.dataset.producto);
    });

    document.getElementById("cerrar-detalle-producto")?.addEventListener("click", () => dialogoProducto?.close());
    dialogoProducto?.addEventListener("click", (e) => {
        if (e.target === dialogoProducto) dialogoProducto.close();
    });

    if (window.RequestyAPI.leerSesion()) {
        cargarTodo();
    } else {
        window.addEventListener("requesty-sesion-lista", cargarTodo, { once: true });
    }
})();
