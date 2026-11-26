const CLAVE_MESAS = 'requestyMesas';
const CLAVE_COMBINACIONES = 'requestyMesasCombinadas';
const CLAVE_ALERTA_OCUPACION = 'requestyAlertaOcupacion';
const estadosValidos = new Set(['libre', 'ocupada', 'reservada', 'inactiva']);

let mesas = [];
let combinaciones = [];
let filtroActual = 'all';
let modoCombinacion = false;
let mesasSeleccionadas = new Set();
let unidadSeleccionada = null;
let alertaOcupacionActiva = null;

const elemento = id => document.getElementById(id);

document.addEventListener('DOMContentLoaded', iniciarMesas);

function iniciarMesas() {
    const iniciales = window.requestyMesasIniciales;
    if (!Array.isArray(iniciales)) {
        mostrarToast('No se pudieron cargar las mesas del servidor.', 'error');
        return;
    }

    const mesasLocales = leerMesasLocales();
    mesas = iniciales.map(mesa => normalizarMesaServidor(mesa, mesasLocales));
    combinaciones = leerCombinaciones();
    reconciliarCombinaciones();
    agregarEventos();
    guardarMesasLocales();
    renderizarMesas();
    actualizarStats();
}

function normalizarEstado(estado) {
    const valor = String(estado ?? '').trim().toLocaleLowerCase('es');
    if (['libre', 'disponible'].includes(valor)) return 'libre';
    if (['ocupada', 'ocupado'].includes(valor)) return 'ocupada';
    if (valor === 'reservada') return 'reservada';
    return 'inactiva';
}

function normalizarMesaServidor(mesa, locales) {
    const numero = Number(mesa.numero_mesa ?? mesa.numero ?? mesa.id);
    const local = locales.find(actual => Number(actual.numero ?? actual.numero_mesa ?? actual.id) === numero);
    const estado = normalizarEstado(mesa.estado);
    const conservarDatosDeOcupacion = estado === 'ocupada' && normalizarEstado(local?.estado) === 'ocupada';
    const conservarFechaReserva = estado === 'reservada' && normalizarEstado(local?.estado) === 'reservada';

    return {
        id: Number(mesa.id_mesa ?? mesa.id ?? numero),
        numero,
        capacidad: Math.max(1, Number(mesa.capacidad) || 4),
        estado,
        clientes: conservarDatosDeOcupacion ? Math.max(0, Number(local?.clientes) || 0) : 0,
        pedidos: conservarDatosDeOcupacion ? Math.max(0, Number(local?.pedidos) || 0) : 0,
        tiempo: conservarDatosDeOcupacion ? Math.max(0, Number(local?.tiempo) || 0) : 0,
        ocupadaDesde: conservarDatosDeOcupacion ? Number(local?.ocupadaDesde) || 0 : 0,
        clienteNombre: conservarDatosDeOcupacion ? String(local?.clienteNombre || '') : '',
        clienteCorreo: conservarDatosDeOcupacion ? String(local?.clienteCorreo || '') : '',
        fechaReserva: conservarFechaReserva ? normalizarFechaReserva(local?.fechaReserva) : ''
    };
}

function leerMesasLocales() {
    try {
        const guardadas = JSON.parse(localStorage.getItem(CLAVE_MESAS) || '[]');
        if (!Array.isArray(guardadas)) throw new Error('El formato guardado no es una lista.');
        return guardadas;
    } catch (error) {
        mostrarToast('No se pudo leer el estado local de las mesas. Se usaran los datos del servidor.', 'error');
        return [];
    }
}

function leerCombinaciones() {
    try {
        const guardadas = JSON.parse(localStorage.getItem(CLAVE_COMBINACIONES) || '[]');
        if (!Array.isArray(guardadas)) throw new Error('El formato guardado no es una lista.');
        return guardadas
            .filter(grupo => Array.isArray(grupo))
            .map(grupo => [...new Set(grupo.map(Number).filter(Number.isInteger))])
            .filter(grupo => grupo.length > 1);
    } catch (error) {
        mostrarToast('No se pudieron recuperar las combinaciones guardadas.', 'error');
        return [];
    }
}

function reconciliarCombinaciones() {
    const numerosExistentes = new Set(mesas.map(mesa => mesa.numero));
    combinaciones = combinaciones
        .map(grupo => grupo.filter(numero => numerosExistentes.has(numero)))
        .filter(grupo => grupo.length > 1);
}

function guardarMesasLocales() {
    try {
        localStorage.setItem(CLAVE_MESAS, JSON.stringify(mesas));
        window.dispatchEvent(new CustomEvent('requesty-mesas-actualizadas', { detail: mesas }));
        return true;
    } catch (error) {
        mostrarToast('No se pudo sincronizar el estado local de las mesas.', 'error');
        return false;
    }
}

