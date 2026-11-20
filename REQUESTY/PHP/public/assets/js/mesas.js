
const claveMesas = 'requestyMesas';
const mesasIniciales = [
    { id: 1, numero: 1, capacidad: 2, estado: 'libre', clientes: 0, pedidos: 0, tiempo: 0 },
    { id: 2, numero: 2, capacidad: 2, estado: 'libre', clientes: 0, pedidos: 0, tiempo: 0 },
    { id: 3, numero: 3, capacidad: 4, estado: 'libre', clientes: 0, pedidos: 0, tiempo: 0 },
    { id: 4, numero: 4, capacidad: 4, estado: 'libre', clientes: 0, pedidos: 0, tiempo: 0 },
    { id: 5, numero: 5, capacidad: 6, estado: 'libre', clientes: 0, pedidos: 0, tiempo: 0 },
    { id: 6, numero: 6, capacidad: 2, estado: 'libre', clientes: 0, pedidos: 0, tiempo: 0 },
    { id: 7, numero: 7, capacidad: 4, estado: 'libre', clientes: 0, pedidos: 0, tiempo: 0 },
    { id: 8, numero: 8, capacidad: 4, estado: 'libre', clientes: 0, pedidos: 0, tiempo: 0 },
    { id: 9, numero: 9, capacidad: 2, estado: 'libre', clientes: 0, pedidos: 0, tiempo: 0 },
    { id: 10, numero: 10, capacidad: 6, estado: 'libre', clientes: 0, pedidos: 0, tiempo: 0 },
    { id: 11, numero: 11, capacidad: 4, estado: 'libre', clientes: 0, pedidos: 0, tiempo: 0 },
    { id: 12, numero: 12, capacidad: 2, estado: 'libre', clientes: 0, pedidos: 0, tiempo: 0 },
    { id: 13, numero: 13, capacidad: 4, estado: 'libre', clientes: 0, pedidos: 0, tiempo: 0 },
    { id: 14, numero: 14, capacidad: 4, estado: 'libre', clientes: 0, pedidos: 0, tiempo: 0 },
    { id: 15, numero: 15, capacidad: 6, estado: 'libre', clientes: 0, pedidos: 0, tiempo: 0 }
];
let mesas = cargarMesas();
let mesaSeleccionada = null;
let filtroActual = 'all';
let confirmandoVaciado = false;

function cargarMesas() {
    try {
        const guardadas = JSON.parse(localStorage.getItem(claveMesas) || 'null');
        if (!Array.isArray(guardadas)) return mesasIniciales;
        return mesasIniciales.map(base => ({
            ...base,
            ...guardadas.find(mesa => Number(mesa.id) === base.id)
        }));
    } catch {
        return mesasIniciales;
    }
}

function guardarMesas() {
    try {
        localStorage.setItem(claveMesas, JSON.stringify(mesas));
        window.dispatchEvent(new CustomEvent('requesty-mesas-actualizadas', { detail: mesas }));
        return true;
    } catch {
        mostrarToast('No se pudo guardar el estado de las mesas.', true);
        return false;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    renderizarMesas(mesas);
    actualizarStats();
    agregarEventosFiltros();
});

function renderizarMesas(mesasAMostrar) {
    const grid = document.getElementById('mesasGrid');
    grid.innerHTML = '';

    mesasAMostrar.forEach(mesa => {
        const mesaElement = document.createElement('div');
        mesaElement.className = `mesa-card ${mesa.estado}`;
        mesaElement.innerHTML = `
            <div class="mesa-icon">${mesa.estado === 'libre' ? '🪑' : '👥'}</div>
            <div class="mesa-number">Mesa ${mesa.numero}</div>
            <div class="mesa-status">${mesa.estado === 'libre' ? 'Libre' : 'Ocupada'}</div>
            ${mesa.clienteNombre ? `<div class="mesa-cliente">${escaparHTML(mesa.clienteNombre)}</div>` : ''}
        `;
        mesaElement.onclick = () => abrirModalMesa(mesa);
        grid.appendChild(mesaElement);
    });
}

