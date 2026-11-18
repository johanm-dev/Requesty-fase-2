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

function normalizarEstado(estado) {
    const normalizado = String(estado || "").trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLocaleLowerCase("es");
    if (["nuevo", "pendiente", "en cola"].includes(normalizado)) return nombresEstados.cola;
    if (["preparando", "en preparacion"].includes(normalizado)) return nombresEstados.preparacion;
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
    if (!coincidencia) return 0;
    const fecha = new Date();
    fecha.setHours(Number(coincidencia[1]), Number(coincidencia[2]), 0, 0);
    if (fecha.getTime() > Date.now()) fecha.setDate(fecha.getDate() - 1);
    return fecha.getTime();
}

function leerPedidos() {
    try {
        const guardados = JSON.parse(localStorage.getItem(clavePedidos) || "[]");
        if (!Array.isArray(guardados)) throw new Error("La lista de pedidos guardada no es válida.");
        mostrarError("");
        return guardados.map(pedido => ({
            ...pedido,
            status: normalizarEstado(pedido.status || pedido.estado),
            items: Array.isArray(pedido.items) ? pedido.items : [],
            receivedAt: pedido.receivedAt || pedido.createdAt || new Date(obtenerFechaRecepcion(pedido)).toISOString()
        }));
    } catch (error) {
        mostrarError(error.message || "No se pudieron leer las comandas.");
        return [];
    }
}

function mostrarError(mensaje) {
    document.getElementById("mensaje-cocina").textContent = mensaje;
}

