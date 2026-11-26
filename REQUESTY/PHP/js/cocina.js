/**
 * Requesty KDS - Comandas Simples en Riel Metálico
 * Con colores Amarillo (advertencia) y Rojo (crítico), vibración y opción de arrugar/tirar
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

// Pedidos demo simples que muestran los estados Rojo (vibración), Amarillo y Blanco normal
const pedidosDemoKds = [
    {
        id: "1885",
        mesa: 3,
        destination: "Mesa 3",
        channel: "Mesa",
        prioridad: "alta",
        time: "20:15",
        receivedAt: new Date(Date.now() - 22 * 60000).toISOString(),
        status: "En preparación",
        items: [
            { id: 1, nombre: "Pizza Mediana", cantidad: 1, especificaciones: ["Jamón y Queso"] },
            { id: 2, nombre: "Gaseosa 1.5 L", cantidad: 1, especificaciones: [] }
        ]
    },
    {
        id: "1886",
        mesa: 7,
        destination: "Mesa 7",
        channel: "Mesa",
        time: "20:17",
        receivedAt: new Date(Date.now() - 14 * 60000).toISOString(),
        status: "En preparación",
        items: [
            { id: 3, nombre: "Pizza Grande", cantidad: 1, especificaciones: ["Carnívora"] },
            { id: 4, nombre: "Agua", cantidad: 1, especificaciones: [] }
        ]
    },
    {
        id: "156",
        mesa: "Delivery",
        destination: "Delivery",
        channel: "Domicilio",
        time: "20:18",
        receivedAt: new Date(Date.now() - 4 * 60000).toISOString(),
        status: "En cola",
        items: [
            { id: 5, nombre: "Pizza Pequeña", cantidad: 1, especificaciones: ["Jamón y Queso"] },
            { id: 6, nombre: "Refresco", cantidad: 1, especificaciones: [] }
        ]
    },
    {
        id: "157",
        mesa: 12,
        destination: "Mesa 12",
        channel: "Mesa",
        time: "20:20",
        receivedAt: new Date(Date.now() - 2 * 60000).toISOString(),
        status: "En cola",
        items: [
            { id: 7, nombre: "Burger Clásica", cantidad: 2, especificaciones: ["Término 3/4", "Sin cebolla"] },
            { id: 8, nombre: "Limonada Natural", cantidad: 2, especificaciones: [] }
        ]
    }
];

let audioCtx = null;
function reproducirSonidoArrugar() {
    try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext;
        if (!AudioContextClass) return;
        if (!audioCtx) audioCtx = new AudioContextClass();
        if (audioCtx.state === "suspended") audioCtx.resume();

        const duracion = 0.55;
        const bufferSize = Math.floor(audioCtx.sampleRate * duracion);
        const buffer = audioCtx.createBuffer(1, bufferSize, audioCtx.sampleRate);
        const data = buffer.getChannelData(0);

        for (let i = 0; i < bufferSize; i++) {
            const t = i / bufferSize;
            const envolvente = Math.pow(1 - t, 0.75);
            const crujidoFibra = Math.random() > 0.86 ? (Math.random() * 2 - 1) * 1.4 : (Math.random() * 0.4 - 0.2);
            data[i] = crujidoFibra * envolvente;
        }

        const noise = audioCtx.createBufferSource();
        noise.buffer = buffer;

        const filter = audioCtx.createBiquadFilter();
        filter.type = "bandpass";
        filter.frequency.setValueAtTime(1800, audioCtx.currentTime);
        filter.frequency.exponentialRampToValueAtTime(420, audioCtx.currentTime + duracion);
        filter.Q.value = 2.8;

        const gainNode = audioCtx.createGain();
        gainNode.gain.setValueAtTime(0.42, audioCtx.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + duracion);

        noise.connect(filter);
        filter.connect(gainNode);
        gainNode.connect(audioCtx.destination);

        noise.start();
    } catch (e) {}
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
        if (guardados === null) {
            guardados = pedidosDemoKds;
            localStorage.setItem(clavePedidos, JSON.stringify(guardados));
        }
        if (!Array.isArray(guardados)) {
            guardados = [];
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

function esPedidoPrioritario(pedido, espera) {
    return pedido.prioridad === "alta" || pedido.priority === "alta" || espera >= minutosCriticos;
}

function claseAlerta(pedido, espera) {
    if (pedido.status === nombresEstados.listo) return "alerta-listo";
    if (esPedidoPrioritario(pedido, espera)) return "alerta-critica";
    if (espera >= minutosAlerta) return "alerta-advertencia";
    return "alerta-normal";
}

function extraerEtiquetaDestino(pedido) {
    const canal = String(pedido.channel || pedido.tipo || "").toLowerCase();
    const dest = String(pedido.destination || "").trim();
    const mesaRaw = String(pedido.mesa ?? "").trim();

    if (canal.includes("domicilio") || canal.includes("delivery") || dest.toLowerCase() === "delivery" || mesaRaw.toLowerCase() === "delivery") {
        return { titulo: "Delivery", claveFiltro: "Delivery" };
    }
    if (canal.includes("llevar")) {
        return { titulo: "Para llevar", claveFiltro: "Para llevar" };
    }
    if (mesaRaw !== "") {
        const limpia = mesaRaw.replace(/^mesa\s*:?\s*/i, "");
        return { titulo: `Mesa: ${limpia}`, claveFiltro: limpia };
    }
    const match = dest.match(/Mesa\s*:?\s*([0-9a-zA-Z]+)/i);
    if (match) {
        return { titulo: `Mesa: ${match[1]}`, claveFiltro: match[1] };
    }
    return { titulo: "Mesa: 1", claveFiltro: "1" };
}