function abrirModalMesa(mesa) {
    mesaSeleccionada = mesa;
    confirmandoVaciado = false;

    document.getElementById('modalTitle').textContent = `Mesa ${mesa.numero}`;
    document.getElementById('modalEstado').textContent = mesa.estado === 'libre' ? '✓ Libre' : '✗ Ocupada';
    document.getElementById('modalCapacidad').textContent = `${mesa.capacidad} personas`;
    document.getElementById('modalClientes').textContent = mesa.clientes > 0 ? mesa.clientes : '-';
    document.getElementById('modalTiempo').textContent = mesa.tiempo > 0 ? formatearTiempo(mesa.tiempo) : '-';
    document.getElementById('modalPedidos').textContent = mesa.pedidos > 0 ? mesa.pedidos : '-';
    const clienteAsignado = [mesa.clienteNombre, mesa.clienteCorreo].filter(Boolean).join(' · ');
    document.getElementById('clienteAsignadoGroup').hidden = !clienteAsignado;
    document.getElementById('modalClienteAsignado').textContent = clienteAsignado || '-';

    const btnAccion = document.getElementById('btnAccion');
    const inputGroup = document.getElementById('asignarInputGroup');
    const errorSpan = document.getElementById('errorClientes');
    errorSpan.style.display = 'none';

    if (mesa.estado === 'libre') {
        btnAccion.textContent = '✓ Asignar Clientes';
        btnAccion.style.backgroundColor = '#10b981';
        inputGroup.style.display = 'flex';
        const input = document.getElementById('inputClientes');
        input.max = mesa.capacidad;
        input.value = 1;
        document.getElementById('inputNombreCliente').value = '';
        document.getElementById('inputCorreoCliente').value = '';
    } else {
        btnAccion.textContent = '✗ Vaciar Mesa';
        btnAccion.style.backgroundColor = '#ef4444';
        inputGroup.style.display = 'none';
    }
    document.getElementById('modalMesa').classList.add('active');
    document.getElementById('modalOverlay').classList.add('active');
}

function cerrarModal() {
    document.getElementById('modalMesa').classList.remove('active');
    document.getElementById('modalOverlay').classList.remove('active');
    mesaSeleccionada = null;
    confirmandoVaciado = false;
}

function accionMesa() {
    if (!mesaSeleccionada) {
        mostrarToast('Error: selecciona una mesa primero', true);
        return;
    }

    if (mesaSeleccionada.estado === 'libre') {
        asignarMesa();
    } else {
        vaciarMesa();
    }
}

function asignarMesa() {
    const mesa = mesaSeleccionada;
    const input = document.getElementById('inputClientes');
    const errorSpan = document.getElementById('errorClientes');
    const numClientes = parseInt(input.value, 10);
    if (isNaN(numClientes) || numClientes <= 0 || numClientes > mesa.capacidad) {
        errorSpan.textContent = `Ingresa un número entre 1 y ${mesa.capacidad}`;
        errorSpan.style.display = 'block';
        return;
    }
    const clienteCorreo = document.getElementById('inputCorreoCliente').value.trim().toLocaleLowerCase('es');
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(clienteCorreo)) {
        errorSpan.textContent = 'Ingresa el correo válido del cliente para asociarlo con la mesa.';
        errorSpan.style.display = 'block';
        return;
    }
    mesa.estado = 'ocupada';
    mesa.clientes = numClientes;
    mesa.pedidos = 0;
    mesa.tiempo = 0;
    mesa.clienteNombre = document.getElementById('inputNombreCliente').value.trim();
    mesa.clienteCorreo = clienteCorreo;
    if (!guardarMesas()) return;
    mostrarToast(`✓ Mesa ${mesa.numero} asignada a ${numClientes} cliente(s)`);
    cerrarModal();
    renderizarMesas(obtenerMesasFiltradas());
    actualizarStats();
}