function guardarCombinaciones() {
    try {
        localStorage.setItem(CLAVE_COMBINACIONES, JSON.stringify(combinaciones));
        return true;
    } catch (error) {
        mostrarToast('No se pudieron guardar las combinaciones de mesas.', 'error');
        return false;
    }
}

function agregarEventos() {
    document.querySelectorAll('.filter-btn').forEach(boton => {
        boton.addEventListener('click', () => {
            filtroActual = boton.dataset.filter || 'all';
            document.querySelectorAll('.filter-btn').forEach(actual => {
                const activo = actual === boton;
                actual.classList.toggle('active', activo);
                actual.setAttribute('aria-pressed', String(activo));
            });
            renderizarMesas();
        });
    });

    elemento('btnModoCombinar').addEventListener('click', activarModoCombinacion);
    elemento('btnConfirmarCombinacion').addEventListener('click', combinarMesasSeleccionadas);
    elemento('btnCancelarCombinacion').addEventListener('click', cancelarModoCombinacion);
    elemento('btnCerrarModalMesa').addEventListener('click', cerrarModalMesa);
    elemento('btnCancelarModalMesa').addEventListener('click', cerrarModalMesa);
    elemento('modalOverlay').addEventListener('click', cerrarModalMesa);
    elemento('btnAccion').addEventListener('click', accionMesa);
    elemento('selectEstadoMesa').addEventListener('change', actualizarCamposEstadoMesa);
    elemento('inputClientes').addEventListener('input', limpiarErrorAsignacion);
    elemento('inputCorreoCliente').addEventListener('input', limpiarErrorAsignacion);
    elemento('inputFechaReserva').addEventListener('input', limpiarErrorReserva);

    if (window.requestyPuedeCrearMesa) {
        elemento('btnAgregarMesa').addEventListener('click', abrirModalNuevaMesa);
        elemento('btnCerrarNuevaMesa').addEventListener('click', cerrarModalNuevaMesa);
        elemento('btnCancelarNuevaMesa').addEventListener('click', cerrarModalNuevaMesa);
        elemento('modalNuevaMesaOverlay').addEventListener('click', cerrarModalNuevaMesa);
        elemento('formNuevaMesa').addEventListener('submit', crearMesa);
    }

    document.addEventListener('keydown', evento => {
        if (evento.key === 'Escape') {
            cerrarModalMesa();
            cerrarModalNuevaMesa();
            if (modoCombinacion) cancelarModoCombinacion();
        }
    });

    window.addEventListener('storage', evento => {
        if (evento.key === CLAVE_MESAS && evento.newValue) {
            actualizarDesdeOtraPestana(evento.newValue);
        }
        if (evento.key === CLAVE_COMBINACIONES) {
            combinaciones = leerCombinaciones();
            reconciliarCombinaciones();
            renderizarMesas();
        }
    });

    window.addEventListener('requesty-mesas-actualizadas', evento => {
        if (Array.isArray(evento.detail)) actualizarDatosLocales(evento.detail);
    });
}

function actualizarDatosLocales(registros) {
    const porNumero = new Map(registros.map(mesa => [
        Number(mesa.numero ?? mesa.numero_mesa ?? mesa.id),
        mesa
    ]));
    mesas = mesas.map(mesa => {
        const registro = porNumero.get(mesa.numero);
        if (!registro) return mesa;
        return {
            ...mesa,
            pedidos: Math.max(0, Number(registro.pedidos) || 0),
            clientes: mesa.estado === 'ocupada' ? Math.max(0, Number(registro.clientes) || mesa.clientes) : 0,
            clienteNombre: mesa.estado === 'ocupada' ? String(registro.clienteNombre || mesa.clienteNombre) : '',
            clienteCorreo: mesa.estado === 'ocupada' ? String(registro.clienteCorreo || mesa.clienteCorreo) : ''
        };
    });
    renderizarMesas();
    actualizarStats();
}

