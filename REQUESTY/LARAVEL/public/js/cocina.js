// Cocina KDS: Cuerda con libretas colgantes + animación de arrugar papel conectada 100% a PostgreSQL (api/pedidos)
(function () {
    const minutosAlerta = 10;
    const minutosCriticos = 20;
    let pedidosCache = [];
    let mesaFiltroActual = "TODAS";
    const pedidosDesechados = new Set();

    function esc(v) {
        return window.RequestyAPI.escaparHTML(v);
    }

    function mostrarError(msg) {
        const el = document.getElementById("mensaje-cocina");
        if (el) el.textContent = msg || "";
    }

    function minutosEspera(pedido, ahora) {
        const ts = Date.parse(pedido.fecha_hora || "");
        if (!Number.isFinite(ts)) return 0;
        return Math.max(0, Math.floor((ahora - ts) / 60000));
    }

    function etiquetaEstado(estado) {
        const st = String(estado || "").toUpperCase();
        if (st === "PENDIENTE") return "En espera · Pendiente por iniciar";
        if (st === "EN_PREPARACION") return "En preparación · Cocinando en estación";
        if (st === "LISTO") return "Listo para servir · Esperando retiro";
        return st;
    }

    function obtenerClaveMesa(pedido) {
        return pedido.numero_mesa ? `Mesa ${pedido.numero_mesa}` : "Mostrador";
    }

    function renderizarFiltrosMesa() {
        const dock = document.getElementById("filtros-mesa-cocina");
        if (!dock) return;

        const mesasUnicas = [...new Set(pedidosCache.map(obtenerClaveMesa))];
        if (mesaFiltroActual !== "TODAS" && !mesasUnicas.includes(mesaFiltroActual)) {
            mesaFiltroActual = "TODAS";
        }

        const botones = [
            `<button type="button" class="kds-mesa-pill ${mesaFiltroActual === "TODAS" ? "activo" : ""}" data-mesa-filtro="TODAS">
                <span class="kds-mesa-num">Todas</span>
                <span class="kds-mesa-id">${pedidosCache.length}</span>
            </button>`,
            ...mesasUnicas.map((mesa) => {
                const cant = pedidosCache.filter((p) => obtenerClaveMesa(p) === mesa).length;
                return `<button type="button" class="kds-mesa-pill ${mesaFiltroActual === mesa ? "activo" : ""}" data-mesa-filtro="${esc(mesa)}">
                    <span class="kds-mesa-num">${esc(mesa)}</span>
                    <span class="kds-mesa-id">${cant}</span>
                </button>`;
            })
        ];
        dock.innerHTML = botones.join("");
    }

    function renderizarItem(item) {
        const detalles = [];
        if (item.termino_coccion) detalles.push(`Término: ${item.termino_coccion}`);
        if (item.observaciones) detalles.push(`Nota: ${item.observaciones}`);
        if (item.ingredientes_excluidos?.length) detalles.push(`Sin: ${item.ingredientes_excluidos.join(", ")}`);
        (item.extras || []).forEach((ext) => detalles.push(`Extra: ${ext.nombre} × ${ext.cantidad}`));

        return `<li class="libreta-item">
            <div class="libreta-item-principal">
                <span class="libreta-item-cant">${Number(item.cantidad) || 1}</span>
                <strong class="libreta-item-nombre">${esc(item.producto_nombre || "Producto")}</strong>
            </div>
            ${detalles.length ? `<ul class="personalizaciones libreta-especificaciones">${detalles.map((d) => `<li>${esc(d)}</li>`).join("")}</ul>` : ""}
        </li>`;
    }

    function renderizarPedido(pedido, ahora) {
        const st = String(pedido.estado || "PENDIENTE").toUpperCase();
        const espera = minutosEspera(pedido, ahora);
        const claseEstado = st === "PENDIENTE" ? "estado-pendiente" : st === "EN_PREPARACION" ? "estado-preparacion" : "estado-listo";
        const esCritico = st !== "LISTO" && espera >= minutosCriticos;
        const claseAlerta = st === "LISTO" ? "alerta-listo" : esCritico ? "alerta-critica prioridad-alta" : espera >= minutosAlerta ? "alerta-advertencia" : "alerta-normal";
        const hora = new Date(pedido.fecha_hora).toLocaleTimeString("es-CO", { hour: "2-digit", minute: "2-digit" });
        const mesaTexto = obtenerClaveMesa(pedido);
        const idNum = Number(pedido.id_pedido);

        const accion = st === "PENDIENTE"
            ? `<button class="boton-accion btn-iniciar" type="button" data-estado-pedido="${idNum}" data-nuevo-estado="EN_PREPARACION">
                <i class="fa-solid fa-fire-burner"></i> Iniciar preparación
               </button>`
            : st === "EN_PREPARACION"
                ? `<button class="boton-accion btn-completar" type="button" data-estado-pedido="${idNum}" data-nuevo-estado="LISTO">
                    <i class="fa-solid fa-check-double"></i> Marcar listo
                   </button>`
                : `<button class="boton-accion btn-desechar-nota" type="button" data-desechar-pedido="${idNum}">
                    <i class="fa-solid fa-trash-can"></i> Completar y tirar nota
                   </button>`;

        const itemsHtml = Array.isArray(pedido.items) && pedido.items.length
            ? pedido.items.map(renderizarItem).join("")
            : '<li class="libreta-item">Sin productos registrados.</li>';

        return `<article class="tarjeta-pedido libreta-colgante ${claseEstado} ${claseAlerta}" data-pedido-id="${idNum}">
            <div class="pinza-cuerda" aria-hidden="true">
                <span class="pinza-argolla"></span>
                <span class="pinza-cuerpo"></span>
            </div>
            <div class="libreta-papel">
                <div class="libreta-perforado" aria-hidden="true">
                    <span></span><span></span><span></span><span></span><span></span><span></span>
                </div>
                <header class="libreta-encabezado">
                    <div class="libreta-meta-principal">
                        <span class="pedido-numero">#${idNum}</span>
                        <span class="libreta-mesa">${esc(mesaTexto)}</span>
                        <span class="libreta-hora"><i class="fa-regular fa-clock"></i> ${esc(hora)}</span>
                    </div>
                    <span class="pedido-espera" role="status">${espera} min</span>
                </header>
                <div class="libreta-cuerpo">
                    <ul class="lista-productos libreta-lista">${itemsHtml}</ul>
                </div>
                <footer class="libreta-pie">
                    <div class="etiqueta-estado ${claseEstado}">
                        <span class="estado-punto"></span>
                        <span>${esc(etiquetaEstado(st))}</span>
                    </div>
                    ${accion}
                </footer>
            </div>
        </article>`;
    }

    function renderizarTablero() {
        renderizarFiltrosMesa();
        const contenedor = document.getElementById("contenedor-pedidos");
        const ahora = Date.now();
        const visibles = mesaFiltroActual === "TODAS"
            ? pedidosCache
            : pedidosCache.filter((p) => obtenerClaveMesa(p) === mesaFiltroActual);

        if (contenedor) {
            contenedor.innerHTML = visibles.length
                ? visibles.map((p) => renderizarPedido(p, ahora)).join("")
                : '<p class="cocina-vacia">No hay comandas pendientes en la cuerda. Los pedidos nuevos aparecerán aquí automáticamente.</p>';
        }
        const cPend = document.getElementById("contador-pendientes");
        const cPrep = document.getElementById("contador-preparacion");
        const cList = document.getElementById("contador-listos");
        if (cPend) cPend.textContent = String(pedidosCache.filter((p) => p.estado === "PENDIENTE").length);
        if (cPrep) cPrep.textContent = String(pedidosCache.filter((p) => p.estado === "EN_PREPARACION").length);
        if (cList) cList.textContent = String(pedidosCache.filter((p) => p.estado === "LISTO").length);
    }

    async function cargarPedidos() {
        try {
            const res = await window.RequestyAPI.get("api/pedidos");
            const todos = Array.isArray(res?.pedidos) ? res.pedidos : [];
            pedidosCache = todos.filter((p) => {
                const st = String(p.estado || "").toUpperCase();
                return ["PENDIENTE", "EN_PREPARACION", "LISTO"].includes(st) && !pedidosDesechados.has(Number(p.id_pedido));
            });
            renderizarTablero();
            mostrarError("");
        } catch (err) {
            mostrarError(err.message || "No se pudieron consultar las comandas.");
        }
    }

    async function cambiarEstadoPedido(idPedido, nuevoEstado) {
        try {
            await window.RequestyAPI.post("api/pedidos", { id_pedido: Number(idPedido), estado: nuevoEstado });
            mostrarError("");
            await cargarPedidos();
        } catch (err) {
            mostrarError(err.message || "No se pudo actualizar la comanda.");
        }
    }

    function animarYDesecharNota(idPedido, tarjeta) {
        const idNum = Number(idPedido);
        if (tarjeta) {
            tarjeta.classList.add("arrugando-nota", "desechando-papel");
            window.setTimeout(() => {
                pedidosDesechados.add(idNum);
                pedidosCache = pedidosCache.filter((p) => Number(p.id_pedido) !== idNum);
                renderizarTablero();
            }, 680);
        } else {
            pedidosDesechados.add(idNum);
            pedidosCache = pedidosCache.filter((p) => Number(p.id_pedido) !== idNum);
            renderizarTablero();
        }
    }

    document.getElementById("filtros-mesa-cocina")?.addEventListener("click", (e) => {
        const pill = e.target.closest("[data-mesa-filtro]");
        if (!pill) return;
        mesaFiltroActual = pill.dataset.mesaFiltro || "TODAS";
        renderizarTablero();
    });

    document.getElementById("contenedor-pedidos")?.addEventListener("click", (e) => {
        const btnDesechar = e.target.closest("[data-desechar-pedido]");
        if (btnDesechar) {
            const tarjeta = btnDesechar.closest(".tarjeta-pedido");
            animarYDesecharNota(btnDesechar.dataset.desecharPedido, tarjeta);
            return;
        }
        const btn = e.target.closest("[data-estado-pedido]");
        if (btn) {
            cambiarEstadoPedido(btn.dataset.estadoPedido, btn.dataset.nuevoEstado);
        }
    });

    if (window.RequestyAPI.leerSesion()) {
        cargarPedidos();
    } else {
        window.addEventListener("requesty-sesion-lista", cargarPedidos, { once: true });
    }
    window.setInterval(cargarPedidos, 5000);
})();