function guardarPedidos(pedidosActualizados) {
    try {
        localStorage.setItem(clavePedidos, JSON.stringify(pedidosActualizados));
        window.dispatchEvent(new CustomEvent(eventoPedidos, { detail: pedidosActualizados }));
        mostrarError("");
        return true;
    } catch {
        mostrarError("No se pudo actualizar la comanda. Verifica el almacenamiento del navegador.");
        return false;
    }
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

function detallePersonalizacion(item) {
    const detalles = [];
    if (item.terminoCoccion) detalles.push(`Término: ${item.terminoCoccion}`);
    if (item.alternativaReceta) detalles.push(`Preparación: ${item.alternativaReceta}`);
    if (item.ingredientesExcluidos?.length) detalles.push(`Sin: ${item.ingredientesExcluidos.join(", ")}`);
    (item.extras || []).forEach(extra => {
        detalles.push(`Extra: ${extra.nombre} × ${extra.cantidad}`);
    });
    return detalles;
}

function renderizarItem(item) {
    const personalizaciones = detallePersonalizacion(item);
    return `<li><strong>${Math.max(1, Number(item.cantidad) || 1)} × ${escaparHTML(item.nombre || "Producto")}</strong>${personalizaciones.length
        ? `<ul class="personalizaciones">${personalizaciones.map(detalle => `<li>${escaparHTML(detalle)}</li>`).join("")}</ul>`
        : ""}</li>`;
}

function renderizarPedido(pedido, ahora) {
    const estado = pedido.status;
    const espera = minutosEnEspera(pedido, ahora);
    const claseEstado = estado === nombresEstados.cola ? "estado-pendiente"
        : estado === nombresEstados.preparacion ? "estado-preparacion" : "estado-listo";
    const claseEspera = claseAlerta(pedido, espera);
    const fechaRecepcion = new Date(obtenerFechaRecepcion(pedido));
    const hora = Number.isNaN(fechaRecepcion.getTime())
        ? escaparHTML(pedido.time || "Hora no disponible")
        : fechaRecepcion.toLocaleTimeString("es-CO", { hour: "2-digit", minute: "2-digit" });
    const accion = estado === nombresEstados.cola
        ? `<button class="boton-accion" type="button" data-estado-pedido="${escaparHTML(pedido.id)}" data-nuevo-estado="${nombresEstados.preparacion}">Iniciar preparación</button>`
        : estado === nombresEstados.preparacion
            ? `<button class="boton-accion" type="button" data-estado-pedido="${escaparHTML(pedido.id)}" data-nuevo-estado="${nombresEstados.listo}">Marcar listo para servir</button>`
            : '<p class="mensaje-listo">Listo para servir</p>';
    const productos = pedido.items.length
        ? pedido.items.map(renderizarItem).join("")
        : "<li>Sin productos registrados.</li>";

    return `<article class="tarjeta-pedido ${claseEstado} ${claseEspera}" data-pedido-id="${escaparHTML(pedido.id)}">
        <div class="pedido-encabezado">
            <span class="pedido-numero">PEDIDO ${escaparHTML(pedido.id)}</span>
            <span class="pedido-espera" data-tiempo-pedido="${escaparHTML(pedido.id)}" role="status">${espera} min</span>
        </div>
        <p class="pedido-info">${escaparHTML(pedido.destination || pedido.channel || "Pedido")}</p>
        <p class="pedido-info">Recibido: ${hora}</p>
        <p class="pedido-seccion-titulo">Productos</p>
        <ul class="lista-productos">${productos}</ul>
        ${pedido.stockDeductions?.length ? `<p class="pedido-info inventario-descontado">Insumos descontados: ${pedido.stockDeductions.map(insumo => `${escaparHTML(insumo.nombre)} ${insumo.cantidad}`).join(", ")}</p>` : ""}
        <span class="etiqueta-estado ${claseEstado}">${escaparHTML(estado)}</span>
        ${accion}
    </article>`;
}

function mostrarPedidos() {
    const contenedor = document.getElementById("contenedor-pedidos");
    const pedidos = leerPedidos()
        .map(pedido => ({ ...pedido, status: normalizarEstado(pedido.status) }))
        .filter(pedido => ![nombresEstados.entregado, nombresEstados.cancelado].includes(pedido.status))
        .sort((a, b) => obtenerFechaRecepcion(a) - obtenerFechaRecepcion(b));
    const ahora = Date.now();
    contenedor.innerHTML = pedidos.length
        ? pedidos.map(pedido => renderizarPedido(pedido, ahora)).join("")
        : '<p class="cocina-vacia">No hay comandas pendientes. Los pedidos nuevos aparecerán aquí automáticamente.</p>';
    document.getElementById("contador-pendientes").textContent = pedidos.filter(pedido => pedido.status === nombresEstados.cola).length;
    document.getElementById("contador-preparacion").textContent = pedidos.filter(pedido => pedido.status === nombresEstados.preparacion).length;
    document.getElementById("contador-listos").textContent = pedidos.filter(pedido => pedido.status === nombresEstados.listo).length;
}

function prepararDescuentoInventario(pedido) {
    const inventario = window.RequestyInventario.leerInventario();
    let productos;
    try {
        productos = JSON.parse(localStorage.getItem("productos-restaurante") || "null");
    } catch {
        throw new Error("No se pudo leer el menú para comprobar las recetas.");
    }
    if (!Array.isArray(productos)) throw new Error("El menú guardado no tiene un formato válido.");
    const stockDisponible = new Map(inventario.map(insumo => [window.RequestyInventario.normalizarNombre(insumo.nombre), insumo.stockActual]));
    const consumo = new Map();
    const nombresInsumo = new Map(inventario.map(insumo => [window.RequestyInventario.normalizarNombre(insumo.nombre), insumo.nombre]));
    for (const item of pedido.items) {
        const producto = productos.find(actual => String(actual.id) === String(item.id));
        if (!producto) throw new Error(`No se encontró la receta del plato ${item.nombre}.`);
        const ingredientes = Array.isArray(producto.ingredientes) ? producto.ingredientes : [];
        const receta = producto.receta;
        const excluidos = new Set((item.ingredientesExcluidos || []).map(window.RequestyInventario.normalizarNombre));
        const porciones = Math.max(1, Math.floor(Number(item.cantidad) || 1));
        for (const ingrediente of ingredientes) {
            const clave = window.RequestyInventario.normalizarNombre(ingrediente);
            if (excluidos.has(clave)) continue;
            const cantidad = Number(receta?.[clave]);
            if (!Number.isFinite(cantidad) || cantidad <= 0) {
                throw new Error(`La receta de ${producto.nombre} no tiene una cantidad válida para ${ingrediente}. Configúrala en Reportes > Administrar menú.`);
            }
            if (!stockDisponible.has(clave)) throw new Error(`Registra ${ingrediente} en el inventario antes de preparar ${producto.nombre}.`);
            consumo.set(clave, (consumo.get(clave) || 0) + cantidad * porciones);
        }
        for (const grupo of producto.recetaAlternativas || []) {
            const opcion = (grupo.opciones || []).find(actual =>
                window.RequestyInventario.normalizarNombre(actual.ingrediente)
                    === window.RequestyInventario.normalizarNombre(item.alternativaReceta)
            );
            if (!opcion) {
                throw new Error(`Selecciona una opción válida para ${grupo.nombre || producto.nombre} antes de iniciar la preparación.`);
            }
            const clave = window.RequestyInventario.normalizarNombre(opcion.ingrediente);
            if (!stockDisponible.has(clave)) throw new Error(`Registra ${opcion.ingrediente} en el inventario antes de preparar ${producto.nombre}.`);
            consumo.set(clave, (consumo.get(clave) || 0) + Number(opcion.cantidad) * porciones);
        }
        for (const extra of item.extras || []) {
            const clave = window.RequestyInventario.normalizarNombre(extra.nombre);
            if (stockDisponible.has(clave)) {
                consumo.set(clave, (consumo.get(clave) || 0) + Number(extra.cantidad || 0) * porciones);
            }
        }
    }
    const faltantes = [...consumo].filter(([clave, cantidad]) => stockDisponible.get(clave) < cantidad);
    if (faltantes.length) {
        throw new Error(`Stock insuficiente para iniciar la preparación: ${faltantes.map(([clave, cantidad]) => `${nombresInsumo.get(clave)} (disponible ${stockDisponible.get(clave)}, requerido ${cantidad})`).join(", ")}.`);
    }
    const detalle = [...consumo].map(([clave, cantidad]) => ({ nombre: nombresInsumo.get(clave), cantidad }));
    const actualizado = inventario.map(insumo => {
        const cantidad = consumo.get(window.RequestyInventario.normalizarNombre(insumo.nombre)) || 0;
        return cantidad ? { ...insumo, stockActual: insumo.stockActual - cantidad } : insumo;
    });
    return { inventarioActualizado: actualizado, detalle };
}

function cambiarEstadoPedido(id, nuevoEstado) {
    if (![nombresEstados.preparacion, nombresEstados.listo].includes(nuevoEstado)) return;
    const pedidos = leerPedidos();
    const pedido = pedidos.find(actual => String(actual.id) === String(id));
    if (!pedido) {
        mostrarError("La comanda ya no existe o fue eliminada.");
        mostrarPedidos();
        return;
    }
    const estadoActual = normalizarEstado(pedido.status || pedido.estado);
    const transicionesValidas = {
        [nombresEstados.cola]: nombresEstados.preparacion,
        [nombresEstados.preparacion]: nombresEstados.listo
    };
    if (transicionesValidas[estadoActual] !== nuevoEstado) {
        mostrarError("La comanda cambió de estado en otra pantalla. Se actualizará la lista.");
        mostrarPedidos();
        return;
    }
    let descuento = null;
    if (nuevoEstado === nombresEstados.preparacion && !pedido.stockDeductions) {
        try {
            descuento = prepararDescuentoInventario(pedido);
        } catch (error) {
            mostrarError(error.message || "No se pudo descontar el inventario.");
            return;
        }
    }
    const actualizado = {
        ...pedido,
        status: nuevoEstado,
        statusClass: nuevoEstado === nombresEstados.preparacion ? "status-preparando" : "status-listo",
        updatedAt: new Date().toISOString(),
        ...(descuento ? { stockDeductions: descuento.detalle, stockDeductedAt: new Date().toISOString() } : {})
    };
    const actualizados = pedidos.map(actual => String(actual.id) === String(id) ? actualizado : actual);
    const pedidosAnteriores = localStorage.getItem(clavePedidos);
    const inventarioAnterior = localStorage.getItem(window.RequestyInventario.claveInventario);
    try {
        if (descuento) localStorage.setItem(window.RequestyInventario.claveInventario, JSON.stringify(descuento.inventarioActualizado));
        localStorage.setItem(clavePedidos, JSON.stringify(actualizados));
        if (descuento) window.dispatchEvent(new CustomEvent(window.RequestyInventario.eventoInventario));
        window.dispatchEvent(new CustomEvent(eventoPedidos, { detail: actualizados }));
        mostrarError("");
        mostrarPedidos();
    } catch {
        try {
            if (inventarioAnterior === null) localStorage.removeItem(window.RequestyInventario.claveInventario);
            else localStorage.setItem(window.RequestyInventario.claveInventario, inventarioAnterior);
            if (pedidosAnteriores === null) localStorage.removeItem(clavePedidos);
            else localStorage.setItem(clavePedidos, pedidosAnteriores);
            mostrarError("No se pudo guardar la actualización de la comanda y su consumo de inventario. Se revirtieron los cambios.");
        } catch (errorRollback) {
            mostrarError(`Falló el guardado y no se pudo completar la reversión: ${errorRollback.message}. Revisa el inventario y el estado de la comanda.`);
        }
    }
}

function actualizarTiempos() {
    const ahora = Date.now();
    leerPedidos().forEach(pedido => {
        const tarjeta = document.querySelector(`[data-pedido-id="${CSS.escape(String(pedido.id))}"]`);
        if (!tarjeta) return;
        const espera = minutosEnEspera(pedido, ahora);
        tarjeta.classList.remove("alerta-normal", "alerta-advertencia", "alerta-critica", "alerta-listo");
        tarjeta.classList.add(claseAlerta(pedido, espera));
        const tiempo = tarjeta.querySelector("[data-tiempo-pedido]");
        if (tiempo) tiempo.textContent = `${espera} min`;
    });
}

document.getElementById("contenedor-pedidos").addEventListener("click", evento => {
    const boton = evento.target.closest("[data-estado-pedido]");
    if (boton) cambiarEstadoPedido(boton.dataset.estadoPedido, boton.dataset.nuevoEstado);
});

window.addEventListener("storage", evento => {
    if (evento.key === clavePedidos) mostrarPedidos();
});
window.addEventListener(eventoPedidos, mostrarPedidos);

mostrarPedidos();
window.setInterval(actualizarTiempos, 15000);