function actualizarDesdeOtraPestana(valor) {
    try {
        const registros = JSON.parse(valor);
        if (!Array.isArray(registros)) throw new Error('La lista recibida no es valida.');
        const porNumero = new Map(registros.map(mesa => [
            Number(mesa.numero ?? mesa.numero_mesa ?? mesa.id),
            mesa
        ]));
        mesas = mesas.map(mesa => {
            const registro = porNumero.get(mesa.numero);
            return registro ? {
                ...mesa,
                estado: normalizarEstado(registro.estado),
                clientes: Math.max(0, Number(registro.clientes) || 0),
                pedidos: Math.max(0, Number(registro.pedidos) || 0),
                tiempo: Math.max(0, Number(registro.tiempo) || 0),
                ocupadaDesde: Number(registro.ocupadaDesde) || 0,
                clienteNombre: String(registro.clienteNombre || ''),
                clienteCorreo: String(registro.clienteCorreo || ''),
                fechaReserva: normalizarFechaReserva(registro.fechaReserva)
            } : mesa;
        });
        renderizarMesas();
        actualizarStats();
    } catch (error) {
        mostrarToast('No se pudieron actualizar las mesas desde otra pestana.', 'error');
    }
}

function obtenerUnidades() {
    const porNumero = new Map(mesas.map(mesa => [mesa.numero, mesa]));
    const usadas = new Set();
    const unidades = [];

    combinaciones.forEach(grupo => {
        const miembros = grupo.map(numero => porNumero.get(numero)).filter(Boolean);
        if (miembros.length < 2) return;
        miembros.forEach(mesa => usadas.add(mesa.numero));
        unidades.push(crearUnidad(miembros));
    });

    mesas.forEach(mesa => {
        if (!usadas.has(mesa.numero)) unidades.push(crearUnidad([mesa]));
    });

    return unidades.sort((a, b) => a.miembros[0].numero - b.miembros[0].numero);
}

function crearUnidad(miembros) {
    const numeros = miembros.map(mesa => mesa.numero).sort((a, b) => a - b);
    const estados = new Set(miembros.map(mesa => mesa.estado));
    const estado = estados.size === 1 ? miembros[0].estado : 'mixta';
    const etiqueta = numeros.length > 1 ? numeros.join(' + ') : String(numeros[0]);
    const inicioOcupacion = Math.min(...miembros.map(mesa => mesa.ocupadaDesde || Infinity));
    const tiempoTranscurrido = Number.isFinite(inicioOcupacion)
        ? Math.floor((Date.now() - inicioOcupacion) / 1000)
        : 0;
    const fechaReserva = miembros.map(mesa => mesa.fechaReserva).find(Boolean) || '';
    return {
        key: numeros.join('-'),
        etiqueta,
        nombre: numeros.length > 1 ? `Mesas ${etiqueta}` : `Mesa ${etiqueta}`,
        miembros,
        capacidad: miembros.reduce((total, mesa) => total + mesa.capacidad, 0),
        estado,
        clientes: miembros.reduce((total, mesa) => total + mesa.clientes, 0),
        pedidos: miembros.reduce((total, mesa) => total + mesa.pedidos, 0),
        tiempo: Math.max(tiempoTranscurrido, ...miembros.map(mesa => mesa.tiempo)),
        ocupadaDesde: inicioOcupacion,
        fechaReserva,
        clienteNombre: [...new Set(miembros.map(mesa => mesa.clienteNombre).filter(Boolean))].join(', '),
        clienteCorreo: [...new Set(miembros.map(mesa => mesa.clienteCorreo).filter(Boolean))].join(', ')
    };
}

function obtenerUnidadesFiltradas() {
    const unidades = obtenerUnidades();
    if (filtroActual === 'libres') return unidades.filter(unidad => unidad.estado === 'libre');
    if (filtroActual === 'ocupadas') return unidades.filter(unidad => unidad.estado === 'ocupada');
    return unidades;
}

