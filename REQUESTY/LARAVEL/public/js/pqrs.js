// Módulo PQRS conectado 100% a PostgreSQL (api/pqrs, api/clientes, api/pedidos)
(function () {
    const form = document.getElementById("form-pqrs");
    const selectCliente = document.getElementById("id_cliente");
    const grupoCliente = document.getElementById("grupo-cliente-pqrs");
    const selectPedido = document.getElementById("id_pedido");
    const mensaje = document.getElementById("pqrs-mensaje");
    const listaContenedor = document.getElementById("lista-pqrs-propios");

    if (!form) return;

    function mostrarMensaje(texto, esError = false) {
        if (!mensaje) return;
        mensaje.textContent = texto;
        mensaje.style.color = esError ? "#b91c1c" : "#15803d";
    }

    async function cargarDatos() {
        try {
            const sesion = window.RequestyAPI.leerSesion() || await window.RequestyAPI.cargarSesion();
            const rol = String(sesion?.rol || "").toUpperCase();

            if (rol === "CLIENTE") {
                if (grupoCliente) grupoCliente.hidden = true;
                if (selectCliente) selectCliente.required = false;
            } else {
                const resCli = await window.RequestyAPI.get("api/clientes");
                const clientes = resCli?.clientes || [];
                if (selectCliente) {
                    selectCliente.innerHTML = '<option value="" disabled selected>Selecciona un cliente</option>' +
                        clientes.map((c) => `<option value="${Number(c.id_cliente)}">${window.RequestyAPI.escaparHTML(c.nombre)}${c.correo ? ` (${window.RequestyAPI.escaparHTML(c.correo)})` : ""}</option>`).join("");
                }
            }

            try {
                const resPed = await window.RequestyAPI.get("api/pedidos");
                const pedidos = resPed?.pedidos || [];
                if (selectPedido) {
                    selectPedido.innerHTML = '<option value="">Ninguno</option>' +
                        pedidos.map((p) => `<option value="${Number(p.id_pedido)}">Pedido #${Number(p.id_pedido)} · $${Number(p.total || 0).toLocaleString("es-CO")} (${window.RequestyAPI.escaparHTML(p.estado)})</option>`).join("");
                }
            } catch {
                // Opcional si el usuario aún no tiene pedidos
            }

            await cargarListaPqrs();
        } catch (err) {
            mostrarMensaje(err.message || "No se pudieron cargar los datos de PQRS.", true);
        }
    }

    async function cargarListaPqrs() {
        if (!listaContenedor) return;
        try {
            const res = await window.RequestyAPI.get("api/pqrs");
            const lista = res?.pqrs || [];
            if (!lista.length) {
                listaContenedor.innerHTML = "<p>Todavía no hay solicitudes PQRS registradas.</p>";
                return;
            }
            listaContenedor.innerHTML = lista.map((item) => `
                <article style="padding:12px; border:1px solid #e5e7eb; border-radius:8px; margin-bottom:10px;">
                    <div style="display:flex; justify-content:space-between; gap:8px;">
                        <strong>#${Number(item.id_pqrs)} · ${window.RequestyAPI.escaparHTML(item.tipo)}</strong>
                        <span>${window.RequestyAPI.escaparHTML(item.estado)}</span>
                    </div>
                    <p style="margin:6px 0;">${window.RequestyAPI.escaparHTML(item.descripcion)}</p>
                    <small>${window.RequestyAPI.escaparHTML(item.cliente_nombre || "Cliente")} · ${new Date(item.fecha).toLocaleString("es-CO")}</small>
                </article>
            `).join("");
        } catch (err) {
            listaContenedor.textContent = err.message || "No se pudo consultar el historial de PQRS.";
        }
    }

    form.addEventListener("submit", async (evento) => {
        evento.preventDefault();
        mostrarMensaje("");

        const sesion = window.RequestyAPI.leerSesion();
        const rol = String(sesion?.rol || "").toUpperCase();
        const tipo = document.getElementById("tipo")?.value || "";
        const descripcion = document.getElementById("descripcion")?.value.trim() || "";
        const idCliente = rol === "CLIENTE" ? null : Number(selectCliente?.value || 0);
        const idPedidoRaw = selectPedido?.value || "";

        if (!tipo || descripcion.length < 5 || (rol !== "CLIENTE" && !idCliente)) {
            mostrarMensaje("Completa tipo, cliente y descripción (mínimo 5 caracteres).", true);
            return;
        }

        const payload = {
            tipo,
            descripcion,
            ...(idCliente ? { id_cliente: idCliente } : {}),
            ...(idPedidoRaw ? { id_pedido: Number(idPedidoRaw) } : {})
        };

        try {
            await window.RequestyAPI.post("api/pqrs", payload);
            form.reset();
            mostrarMensaje("PQRS registrado correctamente en PostgreSQL.");
            await cargarListaPqrs();
        } catch (err) {
            mostrarMensaje(err.message || "No se pudo registrar el PQRS.", true);
        }
    });

    if (window.RequestyAPI.leerSesion()) {
        cargarDatos();
    } else {
        window.addEventListener("requesty-sesion-lista", cargarDatos, { once: true });
    }
})();