function obtenerEspecificaciones(item) {
    const especificaciones = [];
    if (Array.isArray(item.especificaciones)) {
        item.especificaciones.forEach(esp => especificaciones.push(String(esp)));
    }
    if (item.terminoCoccion) especificaciones.push(`Término: ${item.terminoCoccion}`);
    if (item.alternativaReceta) especificaciones.push(item.alternativaReceta);
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
            ${especificaciones.map(esp => `<li class="especificacion-tag">${escaparHTML(esp)}</li>`).join("")}
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

function renderizarPedido(pedido, ahora) {
    const estado = pedido.status;
    const espera = minutosEnEspera(pedido, ahora);
    const claseEspera = claseAlerta(pedido, espera);
    const esPrioridad = esPedidoPrioritario(pedido, espera);
    const clasePrioridad = esPrioridad ? "prioridad-alta prioridad-vibracion" : "";
    const { titulo } = extraerEtiquetaDestino(pedido);

    const fechaRecepcion = new Date(obtenerFechaRecepcion(pedido));
    const hora = pedido.time
        ? escaparHTML(pedido.time)
        : Number.isNaN(fechaRecepcion.getTime())
            ? "20:15"
            : fechaRecepcion.toLocaleTimeString("es-CO", { hour: "2-digit", minute: "2-digit", hour12: false });

    let etiquetaEstado = "En espera";
    let botonAccion = `<button type="button" class="btn-accion-libreta btn-iniciar" data-estado-pedido="${escaparHTML(pedido.id)}" data-nuevo-estado="${nombresEstados.preparacion}">
        Iniciar
    </button>`;

    if (estado === nombresEstados.preparacion) {
        etiquetaEstado = esPrioridad ? "Urgente · En preparación" : (espera >= minutosAlerta ? "Demorado · En preparación" : "En preparación");
        botonAccion = `<button type="button" class="btn-accion-libreta btn-completar" data-completar-pedido="${escaparHTML(pedido.id)}">
            <i class="fa-solid fa-check"></i> Listo
        </button>`;
    } else if (estado === nombresEstados.listo) {
        etiquetaEstado = "Listo para servir";
        botonAccion = `<button type="button" class="btn-accion-libreta btn-retirar" data-completar-pedido="${escaparHTML(pedido.id)}">
            Retirar
        </button>`;
    } else if (esPrioridad) {
        etiquetaEstado = "Urgente · En espera";
    } else if (espera >= minutosAlerta) {
        etiquetaEstado = "Demorado · En espera";
    }

    const productos = pedido.items.length
        ? pedido.items.map(renderizarItem).join("")
        : '<li class="item-producto-kds"><div class="item-principal"><span class="item-nombre">Sin productos</span></div></li>';

    return `<article class="libreta-comanda ${claseEspera} ${clasePrioridad}" data-pedido-id="${escaparHTML(pedido.id)}" id="comanda-${escaparHTML(pedido.id)}">
        <button type="button" class="btn-desechar-kds" data-desechar-pedido="${escaparHTML(pedido.id)}" title="Arrugar y tirar comanda #${escaparHTML(pedido.id)}" aria-label="Tirar comanda #${escaparHTML(pedido.id)}">
            <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
        </button>

        <div class="libreta-encabezado">
            <h2 class="comanda-titulo-simple">${escaparHTML(titulo)}</h2>
            <span class="comanda-estado-tag">${escaparHTML(etiquetaEstado)}</span>
        </div>

        <div class="libreta-cuerpo">
            <ul class="lista-productos-kds">
                ${productos}
            </ul>
        </div>

        <div class="libreta-pie">
            <div class="comanda-hora-fila">
                <span>Hora: ${hora}</span>
                <span class="comanda-tiempo-badge">${espera}m · #${escaparHTML(pedido.id)}</span>
            </div>
            <div class="comanda-acciones-pie">
                ${botonAccion}
                <button type="button" class="btn-tirar-pie" data-desechar-pedido="${escaparHTML(pedido.id)}" title="Arrugar y tirar comanda">
                    <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Tirar
                </button>
            </div>
        </div>
    </article>`;
}

function renderizarFiltrosMesas(pedidos) {
    const contenedor = document.getElementById("filtroMesasCocina");
    if (!contenedor) return;

    const conteos = {};
    pedidos.forEach(p => {
        const { claveFiltro } = extraerEtiquetaDestino(p);
        conteos[claveFiltro] = (conteos[claveFiltro] || 0) + 1;
    });

    const mesasOrdenadas = Object.keys(conteos).sort((a, b) => {
        const numA = Number(a), numB = Number(b);
        if (!Number.isNaN(numA) && !Number.isNaN(numB)) return numA - numB;
        return a.localeCompare(b);
    });

    if (filtroMesaActual !== "todas" && !conteos[filtroMesaActual]) {
        filtroMesaActual = "todas";
    }

    let html = `<button type="button" class="btn-filtro-mesa ${filtroMesaActual === 'todas' ? 'active' : ''}" data-mesa="todas">
        <i class="fa-solid fa-layer-group"></i> Todas las mesas
        <span class="badge-conteo-mesa">${pedidos.length}</span>
    </button>`;

    mesasOrdenadas.forEach(mesa => {
        const activa = String(filtroMesaActual) === String(mesa) ? "active" : "";
        const etiqueta = Number.isNaN(Number(mesa)) ? escaparHTML(mesa) : `Mesa ${escaparHTML(mesa)}`;
        html += `<button type="button" class="btn-filtro-mesa ${activa}" data-mesa="${escaparHTML(mesa)}">
            <i class="fa-solid fa-chair"></i> ${etiqueta}
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
        const { titulo } = extraerEtiquetaDestino(p);
        const espera = minutosEnEspera(p, ahora);
        const clase = claseAlerta(p, espera);
        const esPrioridad = esPedidoPrioritario(p, espera);
        const iconoPrioridad = esPrioridad ? '<i class="fa-solid fa-bolt" style="color: #F87171; margin-right: 2px;"></i>' : '';
        return `<button type="button" class="pildora-comanda-btn ${clase}" data-scroll-comanda="comanda-${escaparHTML(p.id)}">
            ${iconoPrioridad}<span>${escaparHTML(titulo)}</span>
            <span style="opacity: 0.7;">#${escaparHTML(p.id)}</span>
        </button>`;
    }).join("");
}

function mostrarPedidos() {
    const contenedor = document.getElementById("contenedor-pedidos");
    if (!contenedor) return;

    const ahora = Date.now();
    const todosLosPedidos = leerPedidos()
        .map(pedido => ({ ...pedido, status: normalizarEstado(pedido.status) }));

    const contadorPendientes = document.getElementById("contador-pendientes");
    const contadorPreparacion = document.getElementById("contador-preparacion");
    const contadorListos = document.getElementById("contador-listos");
    if (contadorPendientes) contadorPendientes.textContent = todosLosPedidos.filter(p => p.status === nombresEstados.cola).length;
    if (contadorPreparacion) contadorPreparacion.textContent = todosLosPedidos.filter(p => p.status === nombresEstados.preparacion).length;
    if (contadorListos) contadorListos.textContent = todosLosPedidos.filter(p => p.status === nombresEstados.listo).length;

    const pedidosActivos = todosLosPedidos
        .filter(pedido => !pedido.comandaRetirada && ![nombresEstados.entregado, nombresEstados.cancelado].includes(pedido.status));

    renderizarFiltrosMesas(pedidosActivos);

    const pedidosFiltrados = (filtroMesaActual === "todas")
        ? pedidosActivos
        : pedidosActivos.filter(p => String(extraerEtiquetaDestino(p).claveFiltro) === String(filtroMesaActual));

    contenedor.innerHTML = pedidosFiltrados.length
        ? pedidosFiltrados.map(pedido => renderizarPedido(pedido, ahora)).join("")
        : `<p class="cocina-vacia" style="margin: 30px auto; color: var(--palette-gold, #C99B45); font-weight: 700; text-align: center;">
            No hay comandas activas en el riel.
          </p>`;

    renderizarBarraInferior(pedidosFiltrados, ahora);
}

function arrugarYTirarComanda(id, nuevoEstado = nombresEstados.listo) {
    const comandaEl = document.querySelector(`.libreta-comanda[data-pedido-id="${CSS.escape(String(id))}"]`);
    reproducirSonidoArrugar();

    if (comandaEl) {
        comandaEl.classList.add("animacion-arrugar");
    }

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
            comandaRetirada: true,
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
    }, 550);
}

function desecharComanda(id) {
    const comandaEl = document.querySelector(`.libreta-comanda[data-pedido-id="${CSS.escape(String(id))}"]`);
    reproducirSonidoArrugar();

    if (comandaEl) {
        comandaEl.classList.add("animacion-arrugar");
    }

    window.setTimeout(() => {
        const pedidos = leerPedidos();
        const actualizados = pedidos.filter(actual => String(actual.id) !== String(id));
        try {
            localStorage.setItem(clavePedidos, JSON.stringify(actualizados));
            window.dispatchEvent(new CustomEvent(eventoPedidos, { detail: actualizados }));
            mostrarError("");
        } catch (e) {
            mostrarError("No se pudo eliminar la comanda.");
        }
        mostrarPedidos();
    }, 550);
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

const riel = document.getElementById("contenedor-pedidos");
document.getElementById("btnRielPrev")?.addEventListener("click", () => {
    if (riel) riel.scrollBy({ left: -260, behavior: "smooth" });
});
document.getElementById("btnRielNext")?.addEventListener("click", () => {
    if (riel) riel.scrollBy({ left: 260, behavior: "smooth" });
});

document.getElementById("pildorasComandas")?.addEventListener("click", (e) => {
    const boton = e.target.closest("[data-scroll-comanda]");
    if (boton) {
        const targetEl = document.getElementById(boton.dataset.scrollComanda);
        if (targetEl) targetEl.scrollIntoView({ behavior: "smooth", inline: "center", block: "nearest" });
    }
});

document.getElementById("contenedor-pedidos")?.addEventListener("click", evento => {
    const btnDesechar = evento.target.closest("[data-desechar-pedido]");
    if (btnDesechar) {
        evento.preventDefault();
        desecharComanda(btnDesechar.dataset.desecharPedido);
        return;
    }

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

document.getElementById("filtroMesasCocina")?.addEventListener("click", (e) => {
    const boton = e.target.closest("[data-mesa]");
    if (boton) {
        filtroMesaActual = boton.dataset.mesa || "todas";
        mostrarPedidos();
    }
});

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

mostrarPedidos();
window.setInterval(() => {
    mostrarPedidos();
}, 15000);