function renderizarMesas() {
    const grid = elemento('mesasGrid');
    grid.replaceChildren();
    grid.classList.toggle('modo-combinacion', modoCombinacion);

    obtenerUnidadesFiltradas().forEach(unidad => {
        const tarjeta = document.createElement('article');
        tarjeta.className = `mesa-card ${estadosValidos.has(unidad.estado) ? unidad.estado : 'inactiva'}`;
        if (unidad.estado === 'mixta') tarjeta.classList.add('mesa-mixta');
        if (mesasSeleccionadas.has(unidad.key)) tarjeta.classList.add('seleccionada');

        const botonMesa = document.createElement('button');
        botonMesa.type = 'button';
        botonMesa.className = 'mesa-card-main';
        botonMesa.setAttribute('aria-pressed', String(mesasSeleccionadas.has(unidad.key)));
        botonMesa.setAttribute('aria-label', `${unidad.nombre}, ${unidad.capacidad} puestos, ${etiquetaEstado(unidad.estado)}`);
        botonMesa.addEventListener('click', () => {
            if (modoCombinacion) {
                alternarSeleccion(unidad);
            } else {
                abrirModalMesa(unidad);
            }
        });

        const escenario = crearEscenarioMesa(unidad);
        const nombre = document.createElement('strong');
        nombre.className = 'mesa-number';
        nombre.textContent = unidad.nombre;
        const capacidad = document.createElement('span');
        capacidad.className = 'mesa-capacity';
        capacidad.textContent = `${unidad.capacidad} ${unidad.capacidad === 1 ? 'puesto' : 'puestos'}`;
        const estado = document.createElement('span');
        estado.className = 'mesa-status';
        estado.textContent = etiquetaEstado(unidad.estado);

        botonMesa.append(escenario, nombre, capacidad, estado);
        if (unidad.estado === 'reservada') {
            const fechaReserva = document.createElement('span');
            fechaReserva.className = 'mesa-reservation-date';
            fechaReserva.textContent = unidad.fechaReserva
                ? `Reserva: ${formatearFechaReserva(unidad.fechaReserva)}`
                : 'Fecha pendiente';
            botonMesa.appendChild(fechaReserva);
        }
        tarjeta.appendChild(botonMesa);

        if (unidad.miembros.length > 1) {
            const separar = document.createElement('button');
            separar.type = 'button';
            separar.className = 'mesa-separar';
            separar.textContent = 'Separar';
            separar.setAttribute('aria-label', 'Separar mesas combinadas');
            separar.addEventListener('click', () => separarUnidad(unidad));
            tarjeta.appendChild(separar);
        }
        grid.appendChild(tarjeta);
    });

    actualizarControlesCombinacion();
}

function crearEscenarioMesa(unidad) {
    const escenario = document.createElement('div');
    const capacidadClase = Math.min(12, Math.max(1, unidad.capacidad));
    const forma = unidad.capacidad <= 4 ? 'circular' : 'rectangular';
    escenario.className = `mesa-table-stage capacidad-${capacidadClase} forma-${forma}`;
    escenario.setAttribute('aria-hidden', 'true');

    obtenerPosicionesSillas(unidad.capacidad).forEach(posicion => {
        const silla = document.createElement('i');
        silla.className = 'fa-solid fa-chair mesa-seat';
        silla.style.setProperty('--seat-x', `${posicion.x}%`);
        silla.style.setProperty('--seat-y', `${posicion.y}%`);
        escenario.appendChild(silla);
    });

    const superficie = document.createElement('div');
    superficie.className = 'mesa-table-surface';
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
    if (estado === 'libre') return 'Disponible';
    if (estado === 'ocupada') return 'Ocupada';
    if (estado === 'reservada') return 'Reservada';
    if (estado === 'mixta') return 'Estado mixto';
    return 'Inactiva';
}

function actualizarStats() {
    const activas = mesas.filter(mesa => mesa.estado !== 'inactiva');
    const disponibles = activas.filter(mesa => mesa.estado === 'libre').length;
    const ocupadas = activas.filter(mesa => mesa.estado === 'ocupada').length;
    const porcentaje = activas.length ? Math.round((ocupadas / activas.length) * 100) : 0;

    elemento('mesasDisponibles').textContent = String(disponibles);
    elemento('mesasOcupadas').textContent = String(ocupadas);
    elemento('tasaOcupacion').textContent = `${porcentaje}%`;
    evaluarAlertaOcupacion(ocupadas, activas.length, porcentaje);
}

function evaluarAlertaOcupacion(ocupadas, total, porcentaje) {
    if (!total || porcentaje <= 80) {
        if (alertaOcupacionActiva) alertaOcupacionActiva.remove();
        alertaOcupacionActiva = null;
        try {
            localStorage.removeItem(CLAVE_ALERTA_OCUPACION);
        } catch (error) {
            mostrarToast('No se pudo guardar el estado del aviso de ocupacion.', 'error');
        }
        return;
    }

    let descartada = null;
    try {
        const valor = localStorage.getItem(CLAVE_ALERTA_OCUPACION);
        descartada = valor === null ? null : Number(valor);
    } catch (error) {
        mostrarToast('No se pudo consultar el estado del aviso de ocupacion.', 'error');
    }
    if (descartada === ocupadas && !alertaOcupacionActiva) return;

    const lleno = porcentaje === 100;
    const mensaje = lleno ? 'El restaurante está lleno.' : 'La ocupación del restaurante superó el 80%.';
    if (alertaOcupacionActiva?.isConnected) {
        alertaOcupacionActiva.querySelector('.toast-message').textContent = mensaje;
        alertaOcupacionActiva.classList.toggle('toast-lleno', lleno);
        alertaOcupacionActiva.classList.toggle('toast-alerta', !lleno);
        return;
    }
    alertaOcupacionActiva = mostrarToast(mensaje, lleno ? 'lleno' : 'alerta', { alerta: true, persistente: true });
}

