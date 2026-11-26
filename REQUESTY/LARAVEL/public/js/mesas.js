// Módulo de Mesas conectado 100% a PostgreSQL (api/mesas)
(function () {
    let mesas = [];
    let mesaSeleccionada = null;
    let filtroActual = "all";

    function esc(v) {
        return window.RequestyAPI.escaparHTML(v);
    }

    function estadoNormalizado(m) {
        return String(m.estado || "DISPONIBLE").toUpperCase();
    }

    function minutosOcupada(m) {
        if (estadoNormalizado(m) !== "OCUPADA" || !m.hora_ocupacion) return 0;
        const t = Date.parse(m.hora_ocupacion);
        if (!Number.isFinite(t)) return 0;
        return Math.max(0, Math.floor((Date.now() - t) / 60000));
    }

    async function cargarMesas() {
        try {
            const res = await window.RequestyAPI.get("api/mesas");
            mesas = Array.isArray(res?.mesas) ? res.mesas : [];
            renderizarMesas(obtenerMesasFiltradas());
            actualizarStats();
        } catch (err) {
            mostrarToast(err.message || "Error al consultar mesas.", true);
        }
    }

    function renderizarMesas(lista) {
        const grid = document.getElementById("mesasGrid");
        if (!grid) return;
        grid.innerHTML = "";

        lista.forEach((mesa) => {
            const libre = estadoNormalizado(mesa) === "DISPONIBLE";
            const el = document.createElement("div");
            el.className = `mesa-card ${libre ? "libre" : "ocupada"}`;
            el.innerHTML = `
                <div class="mesa-icon">${libre ? "🪑" : "👥"}</div>
                <div class="mesa-number">Mesa ${Number(mesa.numero_mesa)}</div>
                <div class="mesa-status">${libre ? "Libre" : esc(mesa.estado)}</div>
                ${mesa.cliente_nombre ? `<div class="mesa-cliente">${esc(mesa.cliente_nombre)}</div>` : ""}
            `;
            el.addEventListener("click", () => abrirModalMesa(mesa));
            grid.appendChild(el);
        });
    }

    function abrirModalMesa(mesa) {
        mesaSeleccionada = mesa;
        const libre = estadoNormalizado(mesa) === "DISPONIBLE";

        document.getElementById("modalTitle").textContent = `Mesa ${Number(mesa.numero_mesa)}`;
        document.getElementById("modalEstado").textContent = libre ? "✓ Libre" : `✗ ${mesa.estado}`;
        document.getElementById("modalCapacidad").textContent = `${Number(mesa.capacidad)} personas`;
        document.getElementById("modalClientes").textContent = Number(mesa.ocupantes_actuales || 0) > 0 ? mesa.ocupantes_actuales : "-";
        const mins = minutosOcupada(mesa);
        document.getElementById("modalTiempo").textContent = !libre && mins > 0 ? `${mins} min` : "-";
        document.getElementById("modalPedidos").textContent = Number(mesa.pedidos_activos || 0) > 0 ? mesa.pedidos_activos : "-";

        const clienteAsignado = [mesa.cliente_nombre, mesa.cliente_correo].filter(Boolean).join(" · ");
        document.getElementById("clienteAsignadoGroup").hidden = !clienteAsignado;
        document.getElementById("modalClienteAsignado").textContent = clienteAsignado || "-";

        const btnAccion = document.getElementById("btnAccion");
        const inputGroup = document.getElementById("asignarInputGroup");
        const errorSpan = document.getElementById("errorClientes");
        errorSpan.style.display = "none";

        if (libre) {
            btnAccion.textContent = "✓ Asignar Clientes";
            btnAccion.style.backgroundColor = "#10b981";
            inputGroup.style.display = "flex";
            const input = document.getElementById("inputClientes");
            input.max = mesa.capacidad;
            input.value = 1;
            document.getElementById("inputNombreCliente").value = "";
            document.getElementById("inputCorreoCliente").value = "";
        } else {
            btnAccion.textContent = "✗ Vaciar Mesa";
            btnAccion.style.backgroundColor = "#ef4444";
            inputGroup.style.display = "none";
        }

        document.getElementById("modalMesa").classList.add("active");
        document.getElementById("modalOverlay").classList.add("active");
    }

    function cerrarModal() {
        document.getElementById("modalMesa")?.classList.remove("active");
        document.getElementById("modalOverlay")?.classList.remove("active");
        mesaSeleccionada = null;
    }

    async function accionMesa() {
        if (!mesaSeleccionada) return;
        const libre = estadoNormalizado(mesaSeleccionada) === "DISPONIBLE";
        if (libre) {
            await asignarMesa();
        } else {
            await vaciarMesa();
        }
    }

    async function asignarMesa() {
        const mesa = mesaSeleccionada;
        const numClientes = parseInt(document.getElementById("inputClientes").value, 10);
        const errorSpan = document.getElementById("errorClientes");
        if (isNaN(numClientes) || numClientes <= 0 || numClientes > Number(mesa.capacidad)) {
            errorSpan.textContent = `Ingresa un número entre 1 y ${mesa.capacidad}`;
            errorSpan.style.display = "block";
            return;
        }
        const clienteCorreo = document.getElementById("inputCorreoCliente").value.trim().toLowerCase();
        const clienteNombre = document.getElementById("inputNombreCliente").value.trim();
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(clienteCorreo)) {
            errorSpan.textContent = "Ingresa el correo válido del cliente para asociarlo en la base de datos.";
            errorSpan.style.display = "block";
            return;
        }

        try {
            await window.RequestyAPI.post("api/mesas", {
                numero_mesa: Number(mesa.numero_mesa),
                estado: "OCUPADA",
                ocupantes: numClientes,
                cliente_nombre: clienteNombre || clienteCorreo.split("@")[0],
                cliente_correo: clienteCorreo
            });
            mostrarToast(`✓ Mesa ${mesa.numero_mesa} asignada a ${numClientes} cliente(s)`);
            cerrarModal();
            await cargarMesas();
        } catch (err) {
            errorSpan.textContent = err.message || "No se pudo asignar la mesa.";
            errorSpan.style.display = "block";
        }
    }

    async function vaciarMesa() {
        const mesa = mesaSeleccionada;
        try {
            await window.RequestyAPI.post("api/mesas", {
                numero_mesa: Number(mesa.numero_mesa),
                estado: "DISPONIBLE"
            });
            mostrarToast(`✓ Mesa ${mesa.numero_mesa} liberada y disponible`);
            cerrarModal();
            await cargarMesas();
        } catch (err) {
            mostrarToast(err.message || "No se pudo liberar la mesa.", true);
        }
    }

    function actualizarStats() {
        const libres = mesas.filter((m) => estadoNormalizado(m) === "DISPONIBLE").length;
        const ocupadas = mesas.filter((m) => estadoNormalizado(m) === "OCUPADA").length;
        const total = mesas.length || 1;
        const pct = Math.round((ocupadas / total) * 100);
        document.getElementById("mesasDisponibles").textContent = libres;
        document.getElementById("mesasOcupadas").textContent = ocupadas;
        document.getElementById("tasaOcupacion").textContent = `${pct}%`;
    }

    function obtenerMesasFiltradas() {
        if (filtroActual === "libres") return mesas.filter((m) => estadoNormalizado(m) === "DISPONIBLE");
        if (filtroActual === "ocupadas") return mesas.filter((m) => estadoNormalizado(m) === "OCUPADA");
        return mesas;
    }

    function mostrarToast(mensaje, esError = false) {
        const toast = document.createElement("div");
        toast.textContent = mensaje;
        toast.style.cssText = `position:fixed;bottom:20px;right:20px;background:${esError ? "#ef4444" : "#10b981"};color:#fff;padding:14px 22px;border-radius:8px;box-shadow:0 5px 15px rgba(0,0,0,.2);z-index:1001;font-weight:bold;`;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    document.querySelectorAll(".filter-btn").forEach((btn) => {
        btn.addEventListener("click", () => {
            document.querySelectorAll(".filter-btn").forEach((b) => b.classList.remove("active"));
            btn.classList.add("active");
            filtroActual = btn.dataset.filter || "all";
            renderizarMesas(obtenerMesasFiltradas());
        });
    });

    document.getElementById("btnAccion")?.addEventListener("click", accionMesa);
    document.getElementById("btnCerrarModalTop")?.addEventListener("click", cerrarModal);
    document.getElementById("btnCerrarModalSec")?.addEventListener("click", cerrarModal);
    document.getElementById("modalOverlay")?.addEventListener("click", cerrarModal);

    if (window.RequestyAPI.leerSesion()) {
        cargarMesas();
    } else {
        window.addEventListener("requesty-sesion-lista", cargarMesas, { once: true });
    }
    window.setInterval(cargarMesas, 10000);
})();
