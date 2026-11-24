/**
 * Requesty KDS - Lógica de Cocina en Cuerda con Libretas Colgantes
 * Soporta animación de arrugado y descarte de comandas tipo papel
 */

const clavePedidos = "requestyPedidos";
const eventoPedidos = "requesty-pedidos-actualizados";
const minutosAlerta = 10;
const minutosCriticos = 20;

const nombresEstados = {
    cola: "En cola",
    preparacion: "En preparación",
    listo: "Listo para servir",
    entregado: "Entregado",
    cancelado: "Cancelado"
};

// Pedidos demo realistas con especificaciones inspirados en el KDS de referencia
const pedidosDemoKds = [
    {
        id: "1885",
        mesa: 24,
        destination: "Mesa 24",
        channel: "Mesa",
        prioridad: "alta",
        priority: "alta",
        time: "12:35 PM",
        receivedAt: new Date(Date.now() - 18 * 60000).toISOString(),
        status: "En preparación",
        items: [
            { id: 1, nombre: "Tabla de Quesos", cantidad: 1, especificaciones: ["Para compartir"] },
            { id: 2, nombre: "Ensalada Queso de Cabra", cantidad: 1, especificaciones: ["Vinagreta aparte"] },
            { id: 3, nombre: "Burger Monster", cantidad: 2, especificaciones: ["Término: 3/4", "Sin cebolla"] },
            { id: 4, nombre: "Burger Maya", cantidad: 1, especificaciones: ["Extra jalapeños"] }
        ]
    },
    {
        id: "1886",
        mesa: 33,
        destination: "Mesa 33",
        channel: "Mesa",
        prioridad: "alta",
        priority: "alta",
        time: "12:36 PM",
        receivedAt: new Date(Date.now() - 17 * 60000).toISOString(),
        status: "En preparación",
        items: [
            { id: 5, nombre: "Foie Mi Cuit", cantidad: 1, especificaciones: ["Con tostadas extra"] },
            { id: 6, nombre: "Ensalada de Foie", cantidad: 2, especificaciones: [] },
            { id: 7, nombre: "Burger Occitana", cantidad: 1, especificaciones: ["Término: Medio"] },
            { id: 8, nombre: "Carrot Cake", cantidad: 2, especificaciones: ["Servir frío"] },
            { id: 9, nombre: "Brownie Artesanal", cantidad: 1, especificaciones: ["Con nueces"] }
        ]
    },
    {
        id: "156",
        mesa: 12,
        destination: "Mesa 12",
        channel: "Mesa",
        time: "12:38 PM",
        receivedAt: new Date(Date.now() - 16 * 60000).toISOString(),
        status: "En preparación",
        items: [
            { id: 10, nombre: "Hummus con Pan Pita", cantidad: 1, especificaciones: ["Sin comino"] },
            { id: 11, nombre: "Foie Mi Cuit", cantidad: 1, especificaciones: [] },
            { id: 12, nombre: "Burger Vegetariana", cantidad: 2, especificaciones: ["Pan sin gluten", "Sin mayonesa"] }
        ]
    },
    {
        id: "157",
        mesa: 7,
        destination: "Mesa 7",
        channel: "Mesa",
        time: "12:51 PM",
        receivedAt: new Date(Date.now() - 2 * 60000).toISOString(),
        status: "En cola",
        items: [
            { id: 13, nombre: "Ensalada de Quinoa", cantidad: 1, especificaciones: ["Cítrica"] },
            { id: 14, nombre: "Chipirones Salteados", cantidad: 1, especificaciones: ["Poco picante"] },
            { id: 15, nombre: "Filete a la Brasa", cantidad: 1, especificaciones: ["Al Punto"] },
            { id: 16, nombre: "Entrecot a la Brasa", cantidad: 1, especificaciones: ["Poco Hecho"] },
            { id: 17, nombre: "Corvina a la Brasa", cantidad: 1, especificaciones: ["Con limón asado"] }
        ]
    },
    {
        id: "158",
        mesa: 14,
        destination: "Mesa 14",
        channel: "Mesa",
        time: "12:52 PM",
        receivedAt: new Date(Date.now() - 1 * 60000).toISOString(),
        status: "En cola",
        items: [
            { id: 18, nombre: "Timbal de Mariscos", cantidad: 1, especificaciones: [] },
            { id: 19, nombre: "Tabla de Quesos", cantidad: 1, especificaciones: [] },
            { id: 20, nombre: "Burger Vegetariana", cantidad: 1, especificaciones: ["Extra queso vegano"] },
            { id: 21, nombre: "Tiramisú Casero", cantidad: 1, especificaciones: [] }
        ]
    }
];