function vaciarMesa() {
    const mesa = mesaSeleccionada;
    const btnAccion = document.getElementById('btnAccion');
    mesa.estado = 'libre';
    mesa.clientes = 0;
    mesa.pedidos = 0;
    mesa.tiempo = 0;
    mesa.clienteNombre = '';
    mesa.clienteCorreo = '';
    guardarMesas();
    confirmandoVaciado = false;
    mostrarToast(`✓ Mesa ${mesa.numero} vaciada y disponible`);
    cerrarModal();
    renderizarMesas(obtenerMesasFiltradas());
    actualizarStats();
}

function actualizarStats() {
    const libres = mesas.filter(m => m.estado === 'libre').length;
    const ocupadas = mesas.filter(m => m.estado === 'ocupada').length;
    const total = mesas.length;
    const porcentaje = Math.round((ocupadas / total) * 100);

    document.getElementById('mesasDisponibles').textContent = libres;
    document.getElementById('mesasOcupadas').textContent = ocupadas;
    document.getElementById('tasaOcupacion').textContent = `${porcentaje}%`;
}

function obtenerMesasFiltradas() {
    if (filtroActual === 'libres') {
        return mesas.filter(m => m.estado === 'libre');
    } else if (filtroActual === 'ocupadas') {
        return mesas.filter(m => m.estado === 'ocupada');
    }
    return mesas;
}

function agregarEventosFiltros() {
    const botonesFiltro = document.querySelectorAll('.filter-btn');
    
    botonesFiltro.forEach(boton => {
        boton.addEventListener('click', () => {
            botonesFiltro.forEach(btn => btn.classList.remove('active'));
            
            boton.classList.add('active');
            
            filtroActual = boton.getAttribute('data-filter');
            renderizarMesas(obtenerMesasFiltradas());
        });
    });
}

function mostrarToast(mensaje, esError = false) {
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = mensaje;
    toast.style.position = 'fixed';
    toast.style.bottom = '20px';
    toast.style.right = '20px';
    toast.style.backgroundColor = esError ? '#ef4444' : '#10b981';
    toast.style.color = 'white';
    toast.style.padding = '15px 25px';
    toast.style.borderRadius = '8px';
    toast.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.2)';
    toast.style.zIndex = '1001';
    toast.style.fontWeight = 'bold';
    
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.remove();
    }, 3000);
}

function formatearTiempo(segundos) {
    const minutos = Math.floor(segundos / 60);
    const secs = segundos % 60;
    return `${minutos}:${secs < 10 ? '0' : ''}${secs}`;
}

function cambiarEstadoMesa(idMesa) {
    const mesa = mesas.find(m => m.id === idMesa);
    if (mesa) {
        mesa.estado = mesa.estado === 'libre' ? 'ocupada' : 'libre';
        if (mesa.estado === 'ocupada') {
            mesa.clientes = 2;
            mesa.pedidos = 1;
            mesa.tiempo = 900;
        } else {
            mesa.clientes = 0;
            mesa.pedidos = 0;
            mesa.tiempo = 0;
        }
        guardarMesas();
        renderizarMesas(mesas);
        actualizarStats();
    }
}

setInterval(() => {
    mesas.forEach(mesa => {
        if (mesa.estado === 'ocupada') {
            mesa.tiempo += 60;
        }
    });
}, 60000);

window.addEventListener('storage', evento => {
    if (evento.key === claveMesas) {
        mesas = cargarMesas();
        renderizarMesas(obtenerMesasFiltradas());
        actualizarStats();
    }
});

window.addEventListener('requesty-mesas-actualizadas', evento => {
    if (!Array.isArray(evento.detail)) return;
    mesas = mesasIniciales.map(base => ({
        ...base,
        ...evento.detail.find(mesa => Number(mesa.id) === base.id)
    }));
    renderizarMesas(obtenerMesasFiltradas());
    actualizarStats();
});

function escaparHTML(valor) {
    return String(valor ?? '').replace(/[&<>"']/g, caracter => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[caracter]);
}
