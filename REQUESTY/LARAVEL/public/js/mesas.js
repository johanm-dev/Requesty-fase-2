// Plano del restaurante y gestión de mesas conectado 100% a PostgreSQL (api/mesas, api/mesas/crear)
(function () {
    const COOKIE_COMBINACIONES = "rq_mesas_combinadas";
    const estadosValidos = new Set(["libre", "ocupada", "reservada", "inactiva"]);

    let mesas = [];
    let combinaciones = [];
    let filtroActual = "all";
    let modoCombinacion = false;
    let mesasSeleccionadas = new Set();
    let unidadSeleccionada = null;
    let alertaOcupacionActiva = null;
    let alertaOcupacionDescartada = null;

    const elemento = (id) => document.getElementById(id);

    function getCookie(name) {
        const match = document.cookie.match(new RegExp("(?:^|; )" + name + "=([^;]*)"));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function setCookie(name, value) {
        document.cookie = name + "=" + encodeURIComponent(value) + "; path=/; max-age=604800; SameSite=Strict";
    }

    function normalizarEstado(estado) {
        const valor = String(estado ?? "").trim().toLocaleLowerCase("es");
        if (["libre", "disponible"].includes(valor)) return "libre";
        if (["ocupada", "ocupado"].includes(valor)) return "ocupada";
        if (valor === "reservada") return "reservada";
        return "inactiva";
    }

    function normalizarMesaServidor(mesa, previas = []) {
        const numero = Number(mesa.numero_mesa ?? mesa.numero ?? mesa.id);
        const previa = previas.find((actual) => Number(actual.numero) === numero);
        const estado = normalizarEstado(mesa.estado);
        const tsOcupacion = mesa.hora_ocupacion ? Date.parse(mesa.hora_ocupacion) : (previa?.ocupadaDesde || 0);
        const ocupadaDesde = estado === "ocupada" && Number.isFinite(tsOcupacion) ? tsOcupacion : 0;
        const tiempoSeg = ocupadaDesde > 0 ? Math.max(0, Math.floor((Date.now() - ocupadaDesde) / 1000)) : 0;

        return {
            id: Number(mesa.id_mesa ?? mesa.id ?? numero),
            numero,
            capacidad: Math.max(1, Number(mesa.capacidad) || 4),
            estado,
            clientes: estado === "ocupada" ? Math.max(0, Number(mesa.ocupantes_actuales ?? previa?.clientes) || 0) : 0,
            pedidos: Math.max(0, Number(mesa.pedidos_activos ?? previa?.pedidos) || 0),
            tiempo: tiempoSeg,
            ocupadaDesde,
            clienteNombre: estado === "ocupada" ? String(mesa.cliente_nombre ?? previa?.clienteNombre ?? "") : "",
            clienteCorreo: estado === "ocupada" ? String(mesa.cliente_correo ?? previa?.clienteCorreo ?? "") : "",
            fechaReserva: estado === "reservada" ? normalizarFechaReserva(previa?.fechaReserva || "") : ""
        };
    }

    function leerCombinaciones() {
        try {
            const raw = getCookie(COOKIE_COMBINACIONES);
            if (!raw) return [];
            const guardadas = JSON.parse(raw);
            if (!Array.isArray(guardadas)) return [];
            return guardadas
                .filter((grupo) => Array.isArray(grupo))
                .map((grupo) => [...new Set(grupo.map(Number).filter(Number.isInteger))])
                .filter((grupo) => grupo.length > 1);
        } catch {
            return [];
        }
    }

    function guardarCombinaciones() {
        try {
            setCookie(COOKIE_COMBINACIONES, JSON.stringify(combinaciones));
            return true;
        } catch {
            return false;
        }
    }

    function reconciliarCombinaciones() {
        const numerosExistentes = new Set(mesas.map((mesa) => mesa.numero));
        combinaciones = combinaciones
            .map((grupo) => grupo.filter((numero) => numerosExistentes.has(numero)))
            .filter((grupo) => grupo.length > 1);
    }

    async function cargarMesasServidor() {
        try {
            const res = await window.RequestyAPI.get("api/mesas");
            const lista = Array.isArray(res?.mesas) ? res.mesas : [];
            mesas = lista.map((m) => normalizarMesaServidor(m, mesas));
            reconciliarCombinaciones();
            renderizarMesas();
            actualizarStats();
        } catch (err) {
            mostrarToast(err.message || "No se pudieron cargar las mesas del servidor.", "error");
        }
    }

    function iniciarMesas() {
        const iniciales = Array.isArray(window.requestyMesasIniciales) ? window.requestyMesasIniciales : [];
        mesas = iniciales.map((m) => normalizarMesaServidor(m, []));
        combinaciones = leerCombinaciones();
        reconciliarCombinaciones();
        agregarEventos();
        renderizarMesas();
        actualizarStats();
        cargarMesasServidor();
    }

    function agregarEventos() {
        document.querySelectorAll(".filter-btn").forEach((boton) => {
            boton.addEventListener("click", () => {
                filtroActual = boton.dataset.filter || "all";
                document.querySelectorAll(".filter-btn").forEach((actual) => {
                    const activo = actual === boton;
                    actual.classList.toggle("active", activo);
                    actual.setAttribute("aria-pressed", String(activo));
                });
                renderizarMesas();
            });
        });

        elemento("btnModoCombinar")?.addEventListener("click", activarModoCombinacion);
        elemento("btnConfirmarCombinacion")?.addEventListener("click", combinarMesasSeleccionadas);
        elemento("btnCancelarCombinacion")?.addEventListener("click", cancelarModoCombinacion);
        elemento("btnCerrarModalMesa")?.addEventListener("click", cerrarModalMesa);
        elemento("btnCancelarModalMesa")?.addEventListener("click", cerrarModalMesa);
        elemento("modalOverlay")?.addEventListener("click", cerrarModalMesa);
        elemento("btnAccion")?.addEventListener("click", accionMesa);
        elemento("selectEstadoMesa")?.addEventListener("change", actualizarCamposEstadoMesa);
        elemento("inputClientes")?.addEventListener("input", limpiarErrorAsignacion);
        elemento("inputCorreoCliente")?.addEventListener("input", limpiarErrorAsignacion);
        elemento("inputFechaReserva")?.addEventListener("input", limpiarErrorReserva);

        if (window.requestyPuedeCrearMesa && elemento("btnAgregarMesa")) {
            elemento("btnAgregarMesa").addEventListener("click", abrirModalNuevaMesa);
            elemento("btnCerrarNuevaMesa")?.addEventListener("click", cerrarModalNuevaMesa);
            elemento("btnCancelarNuevaMesa")?.addEventListener("click", cerrarModalNuevaMesa);
            elemento("modalNuevaMesaOverlay")?.addEventListener("click", cerrarModalNuevaMesa);
            elemento("formNuevaMesa")?.addEventListener("submit", crearMesa);
        }

        document.addEventListener("keydown", (evento) => {
            if (evento.key === "Escape") {
                cerrarModalMesa();
                cerrarModalNuevaMesa();
                if (modoCombinacion) cancelarModoCombinacion();
            }
        });
    }

    function obtenerUnidades() {
        const porNumero = new Map(mesas.map((mesa) => [mesa.numero, mesa]));
        const usadas = new Set();
        const unidades = [];

        combinaciones.forEach((grupo) => {
            const miembros = grupo.map((numero) => porNumero.get(numero)).filter(Boolean);
            if (miembros.length < 2) return;
            miembros.forEach((mesa) => usadas.add(mesa.numero));
            unidades.push(crearUnidad(miembros));
        });

        mesas.forEach((mesa) => {
            if (!usadas.has(mesa.numero)) unidades.push(crearUnidad([mesa]));
        });

        return unidades.sort((a, b) => a.miembros[0].numero - b.miembros[0].numero);
    }

    function crearUnidad(miembros) {
        const numeros = miembros.map((mesa) => mesa.numero).sort((a, b) => a - b);
        const estados = new Set(miembros.map((mesa) => mesa.estado));
        const estado = estados.size === 1 ? miembros[0].estado : "mixta";
        const etiqueta = numeros.length > 1 ? numeros.join(" + ") : String(numeros[0]);
        const inicioOcupacion = Math.min(...miembros.map((mesa) => mesa.ocupadaDesde || Infinity));
        const tiempoTranscurrido = Number.isFinite(inicioOcupacion)
            ? Math.floor((Date.now() - inicioOcupacion) / 1000)
            : 0;
        const fechaReserva = miembros.map((mesa) => mesa.fechaReserva).find(Boolean) || "";
        return {
            key: numeros.join("-"),
            etiqueta,
            nombre: numeros.length > 1 ? `Mesas ${etiqueta}` : `Mesa ${etiqueta}`,
            miembros,
            capacidad: miembros.reduce((total, mesa) => total + mesa.capacidad, 0),
            estado,
            clientes: miembros.reduce((total, mesa) => total + mesa.clientes, 0),
            pedidos: miembros.reduce((total, mesa) => total + mesa.pedidos, 0),
            tiempo: Math.max(tiempoTranscurrido, ...miembros.map((mesa) => mesa.tiempo)),
            ocupadaDesde: inicioOcupacion,
            fechaReserva,
            clienteNombre: [...new Set(miembros.map((mesa) => mesa.clienteNombre).filter(Boolean))].join(", "),
            clienteCorreo: [...new Set(miembros.map((mesa) => mesa.clienteCorreo).filter(Boolean))].join(", ")
        };
    }

    function obtenerUnidadesFiltradas() {
        const unidades = obtenerUnidades();
        if (filtroActual === "libres") return unidades.filter((u) => u.estado === "libre");
        if (filtroActual === "ocupadas") return unidades.filter((u) => u.estado === "ocupada");
        return unidades;
    }

    function renderizarMesas() {
        const grid = elemento("mesasGrid");
        if (!grid) return;
        grid.replaceChildren();
        grid.classList.toggle("modo-combinacion", modoCombinacion);

        obtenerUnidadesFiltradas().forEach((unidad) => {
            const tarjeta = document.createElement("article");
            tarjeta.className = `mesa-card ${estadosValidos.has(unidad.estado) ? unidad.estado : "inactiva"}`;
            if (unidad.estado === "mixta") tarjeta.classList.add("mesa-mixta");
            if (mesasSeleccionadas.has(unidad.key)) tarjeta.classList.add("seleccionada");

            const botonMesa = document.createElement("button");
            botonMesa.type = "button";
            botonMesa.className = "mesa-card-main";
            botonMesa.setAttribute("aria-pressed", String(mesasSeleccionadas.has(unidad.key)));
            botonMesa.setAttribute("aria-label", `${unidad.nombre}, ${unidad.capacidad} puestos, ${etiquetaEstado(unidad.estado)}`);
            botonMesa.addEventListener("click", () => {
                if (modoCombinacion) {
                    alternarSeleccion(unidad);
                } else {
                    abrirModalMesa(unidad);
                }
            });

            const escenario = crearEscenarioMesa(unidad);
            const nombre = document.createElement("strong");
            nombre.className = "mesa-number";
            nombre.textContent = unidad.nombre;
            const capacidad = document.createElement("span");
            capacidad.className = "mesa-capacity";
            capacidad.textContent = `${unidad.capacidad} ${unidad.capacidad === 1 ? "puesto" : "puestos"}`;
            const estado = document.createElement("span");
            estado.className = "mesa-status";
            estado.textContent = etiquetaEstado(unidad.estado);

            botonMesa.append(escenario, nombre, capacidad, estado);
            if (unidad.estado === "reservada") {
                const fechaReserva = document.createElement("span");
                fechaReserva.className = "mesa-reservation-date";
                fechaReserva.textContent = unidad.fechaReserva
                    ? `Reserva: ${formatearFechaReserva(unidad.fechaReserva)}`
                    : "Fecha pendiente";
                botonMesa.appendChild(fechaReserva);
            }
            tarjeta.appendChild(botonMesa);

            if (unidad.miembros.length > 1) {
                const separar = document.createElement("button");
                separar.type = "button";
                separar.className = "mesa-separar";
                separar.textContent = "Separar";
                separar.setAttribute("aria-label", "Separar mesas combinadas");
                separar.addEventListener("click", () => separarUnidad(unidad));
                tarjeta.appendChild(separar);
            }
            grid.appendChild(tarjeta);
        });

        actualizarControlesCombinacion();
    }

    function crearEscenarioMesa(unidad) {
        const escenario = document.createElement("div");
        const capacidadClase = Math.min(12, Math.max(1, unidad.capacidad));
        const forma = unidad.capacidad <= 4 ? "circular" : "rectangular";
        escenario.className = `mesa-table-stage capacidad-${capacidadClase} forma-${forma}`;
        escenario.setAttribute("aria-hidden", "true");

        obtenerPosicionesSillas(unidad.capacidad).forEach((posicion) => {
            const silla = document.createElement("i");
            silla.className = "fa-solid fa-chair mesa-seat";
            silla.style.setProperty("--seat-x", `${posicion.x}%`);
            silla.style.setProperty("--seat-y", `${posicion.y}%`);
            escenario.appendChild(silla);
        });

        const superficie = document.createElement("div");
        superficie.className = "mesa-table-surface";
        superficie.textContent = unidad.etiqueta;
        escenario.appendChild(superficie);
        return escenario;
    }

    function obtenerPosicionesSillas(capacidad) {
        const asientos = [];
        if (capacidad <= 4) {
            for (let indice = 0; indice < capacidad; indice++) {
                const angulo = (-Math.PI / 2) + (2 * Math.PI * indice / capacidad);
                asientos.push({
                    x: 50 + Math.cos(angulo) * 34,
                    y: 50 + Math.sin(angulo) * 38
                });
            }
            return asientos;
        }

        const arriba = Math.ceil(capacidad / 2);
        const abajo = Math.floor(capacidad / 2);
        for (let indice = 0; indice < arriba; indice++) {
            asientos.push({ x: ((indice + 1) / (arriba + 1)) * 100, y: 12 });
        }
        for (let indice = 0; indice < abajo; indice++) {
            asientos.push({ x: ((indice + 1) / (abajo + 1)) * 100, y: 88 });
        }
        return asientos;
    }

    function etiquetaEstado(estado) {
        if (estado === "libre") return "Disponible";
        if (estado === "ocupada") return "Ocupada";
        if (estado === "reservada") return "Reservada";
        if (estado === "mixta") return "Estado mixto";
        return "Inactiva";
    }

    function actualizarStats() {
        const activas = mesas.filter((mesa) => mesa.estado !== "inactiva");
        const disponibles = activas.filter((mesa) => mesa.estado === "libre").length;
        const ocupadas = activas.filter((mesa) => mesa.estado === "ocupada").length;
        const porcentaje = activas.length ? Math.round((ocupadas / activas.length) * 100) : 0;

        if (elemento("mesasDisponibles")) elemento("mesasDisponibles").textContent = String(disponibles);
        if (elemento("mesasOcupadas")) elemento("mesasOcupadas").textContent = String(ocupadas);
        if (elemento("tasaOcupacion")) elemento("tasaOcupacion").textContent = `${porcentaje}%`;
        evaluarAlertaOcupacion(ocupadas, activas.length, porcentaje);
    }

    function evaluarAlertaOcupacion(ocupadas, total, porcentaje) {
        if (!total || porcentaje <= 80) {
            if (alertaOcupacionActiva) alertaOcupacionActiva.remove();
            alertaOcupacionActiva = null;
            alertaOcupacionDescartada = null;
            return;
        }
        if (alertaOcupacionDescartada === ocupadas && !alertaOcupacionActiva) return;

        const lleno = porcentaje === 100;
        const mensaje = lleno ? "El restaurante está lleno." : "La ocupación del restaurante superó el 80%.";
        if (alertaOcupacionActiva?.isConnected) {
            alertaOcupacionActiva.querySelector(".toast-message").textContent = mensaje;
            alertaOcupacionActiva.classList.toggle("toast-lleno", lleno);
            alertaOcupacionActiva.classList.toggle("toast-alerta", !lleno);
            return;
        }
        alertaOcupacionActiva = mostrarToast(mensaje, lleno ? "lleno" : "alerta", { alerta: true, persistente: true });
    }

    function mostrarToast(mensaje, tipo = "success", opciones = {}) {
        const lista = elemento("toastList");
        if (!lista) return null;

        const toast = document.createElement("li");
        toast.className = `toast toast-${tipo}`;
        if (opciones.alerta) toast.dataset.alerta = "ocupacion";

        const texto = document.createElement("span");
        texto.className = "toast-message";
        texto.textContent = mensaje;
        const cerrar = document.createElement("button");
        cerrar.type = "button";
        cerrar.className = "toast-close";
        cerrar.setAttribute("aria-label", "Cerrar notificación");
        cerrar.textContent = "×";
        cerrar.addEventListener("click", () => {
            if (toast === alertaOcupacionActiva) {
                alertaOcupacionDescartada = mesas.filter((mesa) => mesa.estado === "ocupada").length;
                alertaOcupacionActiva = null;
            }
            toast.remove();
        });

        toast.append(texto, cerrar);
        lista.appendChild(toast);
        if (!opciones.persistente) {
            window.setTimeout(() => toast.remove(), 6000);
        }
        return toast;
    }

    function activarModoCombinacion() {
        if (modoCombinacion) return;
        modoCombinacion = true;
        mesasSeleccionadas.clear();
        elemento("btnModoCombinar")?.setAttribute("aria-pressed", "true");
        if (elemento("btnModoCombinar")) elemento("btnModoCombinar").hidden = true;
        if (elemento("btnCancelarCombinacion")) elemento("btnCancelarCombinacion").hidden = false;
        if (elemento("mesasSelectionHint")) elemento("mesasSelectionHint").hidden = false;
        renderizarMesas();
    }

    function alternarSeleccion(unidad) {
        if (unidad.estado !== "libre") {
            mostrarToast("Solo se pueden combinar mesas disponibles.", "error");
            return;
        }
        if (mesasSeleccionadas.has(unidad.key)) {
            mesasSeleccionadas.delete(unidad.key);
        } else {
            mesasSeleccionadas.add(unidad.key);
        }
        renderizarMesas();
    }

    function actualizarControlesCombinacion() {
        const boton = elemento("btnConfirmarCombinacion");
        if (!boton) return;
        const cantidad = mesasSeleccionadas.size;
        boton.hidden = !modoCombinacion;
        boton.disabled = cantidad < 2;
        if (elemento("mesasSelectionHint")) {
            elemento("mesasSelectionHint").textContent = modoCombinacion
                ? `Selecciona dos o más mesas disponibles. ${cantidad} seleccionada(s).`
                : "";
        }
    }

    function combinarMesasSeleccionadas() {
        const unidades = obtenerUnidades().filter((u) => mesasSeleccionadas.has(u.key));
        if (unidades.length < 2 || unidades.some((u) => u.estado !== "libre")) {
            mostrarToast("Selecciona al menos dos mesas disponibles.", "error");
            return;
        }
        const numeros = [...new Set(unidades.flatMap((u) => u.miembros.map((m) => m.numero)))].sort((a, b) => a - b);
        const conjunto = new Set(numeros);
        combinaciones = combinaciones.filter((g) => !g.some((n) => conjunto.has(n)));
        combinaciones.push(numeros);
        guardarCombinaciones();

        cancelarModoCombinacion();
        renderizarMesas();
        mostrarToast(`Mesas ${numeros.join(" + ")} combinadas (${unidades.reduce((t, u) => t + u.capacidad, 0)} puestos).`);
    }

    function cancelarModoCombinacion() {
        modoCombinacion = false;
        mesasSeleccionadas.clear();
        if (elemento("btnModoCombinar")) {
            elemento("btnModoCombinar").hidden = false;
            elemento("btnModoCombinar").setAttribute("aria-pressed", "false");
        }
        if (elemento("btnCancelarCombinacion")) elemento("btnCancelarCombinacion").hidden = true;
        if (elemento("mesasSelectionHint")) elemento("mesasSelectionHint").hidden = true;
        if (elemento("btnConfirmarCombinacion")) elemento("btnConfirmarCombinacion").hidden = true;
        renderizarMesas();
    }

    function separarUnidad(unidad) {
        const numeros = new Set(unidad.miembros.map((m) => m.numero));
        combinaciones = combinaciones.filter((g) => !g.every((n) => numeros.has(n)));
        guardarCombinaciones();
        renderizarMesas();
        mostrarToast("Las mesas volvieron a estar separadas.");
    }

    function abrirModalMesa(unidad) {
        unidadSeleccionada = unidad;
        const cliente = [...new Set(unidad.miembros.map((m) => m.clienteNombre).filter(Boolean))].join(", ");
        const correo = [...new Set(unidad.miembros.map((m) => m.clienteCorreo).filter(Boolean))].join(", ");
        const datosCliente = [cliente, correo].filter(Boolean).join(" · ");

        elemento("modalTitle").textContent = unidad.nombre;
        elemento("modalCapacidad").textContent = `${unidad.capacidad} puestos`;
        elemento("modalClientes").textContent = unidad.clientes ? String(unidad.clientes) : "-";
        elemento("modalTiempo").textContent = unidad.tiempo ? formatearTiempo(unidad.tiempo) : "-";
        elemento("modalPedidos").textContent = String(unidad.pedidos);
        elemento("modalClienteAsignado").textContent = datosCliente || "-";
        elemento("clienteAsignadoGroup").hidden = !datosCliente;
        elemento("fechaReservaActualGroup").hidden = unidad.estado !== "reservada";
        elemento("fechaReservaActual").textContent = unidad.fechaReserva
            ? formatearFechaReserva(unidad.fechaReserva)
            : "Fecha pendiente";
        elemento("asignarInputGroup").hidden = true;
        elemento("reservaInputGroup").hidden = true;
        elemento("errorClientes").hidden = true;
        elemento("errorReserva").hidden = true;
        elemento("errorEstadoMesa").hidden = true;
        elemento("btnAccion").hidden = false;
        elemento("btnAccion").disabled = false;
        elemento("modalMesa").classList.toggle("mesa-modal-ocupada", unidad.estado === "ocupada");
        elemento("modalMesa").classList.toggle("mesa-modal-libre", unidad.estado === "libre");

        const selectEstado = elemento("selectEstadoMesa");
        selectEstado.value = estadosValidos.has(unidad.estado) ? unidad.estado : "";
        const inputClientes = elemento("inputClientes");
        inputClientes.max = String(unidad.capacidad);
        inputClientes.value = String(Math.max(1, unidad.clientes || 1));
        elemento("inputNombreCliente").value = unidad.clienteNombre || "";
        elemento("inputCorreoCliente").value = unidad.clienteCorreo || "";
        actualizarMinimoFechaReserva();
        elemento("inputFechaReserva").value = unidad.fechaReserva ? fechaReservaParaInput(unidad.fechaReserva) : "";
        actualizarCamposEstadoMesa();
        abrirDialogo("modalMesa", "modalOverlay");
    }

    function abrirDialogo(modalId, overlayId) {
        const modal = elemento(modalId);
        const overlay = elemento(overlayId);
        if (modal) modal.hidden = false;
        if (overlay) overlay.hidden = false;
        document.body.classList.add("modal-abierto");
    }

    function cerrarModalMesa() {
        if (!elemento("modalMesa") || elemento("modalMesa").hidden) return;
        elemento("modalMesa").hidden = true;
        elemento("modalOverlay").hidden = true;
        unidadSeleccionada = null;
        actualizarEstadoScroll();
    }

    function limpiarErrorAsignacion() {
        elemento("errorClientes").hidden = true;
        elemento("errorClientes").textContent = "";
    }

    function limpiarErrorReserva() {
        elemento("errorReserva").hidden = true;
        elemento("errorReserva").textContent = "";
    }

    function actualizarCamposEstadoMesa() {
        if (!unidadSeleccionada) return;
        const estado = elemento("selectEstadoMesa").value;
        elemento("errorClientes").hidden = true;
        elemento("errorReserva").hidden = true;
        elemento("errorEstadoMesa").hidden = true;
        elemento("asignarInputGroup").hidden = estado !== "ocupada";
        const tieneClienteAsignado = Boolean(unidadSeleccionada.clienteNombre || unidadSeleccionada.clienteCorreo);
        elemento("clienteAsignadoGroup").hidden = !tieneClienteAsignado || estado !== "ocupada";
        elemento("reservaInputGroup").hidden = estado !== "reservada";
        elemento("fechaReservaActualGroup").hidden = unidadSeleccionada.estado !== "reservada";

        const boton = elemento("btnAccion");
        boton.hidden = !estado;
        if (estado === "ocupada") {
            boton.textContent = unidadSeleccionada.estado === "ocupada" ? "Guardar cambios" : "Asignar mesa";
        } else if (estado === "reservada") {
            boton.textContent = unidadSeleccionada.estado === "reservada" ? "Guardar reserva" : "Reservar mesa";
        } else if (estado === "libre") {
            boton.textContent = unidadSeleccionada.estado === "libre" ? "Guardar estado" : "Dejar disponible";
        } else if (estado === "inactiva") {
            boton.textContent = "Desactivar mesa";
        }
    }

    async function accionMesa() {
        if (!unidadSeleccionada) return;
        const estado = elemento("selectEstadoMesa").value;
        const error = estado === "ocupada"
            ? elemento("errorClientes")
            : estado === "reservada"
                ? elemento("errorReserva")
                : elemento("errorEstadoMesa");
        error.hidden = true;
        error.textContent = "";

        if (estado === "ocupada") {
            const cantidad = Number(elemento("inputClientes").value);
            const correo = elemento("inputCorreoCliente").value.trim().toLocaleLowerCase("es");
            if (!Number.isInteger(cantidad) || cantidad < 1 || cantidad > unidadSeleccionada.capacidad) {
                error.textContent = `Ingresa una cantidad entre 1 y ${unidadSeleccionada.capacidad}.`;
                error.hidden = false;
                return;
            }
            if (correo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
                error.textContent = "El correo ingresado no tiene un formato válido.";
                error.hidden = false;
                return;
            }
            await guardarEstadoMesa(unidadSeleccionada, estado, {
                clientes: cantidad,
                nombre: elemento("inputNombreCliente").value.trim(),
                correo
            });
            return;
        }
        let fechaReserva = "";
        if (estado === "reservada") {
            const entradaFecha = elemento("inputFechaReserva").value;
            const fechaMinima = actualizarMinimoFechaReserva();
            const fechaSeleccionada = new Date(entradaFecha);
            fechaReserva = normalizarFechaReserva(entradaFecha);
            if (!fechaReserva || fechaSeleccionada.getTime() < fechaMinima.getTime()) {
                error.textContent = "Selecciona una fecha y hora actuales o futuras para la reserva.";
                error.hidden = false;
                return;
            }
        } else if (!["libre", "inactiva"].includes(estado)) {
            elemento("errorEstadoMesa").textContent = "Selecciona un estado válido.";
            elemento("errorEstadoMesa").hidden = false;
            return;
        }
        await guardarEstadoMesa(unidadSeleccionada, estado, { fechaReserva });
    }

    async function guardarEstadoMesa(unidad, estado, datos = {}) {
        const estadosApi = {
            libre: "DISPONIBLE",
            ocupada: "OCUPADA",
            reservada: "RESERVADA",
            inactiva: "INACTIVA"
        };
        const boton = elemento("btnAccion");
        boton.disabled = true;
        try {
            const numeros = unidad.miembros.map((m) => m.numero);
            if (numeros.length === 1) {
                const payload = {
                    numero_mesa: numeros[0],
                    estado: estadosApi[estado]
                };
                if (estado === "ocupada") {
                    payload.ocupantes = datos.clientes || 1;
                    if (datos.nombre) payload.cliente_nombre = datos.nombre;
                    if (datos.correo) payload.cliente_correo = datos.correo;
                }
                await window.RequestyAPI.post("api/mesas", payload);
            } else {
                await window.RequestyAPI.post("api/mesas", {
                    numeros_mesa: numeros,
                    estado: estadosApi[estado]
                });
            }

            let clientesRestantes = datos.clientes || 0;
            const ahora = Date.now();
            unidad.miembros.forEach((mesa) => {
                mesa.estado = estado;
                if (estado === "ocupada") {
                    mesa.clientes = Math.min(mesa.capacidad, clientesRestantes);
                    clientesRestantes -= mesa.clientes;
                    mesa.ocupadaDesde = mesa.ocupadaDesde || ahora;
                    mesa.clienteNombre = datos.nombre || "";
                    mesa.clienteCorreo = datos.correo || "";
                    mesa.fechaReserva = "";
                    return;
                }
                mesa.clientes = 0;
                mesa.pedidos = 0;
                mesa.tiempo = 0;
                mesa.ocupadaDesde = 0;
                mesa.clienteNombre = "";
                mesa.clienteCorreo = "";
                mesa.fechaReserva = estado === "reservada" ? datos.fechaReserva : "";
            });

            cerrarModalMesa();
            renderizarMesas();
            actualizarStats();
            if (estado === "ocupada") {
                mostrarToast(`${unidad.nombre} asignada a ${datos.clientes} cliente(s).`);
            } else if (estado === "reservada") {
                mostrarToast(`${unidad.nombre} reservada para ${formatearFechaReserva(datos.fechaReserva)}.`);
            } else if (estado === "libre") {
                mostrarToast(`${unidad.nombre} quedó disponible.`);
            } else {
                mostrarToast(`${unidad.nombre} quedó inactiva.`);
            }
        } catch (errorServidor) {
            const error = estado === "ocupada"
                ? elemento("errorClientes")
                : estado === "reservada"
                    ? elemento("errorReserva")
                    : elemento("errorEstadoMesa");
            error.textContent = errorServidor.message || "No se pudo actualizar el estado de la mesa.";
            error.hidden = false;
            mostrarToast(error.textContent, "error");
        } finally {
            boton.disabled = false;
        }
    }

    function normalizarFechaReserva(valor) {
        if (typeof valor !== "string" || !valor.trim()) return "";
        const fecha = new Date(valor);
        return Number.isNaN(fecha.getTime()) ? "" : fecha.toISOString();
    }

    function fechaReservaParaInput(valor) {
        const fecha = new Date(valor);
        if (Number.isNaN(fecha.getTime())) return "";
        return fechaHoraLocalParaInput(fecha);
    }

    function actualizarMinimoFechaReserva() {
        const primerMinutoValido = new Date(Math.ceil(Date.now() / 60000) * 60000);
        if (elemento("inputFechaReserva")) {
            elemento("inputFechaReserva").min = fechaHoraLocalParaInput(primerMinutoValido);
        }
        return primerMinutoValido;
    }

    function fechaHoraLocalParaInput(fecha) {
        const pad = (valor) => String(valor).padStart(2, "0");
        return `${fecha.getFullYear()}-${pad(fecha.getMonth() + 1)}-${pad(fecha.getDate())}T${pad(fecha.getHours())}:${pad(fecha.getMinutes())}`;
    }

    function formatearFechaReserva(valor) {
        const fecha = new Date(valor);
        if (Number.isNaN(fecha.getTime())) return "Fecha pendiente";
        const hora24 = fecha.getHours();
        const hora12 = hora24 % 12 || 12;
        const hora = String(hora12).padStart(2, "0");
        const minutos = String(fecha.getMinutes()).padStart(2, "0");
        const periodo = hora24 < 12 ? "AM" : "PM";
        const dia = String(fecha.getDate()).padStart(2, "0");
        const mes = String(fecha.getMonth() + 1).padStart(2, "0");
        return `${hora}:${minutos} ${periodo} ${dia}/${mes}`;
    }

    function abrirModalNuevaMesa() {
        if (!elemento("modalNuevaMesa")) return;
        elemento("errorNuevaMesa").hidden = true;
        elemento("errorNuevaMesa").textContent = "";
        elemento("capacidadNuevaMesa").value = "4";
        abrirDialogo("modalNuevaMesa", "modalNuevaMesaOverlay");
        elemento("capacidadNuevaMesa").focus();
    }

    function cerrarModalNuevaMesa() {
        if (!window.requestyPuedeCrearMesa || !elemento("modalNuevaMesa") || elemento("modalNuevaMesa").hidden) return;
        elemento("modalNuevaMesa").hidden = true;
        elemento("modalNuevaMesaOverlay").hidden = true;
        actualizarEstadoScroll();
    }

    function actualizarEstadoScroll() {
        const hayModalAbierto = !elemento("modalMesa")?.hidden
            || (window.requestyPuedeCrearMesa && !elemento("modalNuevaMesa")?.hidden);
        if (!hayModalAbierto) document.body.classList.remove("modal-abierto");
    }

    async function crearMesa(evento) {
        evento.preventDefault();
        const input = elemento("capacidadNuevaMesa");
        const capacidad = Number(input.value);
        const error = elemento("errorNuevaMesa");
        if (!Number.isInteger(capacidad) || capacidad < 1 || capacidad > 12) {
            error.textContent = "La capacidad debe estar entre 1 y 12 puestos.";
            error.hidden = false;
            return;
        }

        const boton = elemento("btnGuardarNuevaMesa");
        boton.disabled = true;
        error.hidden = true;
        try {
            const resultado = await window.RequestyAPI.post("api/mesas/crear", { capacidad });
            if (!resultado?.mesa) {
                throw new Error("El servidor no devolvió la mesa creada.");
            }
            const nuevaMesa = normalizarMesaServidor(resultado.mesa, []);
            mesas.push(nuevaMesa);
            mesas.sort((a, b) => a.numero - b.numero);
            cerrarModalNuevaMesa();
            renderizarMesas();
            actualizarStats();
            mostrarToast(`Mesa ${nuevaMesa.numero} agregada.`);
        } catch (errorServidor) {
            error.textContent = errorServidor.message || "No se pudo guardar la nueva mesa.";
            error.hidden = false;
            mostrarToast(error.textContent, "error");
        } finally {
            boton.disabled = false;
        }
    }

    function formatearTiempo(segundos) {
        const minutos = Math.floor(segundos / 60);
        const restantes = segundos % 60;
        return `${minutos}:${String(restantes).padStart(2, "0")}`;
    }

    document.addEventListener("DOMContentLoaded", iniciarMesas);
})();