// Síntesis de sonido de papel arrugándose con Web Audio API (cero dependencias externas)
let audioCtx = null;
function reproducirSonidoArrugar() {
    try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) return;
        if (!audioCtx) audioCtx = new AudioContextClass();
        if (audioCtx.state === "suspended") audioCtx.resume();

        const bufferSize = audioCtx.sampleRate * 0.45;
        const buffer = audioCtx.createBuffer(1, bufferSize, audioCtx.sampleRate);
        const data = buffer.getChannelData(0);

        // Generar ruido blanco con textura granulada tipo papel
        for (let i = 0; i < bufferSize; i++) {
            data[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / bufferSize, 0.7);
        }

        const noise = audioCtx.createBufferSource();
        noise.buffer = buffer;

        const filter = audioCtx.createBiquadFilter();
        filter.type = "bandpass";
        filter.frequency.setValueAtTime(1400, audioCtx.currentTime);
        filter.frequency.exponentialRampToValueAtTime(320, audioCtx.currentTime + 0.4);
        filter.Q.value = 3.5;

        const gainNode = audioCtx.createGain();
        gainNode.gain.setValueAtTime(0.35, audioCtx.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.45);

        noise.connect(filter);
        filter.connect(gainNode);
        gainNode.connect(audioCtx.destination);

        noise.start();
    } catch (e) {
        // En caso de que el navegador bloquee audio autoplay
    }
}

function normalizarEstado(estado) {
    const normalizado = String(estado || "").trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLocaleLowerCase("es");
    if (["nuevo", "pendiente", "en cola", "cola"].includes(normalizado)) return nombresEstados.cola;
    if (["preparando", "en preparacion", "preparacion"].includes(normalizado)) return nombresEstados.preparacion;
    if (["listo", "listo para servir"].includes(normalizado)) return nombresEstados.listo;
    if (normalizado === "entregado") return nombresEstados.entregado;
    if (normalizado === "cancelado") return nombresEstados.cancelado;
    return nombresEstados.cola;
}

