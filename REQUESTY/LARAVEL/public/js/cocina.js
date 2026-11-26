/**
 * Requesty KDS - Lógica de Cocina en Cuerda con Libretas Colgantes (Laravel + PostgreSQL)
 * Soporta animación de arrugado, síntesis de sonido Web Audio API y sincronización API en tiempo real
 */
(function () {
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
            esDemo: true,
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
            esDemo: true,
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
            esDemo: true,
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
            esDemo: true,
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
            esDemo: true,
            items: [
                { id: 18, nombre: "Timbal de Mariscos", cantidad: 1, especificaciones: [] },
                { id: 19, nombre: "Tabla de Quesos", cantidad: 1, especificaciones: [] },
                { id: 20, nombre: "Burger Vegetariana", cantidad: 1, especificaciones: ["Extra queso vegano"] },
                { id: 21, nombre: "Tiramisú Casero", cantidad: 1, especificaciones: [] }
            ]
        }
    ];

    let pedidosMemoria = [];
    const comandasRetiradas = new Set();
    const comandasDesechadas = new Set();
    let filtroMesaActual = "todas";

    // Síntesis de sonido de papel arrugándose con Web Audio API (cero dependencias externas)
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
        } catch (e) {
            // En caso de que el navegador bloquee audio autoplay
        }
    }

    function normalizarEstado(estado) {
        const normalizado = String(estado || "").trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLocaleLowerCase("es");
        if (["nuevo", "pendiente", "en cola", "cola"].includes(normalizado)) return nombresEstados.cola;
        if (["preparando", "en preparacion", "en_preparacion", "preparacion"].includes(normalizado)) return nombresEstados.preparacion;
        if (["listo", "listo para servir"].includes(normalizado)) return nombresEstados.listo;
        if (normalizado === "entregado") return nombresEstados.entregado;
        if (normalizado === "cancelado") return nombresEstados.cancelado;
        return nombresEstados.cola;
    }

    function estadoApiDesdeNombre(nombreEstado) {
        const norm = normalizarEstado(nombreEstado);
        if (norm === nombresEstados.preparacion) return "EN_PREPARACION";
        if (norm === nombresEstados.listo) return "LISTO";
        if (norm === nombresEstados.entregado) return "ENTREGADO";
        if (norm === nombresEstados.cancelado) return "CANCELADO";
        return "PENDIENTE";
    }

    function escaparHTML(valor) {
        if (window.RequestyAPI && typeof window.RequestyAPI.escaparHTML === "function") {
            return window.RequestyAPI.escaparHTML(valor);
        }
        return String(valor ?? "").replace(/[&<>"']/g, caracter => ({
            "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
        })[caracter]);
    }

    function obtenerFechaRecepcion(pedido) {
        const guardada = Date.parse(pedido.receivedAt || pedido.createdAt || pedido.fecha_hora || "");
        if (Number.isFinite(guardada)) return guardada;
        const coincidencia = String(pedido.time || "").match(/^(\d{1,2}):(\d{2})/);
        if (!coincidencia) return Date.now();
        const fecha = new Date();
        fecha.setHours(Number(coincidencia[1]), Number(coincidencia[2]), 0, 0);
        if (fecha.getTime() > Date.now()) fecha.setDate(fecha.getDate() - 1);
        return fecha.getTime();
    }

    function mostrarError(mensaje) {
        const contenedor = document.getElementById("mensaje-cocina");
        if (contenedor) contenedor.textContent = mensaje || "";
    }

    function minutosEnEspera(pedido, ahora = Date.now()) {
        return Math.max(0, Math.floor((ahora - obtenerFechaRecepcion(pedido)) / 60000));
    }

    function claseAlerta(pedido, espera) {
        if (pedido.status === nombresEstados.listo) return "alerta-listo";
        if (espera >= minutosCriticos) return "alerta-critica";
        if (espera >= minutosAlerta) return "alerta-advertencia";
        return "alerta-normal";
    }

    function extraerNumeroMesa(pedido) {
        const cand = pedido.mesa ?? pedido.numero_mesa ?? pedido.id_mesa;
        if (cand !== undefined && cand !== null && String(cand).trim() !== "") {
            return String(cand).replace(/[^0-9a-zA-Z]/g, "") || "1";
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
        if (item.terminoCoccion || item.termino_coccion) {
            especificaciones.push(`Término: ${item.terminoCoccion || item.termino_coccion}`);
        }
        if (item.observaciones) {
            especificaciones.push(String(item.observaciones));
        }
        if (item.alternativaReceta) {
            especificaciones.push(`Opción: ${item.alternativaReceta}`);
        }
        const excluidos = item.ingredientesExcluidos || item.ingredientes_excluidos;
        if (Array.isArray(excluidos) && excluidos.length) {
            especificaciones.push(`Sin ${excluidos.join(", ")}`);
        }
        (item.extras || []).forEach(extra => {
            especificaciones.push(`+ ${extra.nombre || extra}`);
        });
        return especificaciones;
    }

    function renderizarItem(item) {
        const especificaciones = obtenerEspecificaciones(item);
        const cantidad = Math.max(1, Number(item.cantidad) || 1);
        const nombre = escaparHTML(item.nombre || item.producto_nombre || item.producto || "Plato");

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

        let estadoMensajeClass = "estado-cola";
        let estadoTexto = "En espera";
        let estadoIcono = "fa-clock";
        let botonAccion = `<button type="button" class="btn-accion-libreta btn-iniciar" data-estado-pedido="${escaparHTML(pedido.id)}" data-nuevo-estado="${nombresEstados.preparacion}">
            <i class="fa-solid fa-fire-burner"></i> Iniciar preparación
        </button>`;

        if (estado === nombresEstados.preparacion) {
            estadoMensajeClass = "estado-preparacion";
            estadoTexto = "En preparación";
            estadoIcono = "fa-fire";
            botonAccion = `<button type="button" class="btn-accion-libreta btn-completar" data-completar-pedido="${escaparHTML(pedido.id)}">
                <i class="fa-solid fa-check"></i> Completar pedido
            </button>`;
        } else if (estado === nombresEstados.listo) {
            estadoMensajeClass = "estado-listo";
            estadoTexto = "Listo";
            estadoIcono = "fa-circle-check";
            botonAccion = `<button type="button" class="btn-accion-libreta btn-retirar" data-completar-pedido="${escaparHTML(pedido.id)}">
                <i class="fa-solid fa-paper-plane"></i> Retirar de cocina
            </button>`;
        }

        const productos = Array.isArray(pedido.items) && pedido.items.length
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
            <!-- Pinza metálica superior sujetando la hoja -->
            <div class="pinza-comanda" aria-hidden="true"></div>

            <!-- Cinta de alta prioridad con sutil vibración si aplica -->
            ${cintaPrioridad}

            <!-- Troquelado y perforaciones de libreta de pedidos arrancada -->
            <div class="papel-troquelado-superior" aria-hidden="true">
                <div class="agujeros-perforados">
                    <span></span><span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                </div>
                <div class="linea-desprendimiento"></div>
            </div>

            <!-- Encabezado impreso en papel: Mesa, ID, Hora y Sello KDS -->
            <div class="libreta-encabezado">
                <div class="comanda-meta-izq">
                    <div class="comanda-mesa">
                        <span class="comanda-mesa-label">Mesa</span>
                        <span class="comanda-mesa-numero">${escaparHTML(mesaNumero)}</span>
                    </div>
                    <div class="comanda-detalles-fila">
                        <span class="comanda-id">#${escaparHTML(pedido.id)}</span>
                        <span class="comanda-hora"><i class="fa-regular fa-clock" aria-hidden="true"></i> ${hora}</span>
                    </div>
                </div>

                <div class="comanda-acciones-header">
                    <!-- Botón Desechar / Tirar comanda a la papelera -->
                    <button type="button" class="btn-desechar-kds" data-desechar-pedido="${escaparHTML(pedido.id)}" title="Tirar hoja a la papelera (Borrar comanda #${escaparHTML(pedido.id)})" aria-label="Borrar comanda #${escaparHTML(pedido.id)}">
                        <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                    </button>

                    <!-- Botón / Sello KDS con cronómetro de espera -->
                    <button type="button" class="btn-completar-kds" data-completar-pedido="${escaparHTML(pedido.id)}" title="Completar pedido #${escaparHTML(pedido.id)}" aria-label="Completar pedido #${escaparHTML(pedido.id)}">
                        <i class="fa-solid fa-check" aria-hidden="true"></i>
                        <span class="timer-transcurrido" data-cronometro="${escaparHTML(pedido.id)}">${espera}m</span>
                    </button>
                </div>
            </div>

            <!-- Cuerpo: Productos en hoja rayada con margen rojo de mesero -->
            <div class="libreta-cuerpo">
                <div class="libreta-seccion-titulo">Comanda de cocina</div>
                <ul class="lista-productos-kds">
                    ${productos}
                </ul>
            </div>

            <!-- Pie: Mensaje de estado y botón de acción con borde rasgado -->
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
        const todosLosPedidos = pedidosMemoria
            .filter(p => !comandasDesechadas.has(String(p.id)))
            .map(pedido => ({ ...pedido, status: normalizarEstado(pedido.status || pedido.estado) }));

        const contadorPendientes = document.getElementById("contador-pendientes");
        const contadorPreparacion = document.getElementById("contador-preparacion");
        const contadorListos = document.getElementById("contador-listos");
        if (contadorPendientes) contadorPendientes.textContent = todosLosPedidos.filter(p => p.status === nombresEstados.cola).length;
        if (contadorPreparacion) contadorPreparacion.textContent = todosLosPedidos.filter(p => p.status === nombresEstados.preparacion).length;
        if (contadorListos) contadorListos.textContent = todosLosPedidos.filter(p => p.status === nombresEstados.listo).length;

        const pedidosActivos = todosLosPedidos.filter(
            pedido => !pedido.comandaRetirada && !comandasRetiradas.has(String(pedido.id)) && ![nombresEstados.entregado, nombresEstados.cancelado].includes(pedido.status)
        );

        renderizarFiltrosMesas(pedidosActivos);

        const pedidosFiltrados = (filtroMesaActual === "todas")
            ? pedidosActivos
            : pedidosActivos.filter(p => String(extraerNumeroMesa(p)) === String(filtroMesaActual));

        pedidosFiltrados.sort((a, b) => {
            const esperaA = minutosEnEspera(a, ahora);
            const esperaB = minutosEnEspera(b, ahora);
            const prioA = esPedidoPrioritario(a, esperaA) ? 1 : 0;
            const prioB = esPedidoPrioritario(b, esperaB) ? 1 : 0;

            if (prioA !== prioB) return prioB - prioA;
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

    function normalizarPedidoApi(p) {
        const idStr = String(p.id_pedido ?? p.id ?? "");
        return {
            ...p,
            id: idStr,
            mesa: p.numero_mesa ?? p.mesa ?? p.id_mesa ?? 1,
            status: normalizarEstado(p.estado || p.status),
            receivedAt: p.fecha_hora || p.receivedAt || p.createdAt || new Date().toISOString(),
            items: Array.isArray(p.items) ? p.items : []
        };
    }

    async function sincronizarDesdeServidor() {
        if (!window.RequestyAPI || typeof window.RequestyAPI.get !== "function") {
            if (!pedidosMemoria.length) {
                pedidosMemoria = pedidosDemoKds.map(p => ({ ...p }));
            }
            mostrarPedidos();
            return;
        }
        try {
            const res = await window.RequestyAPI.get("api/pedidos");
            const listaApi = Array.isArray(res?.pedidos) ? res.pedidos : [];
            const activosApi = listaApi
                .filter(p => !comandasDesechadas.has(String(p.id_pedido ?? p.id)))
                .map(normalizarPedidoApi);

            if (activosApi.length > 0) {
                const demosActivos = pedidosMemoria.filter(p => p.esDemo && !comandasDesechadas.has(String(p.id)));
                pedidosMemoria = [...activosApi, ...demosActivos];
            } else if (!pedidosMemoria.length) {
                pedidosMemoria = pedidosDemoKds.map(p => ({ ...p }));
            }
            mostrarError("");
            mostrarPedidos();
        } catch (err) {
            if (!pedidosMemoria.length) {
                pedidosMemoria = pedidosDemoKds.map(p => ({ ...p }));
            }
            mostrarPedidos();
        }
    }

    function arrugarYTirarComanda(id, nuevoEstado = nombresEstados.listo) {
        const comandaEl = document.querySelector(`.libreta-comanda[data-pedido-id="${CSS.escape(String(id))}"]`);
        reproducirSonidoArrugar();

        if (comandaEl) {
            comandaEl.classList.add("animacion-arrugar");
        }

        window.setTimeout(async () => {
            const pedido = pedidosMemoria.find(actual => String(actual.id) === String(id));
            if (pedido) {
                pedido.status = nuevoEstado;
                pedido.comandaRetirada = true;
                comandasRetiradas.add(String(id));

                if (!pedido.esDemo && window.RequestyAPI && Number.isFinite(Number(id))) {
                    try {
                        await window.RequestyAPI.post("api/pedidos", {
                            id_pedido: Number(id),
                            estado: estadoApiDesdeNombre(nuevoEstado)
                        });
                    } catch (e) {
                        // Continuar flujo visual
                    }
                }
            }
            window.dispatchEvent(new CustomEvent(eventoPedidos, { detail: pedidosMemoria }));
            mostrarPedidos();
        }, 550);
    }

    function desecharComanda(id) {
        const comandaEl = document.querySelector(`.libreta-comanda[data-pedido-id="${CSS.escape(String(id))}"]`);
        reproducirSonidoArrugar();

        if (comandaEl) {
            comandaEl.classList.add("animacion-arrugar");
        }

        window.setTimeout(async () => {
            const pedido = pedidosMemoria.find(actual => String(actual.id) === String(id));
            comandasDesechadas.add(String(id));
            pedidosMemoria = pedidosMemoria.filter(actual => String(actual.id) !== String(id));

            if (pedido && !pedido.esDemo && window.RequestyAPI && Number.isFinite(Number(id))) {
                try {
                    await window.RequestyAPI.post("api/pedidos", {
                        id_pedido: Number(id),
                        estado: "LISTO"
                    });
                } catch (e) {
                    // Continuar flujo visual
                }
            }

            window.dispatchEvent(new CustomEvent(eventoPedidos, { detail: pedidosMemoria }));
            mostrarPedidos();
        }, 550);
    }

    async function cambiarEstadoSimple(id, nuevoEstado) {
        const pedido = pedidosMemoria.find(actual => String(actual.id) === String(id));
        if (!pedido) return;

        pedido.status = normalizarEstado(nuevoEstado);

        if (!pedido.esDemo && window.RequestyAPI && Number.isFinite(Number(id))) {
            try {
                await window.RequestyAPI.post("api/pedidos", {
                    id_pedido: Number(id),
                    estado: estadoApiDesdeNombre(nuevoEstado)
                });
            } catch (err) {
                mostrarError(err.message || "No se pudo actualizar el estado en el servidor.");
            }
        }

        window.dispatchEvent(new CustomEvent(eventoPedidos, { detail: pedidosMemoria }));
        mostrarPedidos();
    }

    const riel = document.getElementById("contenedor-pedidos");
    document.getElementById("btnRielPrev")?.addEventListener("click", () => {
        if (riel) riel.scrollBy({ left: -320, behavior: "smooth" });
    });
    document.getElementById("btnRielNext")?.addEventListener("click", () => {
        if (riel) riel.scrollBy({ left: 320, behavior: "smooth" });
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

    document.getElementById("btnRestaurarDemo")?.addEventListener("click", () => {
        comandasRetiradas.clear();
        comandasDesechadas.clear();
        const noDemos = pedidosMemoria.filter(p => !p.esDemo);
        const nuevosDemos = pedidosDemoKds.map(p => ({ ...p, comandaRetirada: false }));
        pedidosMemoria = [...noDemos, ...nuevosDemos];
        mostrarPedidos();
    });

    sincronizarDesdeServidor();
    window.setInterval(sincronizarDesdeServidor, 10000);
})();