function mostrarToast(mensaje, tipo = 'success', opciones = {}) {
    const lista = elemento('toastList');
    if (!lista) return null;

    const toast = document.createElement('li');
    toast.className = `toast toast-${tipo}`;
    if (opciones.alerta) toast.dataset.alerta = 'ocupacion';

    const texto = document.createElement('span');
    texto.className = 'toast-message';
    texto.textContent = mensaje;
    const cerrar = document.createElement('button');
    cerrar.type = 'button';
    cerrar.className = 'toast-close';
    cerrar.setAttribute('aria-label', 'Cerrar notificación');
    cerrar.textContent = '×';
    cerrar.addEventListener('click', () => {
        if (toast === alertaOcupacionActiva) {
            try {
                const ocupadas = mesas.filter(mesa => mesa.estado === 'ocupada').length;
                localStorage.setItem(CLAVE_ALERTA_OCUPACION, String(ocupadas));
            } catch (error) {
                mostrarToast('No se pudo guardar el cierre del aviso.', 'error');
            }
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
    elemento('btnModoCombinar').setAttribute('aria-pressed', 'true');
    elemento('btnModoCombinar').hidden = true;
    elemento('btnCancelarCombinacion').hidden = false;
    elemento('mesasSelectionHint').hidden = false;
    renderizarMesas();
}

function alternarSeleccion(unidad) {
    if (unidad.estado !== 'libre') {
        mostrarToast('Solo se pueden combinar mesas disponibles.', 'error');
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
    const boton = elemento('btnConfirmarCombinacion');
    const cantidad = mesasSeleccionadas.size;
    boton.hidden = !modoCombinacion;
    boton.disabled = cantidad < 2;
    elemento('mesasSelectionHint').textContent = modoCombinacion
        ? `Selecciona dos o más mesas disponibles. ${cantidad} seleccionada(s).`
        : '';
}

function combinarMesasSeleccionadas() {
    const unidades = obtenerUnidades().filter(unidad => mesasSeleccionadas.has(unidad.key));
    if (unidades.length < 2 || unidades.some(unidad => unidad.estado !== 'libre')) {
        mostrarToast('Selecciona al menos dos mesas disponibles.', 'error');
        return;
    }
    const numeros = [...new Set(unidades.flatMap(unidad => unidad.miembros.map(mesa => mesa.numero)))]
        .sort((a, b) => a - b);
    const conjunto = new Set(numeros);
    combinaciones = combinaciones.filter(grupo => !grupo.some(numero => conjunto.has(numero)));
    combinaciones.push(numeros);
    if (!guardarCombinaciones()) return;

    cancelarModoCombinacion();
    renderizarMesas();
    mostrarToast(`Mesas ${numeros.join(' + ')} combinadas (${unidades.reduce((total, unidad) => total + unidad.capacidad, 0)} puestos).`);
}

function cancelarModoCombinacion() {
    modoCombinacion = false;
    mesasSeleccionadas.clear();
    if (elemento('btnModoCombinar')) {
        elemento('btnModoCombinar').hidden = false;
        elemento('btnModoCombinar').setAttribute('aria-pressed', 'false');
    }
    if (elemento('btnCancelarCombinacion')) elemento('btnCancelarCombinacion').hidden = true;
    if (elemento('mesasSelectionHint')) elemento('mesasSelectionHint').hidden = true;
    if (elemento('btnConfirmarCombinacion')) elemento('btnConfirmarCombinacion').hidden = true;
    renderizarMesas();
}

function separarUnidad(unidad) {
    const numeros = new Set(unidad.miembros.map(mesa => mesa.numero));
    combinaciones = combinaciones.filter(grupo => !grupo.every(numero => numeros.has(numero)));
    if (!guardarCombinaciones()) return;
    renderizarMesas();
    mostrarToast('Las mesas volvieron a estar separadas.');
}

function abrirModalMesa(unidad) {
    unidadSeleccionada = unidad;
    const titulo = unidad.nombre;
    const cliente = [...new Set(unidad.miembros.map(mesa => mesa.clienteNombre).filter(Boolean))].join(', ');
    const correo = [...new Set(unidad.miembros.map(mesa => mesa.clienteCorreo).filter(Boolean))].join(', ');
    const datosCliente = [cliente, correo].filter(Boolean).join(' · ');

    elemento('modalTitle').textContent = titulo;
    elemento('modalCapacidad').textContent = `${unidad.capacidad} puestos`;
    elemento('modalClientes').textContent = unidad.clientes ? String(unidad.clientes) : '-';
    elemento('modalTiempo').textContent = unidad.tiempo ? formatearTiempo(unidad.tiempo) : '-';
    elemento('modalPedidos').textContent = String(unidad.pedidos);
    elemento('modalClienteAsignado').textContent = datosCliente || '-';
    elemento('clienteAsignadoGroup').hidden = !datosCliente;
    elemento('fechaReservaActualGroup').hidden = unidad.estado !== 'reservada';
    elemento('fechaReservaActual').textContent = unidad.fechaReserva
        ? formatearFechaReserva(unidad.fechaReserva)
        : 'Fecha pendiente';
    elemento('asignarInputGroup').hidden = true;
    elemento('reservaInputGroup').hidden = true;
    elemento('errorClientes').hidden = true;
    elemento('errorClientes').textContent = '';
    elemento('errorReserva').hidden = true;
    elemento('errorReserva').textContent = '';
    elemento('errorEstadoMesa').hidden = true;
    elemento('errorEstadoMesa').textContent = '';
    elemento('btnAccion').hidden = false;
    elemento('btnAccion').disabled = false;
    elemento('modalMesa').classList.toggle('mesa-modal-ocupada', unidad.estado === 'ocupada');
    elemento('modalMesa').classList.toggle('mesa-modal-libre', unidad.estado === 'libre');

    const selectEstado = elemento('selectEstadoMesa');
    selectEstado.value = estadosValidos.has(unidad.estado) ? unidad.estado : '';
    const inputClientes = elemento('inputClientes');
    inputClientes.max = String(unidad.capacidad);
    inputClientes.value = String(Math.max(1, unidad.clientes || 1));
    elemento('inputNombreCliente').value = unidad.clienteNombre || '';
    elemento('inputCorreoCliente').value = unidad.clienteCorreo || '';
    actualizarMinimoFechaReserva();
    elemento('inputFechaReserva').value = unidad.fechaReserva
        ? fechaReservaParaInput(unidad.fechaReserva)
        : '';
    actualizarCamposEstadoMesa();
    abrirDialogo('modalMesa', 'modalOverlay');
    if (selectEstado.value === 'ocupada') inputClientes.focus();
    else if (selectEstado.value === 'reservada') elemento('inputFechaReserva').focus();
    else selectEstado.focus();
}

function abrirDialogo(modalId, overlayId) {
    const modal = elemento(modalId);
    const overlay = elemento(overlayId);
    modal.hidden = false;
    overlay.hidden = false;
    document.body.classList.add('modal-abierto');
}

function cerrarModalMesa() {
    if (!elemento('modalMesa') || elemento('modalMesa').hidden) return;
    elemento('modalMesa').hidden = true;
    elemento('modalOverlay').hidden = true;
    unidadSeleccionada = null;
    actualizarEstadoScroll();
}

function limpiarErrorAsignacion() {
    elemento('errorClientes').hidden = true;
    elemento('errorClientes').textContent = '';
}

function limpiarErrorReserva() {
    elemento('errorReserva').hidden = true;
    elemento('errorReserva').textContent = '';
}

function actualizarCamposEstadoMesa() {
    if (!unidadSeleccionada) return;
    const estado = elemento('selectEstadoMesa').value;
    elemento('errorClientes').hidden = true;
    elemento('errorReserva').hidden = true;
    elemento('errorEstadoMesa').hidden = true;
    elemento('asignarInputGroup').hidden = estado !== 'ocupada';
    const tieneClienteAsignado = Boolean(unidadSeleccionada.clienteNombre || unidadSeleccionada.clienteCorreo);
    elemento('clienteAsignadoGroup').hidden = !tieneClienteAsignado || estado !== 'ocupada';
    elemento('reservaInputGroup').hidden = estado !== 'reservada';
    elemento('fechaReservaActualGroup').hidden = unidadSeleccionada.estado !== 'reservada';

    const boton = elemento('btnAccion');
    boton.hidden = !estado;
    if (estado === 'ocupada') {
        boton.textContent = unidadSeleccionada.estado === 'ocupada' ? 'Guardar cambios' : 'Asignar mesa';
    } else if (estado === 'reservada') {
        boton.textContent = unidadSeleccionada.estado === 'reservada' ? 'Guardar reserva' : 'Reservar mesa';
    } else if (estado === 'libre') {
        boton.textContent = unidadSeleccionada.estado === 'libre' ? 'Guardar estado' : 'Dejar disponible';
    } else if (estado === 'inactiva') {
        boton.textContent = 'Desactivar mesa';
    }
}

async function accionMesa() {
    if (!unidadSeleccionada) return;
    const estado = elemento('selectEstadoMesa').value;
    const error = estado === 'ocupada'
        ? elemento('errorClientes')
        : estado === 'reservada'
            ? elemento('errorReserva')
            : elemento('errorEstadoMesa');
    error.hidden = true;
    error.textContent = '';

    if (estado === 'ocupada') {
        const cantidad = Number(elemento('inputClientes').value);
        const correo = elemento('inputCorreoCliente').value.trim().toLocaleLowerCase('es');
        if (!Number.isInteger(cantidad) || cantidad < 1 || cantidad > unidadSeleccionada.capacidad) {
            error.textContent = `Ingresa una cantidad entre 1 y ${unidadSeleccionada.capacidad}.`;
            error.hidden = false;
            return;
        }
        if (correo && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
            error.textContent = 'El correo ingresado no tiene un formato válido.';
            error.hidden = false;
            return;
        }
        await guardarEstadoMesa(unidadSeleccionada, estado, {
            clientes: cantidad,
            nombre: elemento('inputNombreCliente').value.trim(),
            correo
        });
        return;
    }
    let fechaReserva = '';
    if (estado === 'reservada') {
        const entradaFecha = elemento('inputFechaReserva').value;
        const fechaMinima = actualizarMinimoFechaReserva();
        const fechaSeleccionada = new Date(entradaFecha);
        fechaReserva = normalizarFechaReserva(entradaFecha);
        if (!fechaReserva || fechaSeleccionada.getTime() < fechaMinima.getTime()) {
            error.textContent = 'Selecciona una fecha y hora actuales o futuras para la reserva.';
            error.hidden = false;
            return;
        }
    } else if (!['libre', 'inactiva'].includes(estado)) {
        elemento('errorEstadoMesa').textContent = 'Selecciona un estado válido.';
        elemento('errorEstadoMesa').hidden = false;
        return;
    }
    await guardarEstadoMesa(unidadSeleccionada, estado, { fechaReserva });
}

async function guardarEstadoMesa(unidad, estado, datos = {}) {
    const estadosApi = {
        libre: 'DISPONIBLE',
        ocupada: 'OCUPADA',
        reservada: 'RESERVADA',
        inactiva: 'INACTIVA'
    };
    const boton = elemento('btnAccion');
    boton.disabled = true;
    try {
        await actualizarEstadoServidor(unidad.miembros.map(mesa => mesa.numero), estadosApi[estado]);
        let clientesRestantes = datos.clientes || 0;
        const ahora = Date.now();
        unidad.miembros.forEach(mesa => {
            mesa.estado = estado;
            if (estado === 'ocupada') {
                mesa.clientes = Math.min(mesa.capacidad, clientesRestantes);
                clientesRestantes -= mesa.clientes;
                mesa.pedidos = unidad.estado === 'ocupada' ? mesa.pedidos : 0;
                mesa.tiempo = unidad.estado === 'ocupada' ? mesa.tiempo : 0;
                mesa.ocupadaDesde = mesa.ocupadaDesde || ahora;
                mesa.clienteNombre = datos.nombre || '';
                mesa.clienteCorreo = datos.correo || '';
                mesa.fechaReserva = '';
                return;
            }
            mesa.clientes = 0;
            mesa.pedidos = 0;
            mesa.tiempo = 0;
            mesa.ocupadaDesde = 0;
            mesa.clienteNombre = '';
            mesa.clienteCorreo = '';
            mesa.fechaReserva = estado === 'reservada' ? datos.fechaReserva : '';
        });
        guardarMesasLocales();
        cerrarModalMesa();
        renderizarMesas();
        actualizarStats();
        if (estado === 'ocupada') {
            mostrarToast(`${unidad.nombre} asignada a ${datos.clientes} cliente(s).`);
        } else if (estado === 'reservada') {
            mostrarToast(`${unidad.nombre} reservada para ${formatearFechaReserva(datos.fechaReserva)}.`);
        } else if (estado === 'libre') {
            mostrarToast(`${unidad.nombre} quedó disponible.`);
        } else {
            mostrarToast(`${unidad.nombre} quedó inactiva.`);
        }
    } catch (errorServidor) {
        const error = estado === 'ocupada'
            ? elemento('errorClientes')
            : estado === 'reservada'
                ? elemento('errorReserva')
                : elemento('errorEstadoMesa');
        error.textContent = errorServidor.message || 'No se pudo actualizar el estado de la mesa.';
        error.hidden = false;
        mostrarToast(error.textContent, 'error');
    } finally {
        boton.disabled = false;
    }
}

function normalizarFechaReserva(valor) {
    if (typeof valor !== 'string' || !valor.trim()) return '';
    const fecha = new Date(valor);
    return Number.isNaN(fecha.getTime()) ? '' : fecha.toISOString();
}

function fechaReservaParaInput(valor) {
    const fecha = new Date(valor);
    if (Number.isNaN(fecha.getTime())) return '';
    return fechaHoraLocalParaInput(fecha);
}

function actualizarMinimoFechaReserva() {
    const primerMinutoValido = new Date(Math.ceil(Date.now() / 60000) * 60000);
    elemento('inputFechaReserva').min = fechaHoraLocalParaInput(primerMinutoValido);
    return primerMinutoValido;
}

function fechaHoraLocalParaInput(fecha) {
    const pad = valor => String(valor).padStart(2, '0');
    return `${fecha.getFullYear()}-${pad(fecha.getMonth() + 1)}-${pad(fecha.getDate())}T${pad(fecha.getHours())}:${pad(fecha.getMinutes())}`;
}

function formatearFechaReserva(valor) {
    const fecha = new Date(valor);
    if (Number.isNaN(fecha.getTime())) return 'Fecha pendiente';
    const hora24 = fecha.getHours();
    const hora12 = hora24 % 12 || 12;
    const hora = String(hora12).padStart(2, '0');
    const minutos = String(fecha.getMinutes()).padStart(2, '0');
    const periodo = hora24 < 12 ? 'AM' : 'PM';
    const dia = String(fecha.getDate()).padStart(2, '0');
    const mes = String(fecha.getMonth() + 1).padStart(2, '0');
    return `${hora}:${minutos} ${periodo} ${dia}/${mes}`;
}

async function actualizarEstadoServidor(numeros, estado) {
    const payload = {
        estado,
        ...(numeros.length === 1 ? { numero_mesa: numeros[0] } : { numeros_mesa: numeros })
    };
    const response = await fetch(window.requestyMesaApiUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify(payload)
    });
    const resultado = await leerRespuesta(response);
    if (!resultado.success) throw new Error(resultado.message || 'El servidor no pudo actualizar las mesas.');
}

function leerRespuesta(response) {
    return response.json().catch(() => ({})).then(resultado => {
        if (!response.ok) {
            throw new Error(resultado.message || resultado.error || `Error del servidor (${response.status}).`);
        }
        return resultado;
    });
}

function abrirModalNuevaMesa() {
    elemento('errorNuevaMesa').hidden = true;
    elemento('errorNuevaMesa').textContent = '';
    elemento('capacidadNuevaMesa').value = '4';
    abrirDialogo('modalNuevaMesa', 'modalNuevaMesaOverlay');
    elemento('capacidadNuevaMesa').focus();
}

function cerrarModalNuevaMesa() {
    if (!window.requestyPuedeCrearMesa || elemento('modalNuevaMesa').hidden) return;
    elemento('modalNuevaMesa').hidden = true;
    elemento('modalNuevaMesaOverlay').hidden = true;
    actualizarEstadoScroll();
}

function actualizarEstadoScroll() {
    const hayModalAbierto = !elemento('modalMesa').hidden
        || (window.requestyPuedeCrearMesa && !elemento('modalNuevaMesa').hidden);
    if (!hayModalAbierto) document.body.classList.remove('modal-abierto');
}

async function crearMesa(evento) {
    evento.preventDefault();
    const input = elemento('capacidadNuevaMesa');
    const capacidad = Number(input.value);
    const error = elemento('errorNuevaMesa');
    if (!Number.isInteger(capacidad) || capacidad < 1 || capacidad > 12) {
        error.textContent = 'La capacidad debe estar entre 1 y 12 puestos.';
        error.hidden = false;
        return;
    }

    const boton = elemento('btnGuardarNuevaMesa');
    boton.disabled = true;
    error.hidden = true;
    try {
        const response = await fetch(window.requestyCrearMesaApiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ capacidad })
        });
        const resultado = await leerRespuesta(response);
        if (!resultado.success || !resultado.mesa) {
            throw new Error(resultado.message || 'El servidor no devolvió la mesa creada.');
        }
        const nuevaMesa = normalizarMesaServidor(resultado.mesa, []);
        mesas.push(nuevaMesa);
        mesas.sort((a, b) => a.numero - b.numero);
        guardarMesasLocales();
        cerrarModalNuevaMesa();
        renderizarMesas();
        actualizarStats();
        mostrarToast(`Mesa ${nuevaMesa.numero} agregada.`);
    } catch (errorServidor) {
        error.textContent = errorServidor.message || 'No se pudo guardar la nueva mesa.';
        error.hidden = false;
        mostrarToast(error.textContent, 'error');
    } finally {
        boton.disabled = false;
    }
}

function formatearTiempo(segundos) {
    const minutos = Math.floor(segundos / 60);
    const restantes = segundos % 60;
    return `${minutos}:${String(restantes).padStart(2, '0')}`;
}
