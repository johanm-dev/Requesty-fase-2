// KDS Cocina conectado 100% a PostgreSQL (api/pedidos + descuento atomico de inventario)
(function () {
    const minutosAlerta = 10;
    const minutosCriticos = 20;
    let pedidosCache = [];

    function esc(v) {
        return window.RequestyAPI.escaparHTML(v);
    }

    function etiquetaEstado(st) {
        if (st === "PENDIENTE") return "En cola";
        if (st === "EN_PREPARACION") return "En preparación";
        if (st === "LISTO") return "Listo para servir";
        return st;
    }

    function mostrarError(msg) {
        const el = document.getElementById("mensaje-cocina");
        if (el) el.textContent = msg || "";
    }

    function minutosEspera(pedido, ahora = Date.now()) {
        const ts = Date.parse(pedido.fecha_hora || "");
        if (!Number.isFinite(ts)) return 0;
        return Math.max(0, Math.floor((ahora - ts) / 60000));
    }

    function renderizarItem(item) {
        const detalles = [];
        if (item.termino_coccion) detalles.push(`Término: ${item.termino_coccion}`);
        if (item.observaciones) detalles.push(`Nota: ${item.observaciones}`);
        if (item.ingredientes_excluidos?.length) detalles.push(`Sin: ${item.ingredientes_excluidos.join(", ")}`);
        (item.extras || []).forEach((ext) => detalles.push(`Extra: ${ext.nombre} × ${ext.cantidad}`));
        return `<li><strong>${Number(item.cantidad) || 1} × ${esc(item.producto_nombre || "Producto")}</strong>${detalles.length
            ? `<ul class="personalizaciones">${detalles.map((d) => `<li>${esc(d)}</li>`).join("")}</ul>`
            : ""}</li>`;
    }

    function renderizarPedido(pedido, ahora) {
        const st = String(pedido.estado || "PENDIENTE").toUpperCase();
        const espera = minutosEspera(pedido, ahora);
        const claseEstado = st === "PENDIENTE" ? "estado-pendiente" : st === "EN_PREPARACION" ? "estado-preparacion" : "estado-listo";
        const claseAlerta = st === "LISTO" ? "alerta-listo" : espera >= minutosCriticos ? "alerta-critica" : espera >= minutosAlerta ? "alerta-advertencia" : "alerta-normal";
        const hora = new Date(pedido.fecha_hora).toLocaleTimeString("es-CO", { hour: "2-digit", minute: "2-digit" });
        const accion = st === "PENDIENTE"
            ? `<button class="boton-accion" type="button" data-estado-pedido="${Number(pedido.id_pedido)}" data-nuevo-estado="EN_PREPARACION">Iniciar preparación</button>`
            : st === "EN_PREPARACION"
                ? `<button class="boton-accion" type="button" data-estado-pedido="${Number(pedido.id_pedido)}" data-nuevo-estado="LISTO">Marcar listo para servir</button>`
                : '<p class="mensaje-listo">Listo para servir</p>';

        const itemsHtml = Array.isArray(pedido.items) && pedido.items.length
            ? pedido.items.map(renderizarItem).join("")
            : "<li>Sin productos registrados.</li>";

        const destino = pedido.numero_mesa ? `Mesa ${pedido.numero_mesa}${pedido.cliente_nombre ? ` · ${pedido.cliente_nombre}` : ""}` : (pedido.observaciones || "Mostrador");
        return `<article class="tarjeta-pedido ${claseEstado} ${claseAlerta}" data-pedido-id="${Number(pedido.id_pedido)}">
            <div class="pedido-encabezado">
                <span class="pedido-numero">PEDIDO #${Number(pedido.id_pedido)}</span>
                <span class="pedido-espera" role="status">${espera} min</span>
            </div>
            <p class="pedido-info">${esc(destino)}</p>
            <p class="pedido-info">Recibido: ${esc(hora)}</p>
            <p class="pedido-seccion-titulo">Productos</p>
            <ul class="lista-productos">${itemsHtml}</ul>
            <span class="etiqueta-estado ${claseEstado}">${esc(etiquetaEstado(st))}</span>
            ${accion}
        </article>`;
    }

    async function cargarPedidos() {
        try {
            const res = await window.RequestyAPI.get("api/pedidos");
            const todos = Array.isArray(res?.pedidos) ? res.pedidos : [];
            pedidosCache = todos.filter((p) => ["PENDIENTE", "EN_PREPARACION", "LISTO"].includes(String(p.estado).toUpperCase()));
            const contenedor = document.getElementById("contenedor-pedidos");
            const ahora = Date.now();
            if (contenedor) {
                contenedor.innerHTML = pedidosCache.length
                    ? pedidosCache.map((p) => renderizarPedido(p, ahora)).join("")
                    : '<p class="cocina-vacia">No hay comandas pendientes. Los pedidos nuevos aparecerán aquí automáticamente.</p>';
            }
            document.getElementById("contador-pendientes").textContent = pedidosCache.filter((p) => p.estado === "PENDIENTE").length;
            document.getElementById("contador-preparacion").textContent = pedidosCache.filter((p) => p.estado === "EN_PREPARACION").length;
            document.getElementById("contador-listos").textContent = pedidosCache.filter((p) => p.estado === "LISTO").length;
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

    document.getElementById("contenedor-pedidos")?.addEventListener("click", (e) => {
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