function escaparHTML(valor) {
    return String(valor ?? "").replace(/[&<>"']/g, caracter => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    })[caracter]);
}

function obtenerFechaRecepcion(pedido) {
    const guardada = Date.parse(pedido.receivedAt || pedido.createdAt || "");
    if (Number.isFinite(guardada)) return guardada;
    const coincidencia = String(pedido.time || "").match(/^(\d{1,2}):(\d{2})/);
    if (!coincidencia) return Date.now();
    const fecha = new Date();
    fecha.setHours(Number(coincidencia[1]), Number(coincidencia[2]), 0, 0);
    if (fecha.getTime() > Date.now()) fecha.setDate(fecha.getDate() - 1);
    return fecha.getTime();
}

function leerPedidos() {
    try {
        let guardados = JSON.parse(localStorage.getItem(clavePedidos) || "null");
        if (!guardados || !Array.isArray(guardados) || guardados.length === 0) {
            guardados = pedidosDemoKds;
            localStorage.setItem(clavePedidos, JSON.stringify(guardados));
        }
        mostrarError("");
        return guardados.map(pedido => ({
            ...pedido,
            status: normalizarEstado(pedido.status || pedido.estado),
            items: Array.isArray(pedido.items) ? pedido.items : [],
            receivedAt: pedido.receivedAt || pedido.createdAt || new Date(obtenerFechaRecepcion(pedido)).toISOString()
        }));
    } catch (error) {
        mostrarError("Cargando comandas en modo seguro.");
        return pedidosDemoKds;
    }
}

function mostrarError(mensaje) {
    const contenedor = document.getElementById("mensaje-cocina");
    if (contenedor) contenedor.textContent = mensaje;
}

function minutosEnEspera(pedido, ahora = Date.now()) {
    return Math.max(0, Math.floor((ahora - obtenerFechaRecepcion(pedido)) / 60000));
}

function formatoCronometro(minutos) {
    const horas = Math.floor(minutos / 60);
    const mins = minutos % 60;
    return `${String(horas).padStart(2, "0")}:${String(mins).padStart(2, "0")}:00`;
}

function claseAlerta(pedido, espera) {
    if (pedido.status === nombresEstados.listo) return "alerta-listo";
    if (espera >= minutosCriticos) return "alerta-critica";
    if (espera >= minutosAlerta) return "alerta-advertencia";
    return "alerta-normal";
}

function extraerNumeroMesa(pedido) {
    if (pedido.mesa !== undefined && pedido.mesa !== null && String(pedido.mesa).trim() !== "") {
        return String(pedido.mesa).replace(/[^0-9a-zA-Z]/g, "") || "1";
    }
    const match = String(pedido.destination || "").match(/Mesa\s*([0-9a-zA-Z]+)/i);
    if (match) return match[1];
    return "1";
}

function obtenerEspecificaciones(item) {
    const especificaciones = [];
    if (Array.isArray(item.especificaciones)) {
        item.especificaciones.forEach(esp => especificaciones.push(String(esp)));
    }
    if (item.terminoCoccion) especificaciones.push(`Término: ${item.terminoCoccion}`);
    if (item.alternativaReceta) especificaciones.push(`Opción: ${item.alternativaReceta}`);
    if (item.ingredientesExcluidos?.length) especificaciones.push(`Sin ${item.ingredientesExcluidos.join(", ")}`);
    (item.extras || []).forEach(extra => {
        especificaciones.push(`+ ${extra.nombre || extra}`);
    });
    return especificaciones;
}

function renderizarItem(item) {
    const especificaciones = obtenerEspecificaciones(item);
    const cantidad = Math.max(1, Number(item.cantidad) || 1);
    const nombre = escaparHTML(item.nombre || item.producto || "Plato");

    const specsHTML = especificaciones.length
        ? `<ul class="lista-especificaciones">
            ${especificaciones.map(esp => `<li class="especificacion-tag"><i class="fa-solid fa-circle-notch"></i> ${escaparHTML(esp)}</li>`).join("")}
          </ul>`
        : "";

    return `<li class="item-producto-kds">
        <div class="item-principal">
            <span class="item-cantidad">${cantidad}</span>
            <span class="item-nombre">${nombre}</span>
        </div>
        ${specsHTML}
    </li>`;
}

let filtroMesaActual = "todas";

function esPedidoPrioritario(pedido, espera) {
    return pedido.prioridad === "alta" || pedido.priority === "alta" || espera >= 15;
}

function renderizarPedido(pedido, ahora) {
    const estado = pedido.status;
    const espera = minutosEnEspera(pedido, ahora);
    const claseEspera = claseAlerta(pedido, espera);
    const mesaNumero = extraerNumeroMesa(pedido);
    const esPrioridad = esPedidoPrioritario(pedido, espera);

    const fechaRecepcion = new Date(obtenerFechaRecepcion(pedido));
    const hora = Number.isNaN(fechaRecepcion.getTime())
        ? escaparHTML(pedido.time || "12:00 PM")
        : fechaRecepcion.toLocaleTimeString("es-CO", { hour: "2-digit", minute: "2-digit" });

    // Mensaje de estado específico
    let estadoMensajeClass = "estado-cola";
    let estadoTexto = "En cola (Pendiente)";
    let estadoIcono = "fa-clock";
    let botonAccion = `<button type="button" class="btn-accion-libreta btn-iniciar" data-estado-pedido="${escaparHTML(pedido.id)}" data-nuevo-estado="${nombresEstados.preparacion}">
        <i class="fa-solid fa-fire-burner"></i> Iniciar preparación
    </button>`;

    if (estado === nombresEstados.preparacion) {
        estadoMensajeClass = "estado-preparacion";
        estadoTexto = "En preparación en cocina";
        estadoIcono = "fa-fire";
        botonAccion = `<button type="button" class="btn-accion-libreta btn-completar" data-completar-pedido="${escaparHTML(pedido.id)}">
            <i class="fa-solid fa-check"></i> Completar pedido
        </button>`;
    } else if (estado === nombresEstados.listo) {
        estadoMensajeClass = "estado-listo";
        estadoTexto = "¡Listo para servir!";
        estadoIcono = "fa-circle-check";
        botonAccion = `<button type="button" class="btn-accion-libreta btn-retirar" data-completar-pedido="${escaparHTML(pedido.id)}">
            <i class="fa-solid fa-paper-plane"></i> Retirar de cocina
        </button>`;
    }

    const productos = pedido.items.length
        ? pedido.items.map(renderizarItem).join("")
        : '<li class="item-producto-kds"><div class="item-principal"><span class="item-nombre">Sin productos registrados</span></div></li>';

    const clasePrioridad = esPrioridad ? "prioridad-alta prioridad-vibracion" : "";
    const cintaPrioridad = esPrioridad
        ? `<div class="cinta-prioridad-alta" role="status">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span>${espera >= 15 ? `TIEMPO EXCESIVO (${espera}m) · PRIORIDAD` : "PRIORIDAD ALTA"}</span>
          </div>`
        : "";

    return `<article class="libreta-comanda ${claseEspera} ${clasePrioridad}" data-pedido-id="${escaparHTML(pedido.id)}" id="comanda-${escaparHTML(pedido.id)}">
        <!-- Pinza metálica superior -->
        <div class="pinza-comanda" aria-hidden="true"></div>

        <!-- Cinta de alta prioridad con sutil vibración si aplica -->
        ${cintaPrioridad}

        <!-- Encabezado con SOLO: ID, MESA, HORA -->
        <div class="libreta-encabezado">
            <div class="comanda-meta-izq">
                <div class="comanda-mesa">
                    <span class="comanda-mesa-label">Mesa</span>
                    <span>${escaparHTML(mesaNumero)}</span>
                </div>
                <div class="comanda-detalles-fila">
                    <span class="comanda-id">ID: #${escaparHTML(pedido.id)}</span>
                    <span class="comanda-hora"><i class="fa-regular fa-clock" aria-hidden="true"></i> ${hora}</span>
                </div>
            </div>

            <!-- Botón Check KDS con tiempo transcurrido -->
            <button type="button" class="btn-completar-kds" data-completar-pedido="${escaparHTML(pedido.id)}" title="Completar pedido #${escaparHTML(pedido.id)}" aria-label="Completar pedido #${escaparHTML(pedido.id)}">
                <i class="fa-solid fa-check" aria-hidden="true"></i>
                <span class="timer-transcurrido" data-cronometro="${escaparHTML(pedido.id)}">${espera}m</span>
            </button>
        </div>

        <!-- Línea de desprendimiento / perforación -->
        <div class="libreta-perforacion" aria-hidden="true"></div>

        <!-- Cuerpo: Productos y sus especificaciones -->
        <div class="libreta-cuerpo">
            <div class="libreta-seccion-titulo">Productos y Comanda</div>
            <ul class="lista-productos-kds">
                ${productos}
            </ul>
        </div>

        <!-- Pie: Mensaje de estado y botón de acción -->
        <div class="libreta-pie">
            <div class="mensaje-estado-comanda ${estadoMensajeClass}">
                <i class="fa-solid ${estadoIcono}" aria-hidden="true"></i>
                <span>${estadoTexto}</span>
            </div>
            ${botonAccion}
        </div>
    </article>`;
}

function renderizarFiltrosMesas(pedidos) {
    const contenedor = document.getElementById("filtroMesasCocina");
    if (!contenedor) return;

    // Conteo por mesa
    const conteos = {};
    pedidos.forEach(p => {
        const mesa = extraerNumeroMesa(p);
        conteos[mesa] = (conteos[mesa] || 0) + 1;
    });

    const mesasOrdenadas = Object.keys(conteos).sort((a, b) => {
        const numA = Number(a), numB = Number(b);
        if (!Number.isNaN(numA) && !Number.isNaN(numB)) return numA - numB;
        return a.localeCompare(b);
    });

    // Si la mesa seleccionada ya no tiene comandas, volver a 'todas'
    if (filtroMesaActual !== "todas" && !conteos[filtroMesaActual]) {
        filtroMesaActual = "todas";
    }

    let html = `<button type="button" class="btn-filtro-mesa ${filtroMesaActual === 'todas' ? 'active' : ''}" data-mesa="todas">
        <i class="fa-solid fa-layer-group"></i> Todas las mesas
        <span class="badge-conteo-mesa">${pedidos.length}</span>
    </button>`;

    mesasOrdenadas.forEach(mesa => {
        const activa = String(filtroMesaActual) === String(mesa) ? "active" : "";
        html += `<button type="button" class="btn-filtro-mesa ${activa}" data-mesa="${escaparHTML(mesa)}">
            <i class="fa-solid fa-chair"></i> Mesa ${escaparHTML(mesa)}
            <span class="badge-conteo-mesa">${conteos[mesa]}</span>
        </button>`;
    });

    contenedor.innerHTML = html;
}

function renderizarBarraInferior(pedidos, ahora) {
    const contenedor = document.getElementById("pildorasComandas");
    if (!contenedor) return;

    if (!pedidos.length) {
        contenedor.innerHTML = '<span style="color: rgba(255,255,255,0.4); font-size: 12px;">Sin comandas activas</span>';
        return;
    }

    contenedor.innerHTML = pedidos.map(p => {
        const mesa = extraerNumeroMesa(p);
        const espera = minutosEnEspera(p, ahora);
        const clase = claseAlerta(p, espera);
        const esPrioridad = esPedidoPrioritario(p, espera);
        const iconoPrioridad = esPrioridad ? '<i class="fa-solid fa-bolt" style="color: #F87171; margin-right: 2px;"></i>' : '';
        return `<button type="button" class="pildora-comanda-btn ${clase}" data-scroll-comanda="comanda-${escaparHTML(p.id)}">
            ${iconoPrioridad}<span>Mesa ${escaparHTML(mesa)}</span>
            <span style="opacity: 0.7;">#${escaparHTML(p.id)}</span>
        </button>`;
    }).join("");
}

function mostrarPedidos() {
    const contenedor = document.getElementById("contenedor-pedidos");
    if (!contenedor) return;

    const ahora = Date.now();
    const pedidosActivos = leerPedidos()
        .map(pedido => ({ ...pedido, status: normalizarEstado(pedido.status) }))
        .filter(pedido => ![nombresEstados.entregado, nombresEstados.cancelado].includes(pedido.status));

    // Actualizar los 3 contadores icónicos globales
    const contadorPendientes = document.getElementById("contador-pendientes");
    const contadorPreparacion = document.getElementById("contador-preparacion");
    const contadorListos = document.getElementById("contador-listos");
    if (contadorPendientes) contadorPendientes.textContent = pedidosActivos.filter(p => p.status === nombresEstados.cola).length;
    if (contadorPreparacion) contadorPreparacion.textContent = pedidosActivos.filter(p => p.status === nombresEstados.preparacion).length;
    if (contadorListos) contadorListos.textContent = pedidosActivos.filter(p => p.status === nombresEstados.listo).length;

    // Renderizar filtro de mesas (ubicado debajo de las 3 cajas)
    renderizarFiltrosMesas(pedidosActivos);

    // Filtrar comandas según mesa seleccionada
    const pedidosFiltrados = (filtroMesaActual === "todas")
        ? pedidosActivos
        : pedidosActivos.filter(p => String(extraerNumeroMesa(p)) === String(filtroMesaActual));

    // Ordenar: pedidos de ALTA PRIORIDAD primero, luego en preparación, luego por antigüedad
    pedidosFiltrados.sort((a, b) => {
        const esperaA = minutosEnEspera(a, ahora);
        const esperaB = minutosEnEspera(b, ahora);
        const prioA = esPedidoPrioritario(a, esperaA) ? 1 : 0;
        const prioB = esPedidoPrioritario(b, esperaB) ? 1 : 0;

        if (prioA !== prioB) return prioB - prioA; // Prioridad alta primero
        if (a.status === nombresEstados.preparacion && b.status !== nombresEstados.preparacion) return -1;
        if (b.status === nombresEstados.preparacion && a.status !== nombresEstados.preparacion) return 1;
        return obtenerFechaRecepcion(a) - obtenerFechaRecepcion(b);
    });

    contenedor.innerHTML = pedidosFiltrados.length
        ? pedidosFiltrados.map(pedido => renderizarPedido(pedido, ahora)).join("")
        : `<p class="cocina-vacia" style="margin: 30px auto; color: var(--palette-gold, #C99B45); font-weight: 700; text-align: center;">
            ${filtroMesaActual === "todas" ? "No hay comandas activas en la cuerda." : `No hay comandas activas para la Mesa ${escaparHTML(filtroMesaActual)}.`}
          </p>`;

    renderizarBarraInferior(pedidosFiltrados, ahora);
}

// ========================================================
// ANIMACIÓN DE ARRUGADO Y DESCARTE DE COMANDA (CRUMPLE & THROW)
// ========================================================
function arrugarYTirarComanda(id, nuevoEstado = nombresEstados.listo) {
    const comandaEl = document.querySelector(`.libreta-comanda[data-pedido-id="${CSS.escape(String(id))}"]`);

    reproducirSonidoArrugar();

    if (comandaEl) {
        comandaEl.classList.add("animacion-arrugar");
    }

    // Esperar a que la animación 3D de arrugado y lanzamiento termine (~800ms)
    window.setTimeout(() => {
        const pedidos = leerPedidos();
        const pedido = pedidos.find(actual => String(actual.id) === String(id));
        if (!pedido) {
            mostrarPedidos();
            return;
        }

        const actualizado = {
            ...pedido,
            status: nuevoEstado,
            statusClass: "status-listo",
            updatedAt: new Date().toISOString()
        };

        const actualizados = pedidos.map(actual => String(actual.id) === String(id) ? actualizado : actual);

        try {
            localStorage.setItem(clavePedidos, JSON.stringify(actualizados));
            window.dispatchEvent(new CustomEvent(eventoPedidos, { detail: actualizados }));
            mostrarError("");
        } catch (e) {
            mostrarError("No se pudo guardar la actualización.");
        }

        mostrarPedidos();
    }, 780);
}

function cambiarEstadoSimple(id, nuevoEstado) {
    const pedidos = leerPedidos();
    const pedido = pedidos.find(actual => String(actual.id) === String(id));
    if (!pedido) return;

    const actualizado = {
        ...pedido,
        status: nuevoEstado,
        updatedAt: new Date().toISOString()
    };

    const actualizados = pedidos.map(actual => String(actual.id) === String(id) ? actualizado : actual);
    localStorage.setItem(clavePedidos, JSON.stringify(actualizados));
    window.dispatchEvent(new CustomEvent(eventoPedidos, { detail: actualizados }));
    mostrarPedidos();
}

// Navegación con flechas del riel
const riel = document.getElementById("contenedor-pedidos");
document.getElementById("btnRielPrev")?.addEventListener("click", () => {
    if (riel) riel.scrollBy({ left: -320, behavior: "smooth" });
});
document.getElementById("btnRielNext")?.addEventListener("click", () => {
    if (riel) riel.scrollBy({ left: 320, behavior: "smooth" });
});

// Click en píldoras inferiores para hacer scroll a una comanda
document.getElementById("pildorasComandas")?.addEventListener("click", (e) => {
    const boton = e.target.closest("[data-scroll-comanda]");
    if (boton) {
        const targetEl = document.getElementById(boton.dataset.scrollComanda);
        if (targetEl) targetEl.scrollIntoView({ behavior: "smooth", inline: "center", block: "nearest" });
    }
});

// Eventos de completar (arrugar y tirar) y avanzar estado
document.getElementById("contenedor-pedidos")?.addEventListener("click", evento => {
    const btnCompletar = evento.target.closest("[data-completar-pedido]");
    if (btnCompletar) {
        evento.preventDefault();
        arrugarYTirarComanda(btnCompletar.dataset.completarPedido);
        return;
    }

    const btnEstado = evento.target.closest("[data-estado-pedido]");
    if (btnEstado) {
        evento.preventDefault();
        cambiarEstadoSimple(btnEstado.dataset.estadoPedido, btnEstado.dataset.nuevoEstado);
    }
});

// Click en píldoras de filtro de mesas (ubicado debajo de las 3 cajas)
document.getElementById("filtroMesasCocina")?.addEventListener("click", (e) => {
    const boton = e.target.closest("[data-mesa]");
    if (boton) {
        filtroMesaActual = boton.dataset.mesa || "todas";
        mostrarPedidos();
    }
});

// Botón para restaurar/recargar ejemplos (solo visible y accesible para admin)
function asegurarVisibilidadDemo() {
    const sesion = typeof leerSesion === "function" ? leerSesion() : null;
    const rol = sesion?.rol ? claveRol(sesion.rol) : "";
    if (rol === "cocina") {
        const btn = document.getElementById("btnRestaurarDemo");
        if (btn) btn.remove();
    }
}
asegurarVisibilidadDemo();

document.getElementById("btnRestaurarDemo")?.addEventListener("click", () => {
    localStorage.setItem(clavePedidos, JSON.stringify(pedidosDemoKds));
    window.dispatchEvent(new CustomEvent(eventoPedidos, { detail: pedidosDemoKds }));
    mostrarPedidos();
});

window.addEventListener("storage", evento => {
    if (evento.key === clavePedidos) mostrarPedidos();
});
window.addEventListener(eventoPedidos, mostrarPedidos);

// Inicialización
mostrarPedidos();
window.setInterval(() => {
    mostrarPedidos();
}, 15000);
