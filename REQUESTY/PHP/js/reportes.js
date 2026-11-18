let chart = null;
const claveInicioNuevoDiaReportes = "requestyInicioNuevoDia";

function leerFacturasReporte() {
    try {
        const facturas = JSON.parse(localStorage.getItem("requestyFacturas") || "[]");
        if (!Array.isArray(facturas)) throw new Error("El historial de facturas no tiene un formato válido.");
        return facturas.filter(factura => factura.fecha && Number.isFinite(Number(factura.total)));
    } catch (error) {
        document.getElementById("reporte-facturas").innerHTML = `<tr><td colspan="6">${escaparCatalogo(error.message || "No se pudo leer el historial de facturas.")}</td></tr>`;
        return [];
    }
}

function claveFecha(fecha) {
    const valor = new Date(fecha);
    return Number.isNaN(valor.getTime()) ? "" : `${valor.getFullYear()}-${String(valor.getMonth() + 1).padStart(2, "0")}-${String(valor.getDate()).padStart(2, "0")}`;
}

function facturasDelPeriodo(startDate = document.getElementById("startDate").value, endDate = document.getElementById("endDate").value) {
    const inicioNuevoDia = Date.parse(localStorage.getItem(claveInicioNuevoDiaReportes) || "");
    const diaInicioNuevoDia = Number.isFinite(inicioNuevoDia) ? claveFecha(inicioNuevoDia) : "";
    return leerFacturasReporte().filter(factura => {
        const dia = claveFecha(factura.fecha);
        const fecha = Date.parse(factura.fecha);
        const dentroDelRango = dia && (!startDate || dia >= startDate) && (!endDate || dia <= endDate);
        const previaAlReinicioDelMismoDia = dia === diaInicioNuevoDia
            && Number.isFinite(fecha)
            && fecha < inicioNuevoDia;
        return dentroDelRango && !previaAlReinicioDelMismoDia;
    });
}

function formatoDineroReporte(valor) {
    return `$${Number(valor || 0).toLocaleString("es-CO")}`;
}

function initializeDates() {
    const today = new Date();
    const sevenDaysAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
    const inicioNuevoDia = Date.parse(localStorage.getItem(claveInicioNuevoDiaReportes) || "");

    document.getElementById('endDate').valueAsDate = today;
    if (Number.isFinite(inicioNuevoDia)) {
        document.getElementById('startDate').value = claveFecha(inicioNuevoDia);
    } else {
        document.getElementById('startDate').valueAsDate = sevenDaysAgo;
    }
}

function createChart() {
    if (typeof Chart !== "function") return;
    const ctx = document.getElementById('salesChart').getContext('2d');
    const range = Number(document.getElementById('chartRange').value);
    const labels = [];
    const fechas = [];
    const today = new Date();
    for (let i = range - 1; i >= 0; i--) {
        const date = new Date(today);
        date.setDate(date.getDate() - i);
        labels.push(date.toLocaleDateString('es-CO', { month: 'short', day: 'numeric' }));
        fechas.push(claveFecha(date));
    }
    const totalesDia = new Map(facturasDelPeriodo().map(factura => [claveFecha(factura.fecha), 0]));
    facturasDelPeriodo().forEach(factura => {
        const dia = claveFecha(factura.fecha);
        totalesDia.set(dia, (totalesDia.get(dia) || 0) + Number(factura.total));
    });
    const data = fechas.map(dia => totalesDia.get(dia) || 0);

    if (chart) {
        chart.destroy();
    }

    chart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Ventas ($)',
                data: data,
                borderColor: '#9D2227', // PANTONE 6294 C
                backgroundColor: 'rgba(157, 34, 39, 0.08)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: '#C15C24', // PANTONE 6259 C
                pointBorderColor: '#FAF4F5',
                pointBorderWidth: 2,
                pointHoverRadius: 7,
                pointHoverBackgroundColor: '#7D1A1E',
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        color: '#3E2834', // PANTONE 6322 C
                        font: { size: 12, weight: 'bold' },
                        padding: 15,
                        usePointStyle: true,
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#725F5B',
                        callback: function(value) {
                            return '$' + (value / 1000).toFixed(0) + 'k';
                        }
                    },
                    grid: {
                        color: 'rgba(215, 180, 161, 0.2)',
                        drawBorder: false
                    }
                },
                x: {
                    ticks: {
                        color: '#725F5B'
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

// ACTUALIZAR GRÁFICA
function updateChart() {
    createChart();
}

// GENERACION DE REPORTE
function generateReport() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;

    if (!startDate || !endDate || startDate > endDate) {
        document.getElementById("reporte-facturas").innerHTML = '<tr><td colspan="6">Selecciona un rango de fechas válido.</td></tr>';
        return;
    }

    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.textContent = `✓ Reporte generado exitosamente para ${startDate} a ${endDate}`;
    document.body.appendChild(toast);


    setTimeout(() => {
        toast.remove();
    }, 3000);

    updateKPIs();
    createChart();
}

function updateKPIs() {
    const facturas = facturasDelPeriodo();
    const ventas = facturas.reduce((total, factura) => total + Number(factura.total), 0);
    document.getElementById('kpiVentas').textContent = formatoDineroReporte(ventas);
    document.getElementById('kpiPedidos').textContent = new Set(facturas.map(factura => String(factura.pedidoId))).size;

    const productos = new Map();
    const horas = new Map();
    for (const factura of facturas) {
        for (const item of factura.items || []) {
            productos.set(item.nombre, (productos.get(item.nombre) || 0) + Math.max(0, Number(item.cantidad) || 0));
        }
        const hora = new Date(factura.fecha).getHours();
        horas.set(hora, (horas.get(hora) || 0) + 1);
    }
    const ranking = [...productos].sort((a, b) => b[1] - a[1]).slice(0, 3);
    document.getElementById("productos-mas-vendidos").innerHTML = ranking.length
        ? ranking.map(([nombre, cantidad], indice) => `<div class="kpi-list-item"><strong>${indice + 1}.</strong> ${escaparCatalogo(nombre)} · ${cantidad} vendidos</div>`).join("")
        : '<div class="kpi-list-item">Sin ventas registradas</div>';
    const horasPico = [...horas].sort((a, b) => b[1] - a[1]).slice(0, 3);
    document.getElementById("horas-pico-lista").innerHTML = horasPico.length
        ? horasPico.map(([hora, cantidad]) => `<div class="kpi-list-item"><strong>${String(hora).padStart(2, "0")}:00–${String((hora + 1) % 24).padStart(2, "0")}:00</strong> · ${cantidad} pedidos</div>`).join("")
        : '<div class="kpi-list-item">Sin ventas registradas</div>';
    renderizarDetalleFacturas(facturas);
}

function renderizarDetalleFacturas(facturas = facturasDelPeriodo()) {
    const cuerpo = document.getElementById("reporte-facturas");
    cuerpo.innerHTML = facturas.length ? facturas.slice().sort((a, b) => Date.parse(b.fecha) - Date.parse(a.fecha)).map(factura => {
        const detalles = (factura.items || []).map(item => `<li>${Number(item.cantidad) || 1} × ${escaparCatalogo(item.nombre)}</li>`).join("");
        return `<tr><td>${escaparCatalogo(factura.numero)}</td><td>${escaparCatalogo(factura.pedidoId)}</td><td>${escaparCatalogo(factura.meseroNombre || factura.meseroEmail || "No registrado")}</td><td>${escaparCatalogo(factura.metodo)}</td><td>${formatoDineroReporte(factura.total)}</td><td><details><summary>Ver</summary><p>Cliente: ${escaparCatalogo(factura.clienteNombre)} · ${escaparCatalogo(factura.clienteCorreo)}</p><p>Mesa ${escaparCatalogo(factura.mesa)} · ${escaparCatalogo(new Date(factura.fecha).toLocaleString("es-CO"))}</p><p>Estado: ${escaparCatalogo(factura.estado)}</p><ul>${detalles}</ul></details></td></tr>`;
    }).join("") : '<tr><td colspan="6">No hay facturas registradas en este periodo.</td></tr>';
}


document.addEventListener('DOMContentLoaded', () => {
    initializeDates();
    updateKPIs();
    createChart();
});

window.addEventListener("storage", evento => {
    if (evento.key === "requestyFacturas") {
        updateKPIs();
        createChart();
    }
});

// ===================================================
// CAMBIAR ENTRE REPORTES Y ADMINISTRAR MENÚ
// ===================================================
function mostrarVistaMenu(mostrarMenu) {
    document.getElementById('vista-reportes').classList.toggle('oculto', mostrarMenu);
    document.getElementById('vista-menu').classList.toggle('oculto', !mostrarMenu);

    // Al volver a reportes, la gráfica se ajusta al tamaño de la pantalla
    if (!mostrarMenu && chart) {
        chart.resize();
    }
}

document.getElementById('btn-administrar-menu').addEventListener('click', () => mostrarVistaMenu(true));
document.getElementById('btn-volver-reportes').addEventListener('click', () => mostrarVistaMenu(false));


// ===================================================
// ADMINISTRAR MENÚ (antes estaba en menu.js)
// ===================================================
const productosIniciales = [
    { id: 1, nombre: "Caldo de costilla", precio: 8000, categoria: "Desayuno", imagen: "img/tarjeta/caldo-de-costilla.jpg", ingredientes: ["Costilla", "Papa", "Cilantro", "Agua Filtrada", "Sal"], receta: { costilla: 200, papa: 250, cilantro: 5, "agua filtrada": 0.5, sal: 3 } },
    { id: 2, nombre: "Jugo de mora", precio: 6000, categoria: "Bebida", imagen: "img/tarjeta/jugo-de-mora.jpg", ingredientes: ["Moras", "Azúcar"], receta: { moras: 80, azucar: 20 }, recetaAlternativas: [{ nombre: "Base líquida", opciones: [{ ingrediente: "Agua Filtrada", cantidad: 0.25 }, { ingrediente: "Leche", cantidad: 0.25 }] }] },
    { id: 3, nombre: "Huevos pericos", precio: 7000, categoria: "Desayuno", imagen: "img/tarjeta/huevos-pericos.jpg", ingredientes: ["Huevos", "Tomate", "Cebolla", "Sal"], receta: { huevos: 2, tomate: 60, cebolla: 40, sal: 3 } },
    { id: 4, nombre: "Bandeja paisa", precio: 30000, categoria: "Almuerzo", imagen: "img/tarjeta/bandeja-paisa.jpg", ingredientes: ["Arroz", "Platano", "Tocineta", "Carne molida", "Arepa", "Aguacate", "Chorizo", "Huevos", "Frijoles"], receta: { arroz: 150, platano: 100, tocineta: 50, "carne molida": 100, arepa: 1, aguacate: 80, chorizo: 1, huevos: 1, frijoles: 150 } }
];

let productos = cargarProductos();
let categorias = cargarCategoriasGuardadas();
let categoriaActual = "Todas";
let busqueda = "";
let productoEditando = null;

function cargarProductos() {
    try {
        const guardados = localStorage.getItem("productos-restaurante");
        if (guardados === null) return productosIniciales.map(producto => ({ ...producto, activo: true, imagenRespaldo: producto.imagen, imagenActiva: true }));
        const lista = JSON.parse(guardados);
        if (!Array.isArray(lista)) return productosIniciales.map(producto => ({ ...producto, activo: true, imagenRespaldo: producto.imagen, imagenActiva: true }));
        return lista.map(producto => {
            const productoBase = productosIniciales.find(base => base.id === producto.id);
            const imagenOriginal = String(producto.imagen || "");
            const productoSinEstadoPrecio = { ...producto };
            delete productoSinEstadoPrecio.precioActivo;
            return {
                ...productoSinEstadoPrecio,
                id: producto.id ?? Date.now() + Math.random(),
                categoria: ({ Desayunos: "Desayuno", Almuerzos: "Almuerzo", Bebidas: "Bebida" })[producto.categoria] || producto.categoria,
                ingredientes: Array.isArray(producto.ingredientes) ? producto.ingredientes : String(producto.ingredientes || "").split(",").map(item => item.trim()).filter(Boolean),
                precio: producto.precio === null || producto.precio === "" || !Number.isFinite(Number(producto.precio)) || Number(producto.precio) < 0 ? null : Number(producto.precio),
                activo: producto.activo !== false,
                imagen: producto.imagen === undefined ? productoBase?.imagen || "" : imagenOriginal,
                imagenUrl: String(producto.imagenUrl || (imagenOriginal.startsWith("data:image/") || imagenOriginal.startsWith("img/tarjeta/") ? "" : imagenOriginal)),
                imagenRespaldo: producto.imagenEliminada ? "" : productoBase?.imagen || "",
                imagenActiva: producto.imagenActiva !== false
            };
        });
    } catch {
        return productosIniciales.map(producto => ({ ...producto, activo: true, imagenRespaldo: producto.imagen, imagenActiva: true }));
    }
}

function esRutaImagenBase(valor) {
    return /^img\/tarjeta\/[a-z0-9-]+\.(?:jpg|jpeg|png|webp)$/i.test(String(valor || ""));
}

function srcImagenProducto(producto) {
    if (producto.imagen.startsWith("data:image/") || esRutaImagenBase(producto.imagen)) return producto.imagen;
    if (urlImagenSegura(producto.imagenUrl)) return producto.imagenUrl;
    return producto.imagenRespaldo || "";
}

function cargarCategoriasGuardadas() {
    try {
        const guardadas = JSON.parse(localStorage.getItem("categorias-restaurante") || "null");
        if (Array.isArray(guardadas)) return guardadas;
    } catch {}

    const nombres = [...new Set([...productosIniciales.map(producto => producto.categoria), ...productos.map(producto => producto.categoria)])];
    const iniciales = nombres.map((nombre, indice) => ({ id: indice + 1, nombre, activa: true }));
    localStorage.setItem("categorias-restaurante", JSON.stringify(iniciales));
    return iniciales;
}

function guardarProductos() {
    try {
        localStorage.setItem("productos-restaurante", JSON.stringify(productos));
        return true;
    } catch {
        return false;
    }
}

function normalizarClaveIngrediente(ingrediente) {
    return String(ingrediente || "").trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLocaleLowerCase("es");
}

function aplicarRecetasBase() {
    const claveMigracion = "requesty-recetas-base-v1";
    const migracionNecesaria = localStorage.getItem(claveMigracion) !== "true";
    let actualizados = migracionNecesaria || localStorage.getItem("productos-restaurante") === null;
    productos = productos.map(producto => {
        const base = productosIniciales.find(inicial =>
            normalizarClaveIngrediente(inicial.nombre) === normalizarClaveIngrediente(producto.nombre)
        );
        if (!base) return producto;
        const ingredientesProducto = (Array.isArray(producto.ingredientes) ? producto.ingredientes : [])
            .map(normalizarClaveIngrediente).sort();
        const ingredientesBase = Object.keys(base.receta).sort();
        if (JSON.stringify(ingredientesProducto) !== JSON.stringify(ingredientesBase)) return producto;
        const recetaExistente = Object.fromEntries(Object.entries(producto.receta || {})
            .map(([ingrediente, cantidad]) => [normalizarClaveIngrediente(ingrediente), Number(cantidad)])
            .filter(([, cantidad]) => Number.isFinite(cantidad) && cantidad > 0));
        if (migracionNecesaria) return { ...producto, receta: { ...base.receta } };
        if (ingredientesBase.every(ingrediente => Number.isFinite(recetaExistente[ingrediente]))) return producto;
        actualizados = true;
        return { ...producto, receta: { ...base.receta, ...recetaExistente } };
    });
    if (actualizados && !guardarProductos()) {
        document.getElementById("mensaje-menu-stock").textContent = "No se pudieron guardar las recetas base en este navegador.";
        return;
    }
    if (migracionNecesaria) {
        try {
            localStorage.setItem(claveMigracion, "true");
        } catch {
            document.getElementById("mensaje-menu-stock").textContent = "Las recetas se guardaron, pero no se pudo marcar la migración como completada.";
        }
    }
}

aplicarRecetasBase();
function migrarRecetasLiquidasALitros() {
    const claveMigracion = "requesty-recetas-liquidas-litros-v1";
    if (localStorage.getItem(claveMigracion) === "true") return;
    const recetasAnteriores = productos;
    productos = productos.map(producto => {
        if (!producto.receta || typeof producto.receta !== "object") return producto;
        const receta = { ...producto.receta };
        let modificada = false;
        Object.entries(receta).forEach(([ingrediente, cantidad]) => {
            const clave = normalizarClaveIngrediente(ingrediente);
            const valor = Number(cantidad);
            if (["agua", "leche o agua"].includes(clave) && Number.isFinite(valor) && valor > 1) {
                receta[ingrediente] = valor / 1000;
                modificada = true;
            }
        });
        return modificada ? { ...producto, receta } : producto;
    });
    if (JSON.stringify(recetasAnteriores) !== JSON.stringify(productos) && !guardarProductos()) {
        productos = recetasAnteriores;
        document.getElementById("mensaje-menu-stock").textContent = "No se pudieron convertir las recetas líquidas a litros.";
        return;
    }
    try {
        localStorage.setItem(claveMigracion, "true");
    } catch {
        document.getElementById("mensaje-menu-stock").textContent = "Las recetas se actualizaron, pero no se pudo guardar el marcador de conversión.";
    }
}
migrarRecetasLiquidasALitros();
window.RequestyInventario.renderizarInventario();

function formatearPrecio(precio) {
    return new Intl.NumberFormat("es-CO").format(precio);
}

function escaparCatalogo(valor) {
    return String(valor ?? "").replace(/[&<>"']/g, (caracter) => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    })[caracter]);
}

function analizarReceta(texto) {
    const receta = {};
    const lineas = String(texto || "").split(/[,\n]/).map(linea => linea.trim()).filter(Boolean);
    for (const linea of lineas) {
        const coincidencia = linea.match(/^(.+?)\s*:\s*(\d+(?:\.\d+)?)$/);
        if (!coincidencia || Number(coincidencia[2]) <= 0) return null;
        const ingrediente = coincidencia[1].trim();
        const clave = ingrediente.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLocaleLowerCase("es");
        if (Object.prototype.hasOwnProperty.call(receta, clave)) return null;
        receta[clave] = Number(coincidencia[2]);
    }
    return Object.keys(receta).length ? receta : null;
}

function serializarReceta(producto) {
    return (Array.isArray(producto?.ingredientes) ? producto.ingredientes : [])
        .map(ingrediente => {
            const clave = ingrediente.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLocaleLowerCase("es");
            const cantidad = producto?.receta?.[clave];
            return `${ingrediente}: ${Number.isFinite(Number(cantidad)) && Number(cantidad) > 0 ? Number(cantidad) : ""}`;
        }).join("\n");
}

function mostrarCatalogo() {
    const catalogo = document.getElementById("catalogo");
    let inventario = [];
    let inventarioValido = true;
    try {
        inventario = window.RequestyInventario.leerInventario();
        document.getElementById("mensaje-menu-stock").textContent = "";
    } catch (error) {
        inventarioValido = false;
        document.getElementById("mensaje-menu-stock").textContent = error.message || "No se pudo comprobar el inventario.";
    }
    const productosVisibles = productos.filter(producto => {
        const coincideCategoria = categoriaActual === "Todas" || producto.categoria === categoriaActual;
        const textoProducto = `${producto.nombre} ${producto.ingredientes.join(" ")}`.toLowerCase();
        return coincideCategoria && textoProducto.includes(busqueda);
    });

    document.getElementById("contador-productos").textContent = `${productosVisibles.length} producto${productosVisibles.length === 1 ? "" : "s"}`;
    catalogo.innerHTML = productosVisibles.length ? productosVisibles.map(producto => {
        const ingredientesAgotados = inventarioValido
            ? window.RequestyInventario.obtenerIngredientesAgotados(producto, inventario)
            : [];
        const estadoProducto = !producto.activo
            ? "Inactivo"
            : !inventarioValido
                ? "Disponibilidad sin verificar"
                : ingredientesAgotados.length
                    ? `No disponible · Agotado: ${ingredientesAgotados.join(", ")}`
                    : "Disponible";
        const claseEstado = !producto.activo || !inventarioValido || ingredientesAgotados.length ? "estado-no-disponible" : "estado-disponible";
        return `
        <article class="producto ${producto.activo ? "" : "producto-inactivo"}">
            ${srcImagenProducto(producto) && producto.imagenActiva ? `<img class="imagen-producto" src="${escaparCatalogo(srcImagenProducto(producto))}" data-respaldo="${escaparCatalogo(producto.imagenRespaldo || "")}" alt="${escaparCatalogo(producto.nombre)}" loading="lazy">` : ""}
            <span class="categoria">${escaparCatalogo(producto.categoria)}</span>
            <h3>${escaparCatalogo(producto.nombre)}</h3>
            <p class="ingredientes"><strong>Ingredientes:</strong> ${escaparCatalogo(producto.ingredientes.join(", "))}</p>
            <p class="precio">${producto.precio === null ? "Sin precio" : `$${formatearPrecio(producto.precio)}`}</p>
            <p class="estado-producto ${claseEstado}">${escaparCatalogo(estadoProducto)}</p>
            <div class="acciones-producto">
                <button class="editar" data-accion="editar" data-id="${escaparCatalogo(producto.id)}">Editar</button>
                <button class="boton-secundario" data-accion="estado" data-id="${escaparCatalogo(producto.id)}">${producto.activo ? "Inactivar" : "Activar"}</button>
                ${producto.precio !== null ? `<button class="boton-peligro" data-accion="precio-eliminar" data-id="${escaparCatalogo(producto.id)}">Eliminar precio</button>` : ""}
                ${srcImagenProducto(producto) ? `<button class="boton-secundario" data-accion="imagen" data-id="${escaparCatalogo(producto.id)}">${producto.imagenActiva ? "Ocultar imagen" : "Mostrar imagen"}</button>` : ""}
                ${srcImagenProducto(producto) ? `<button class="boton-peligro" data-accion="imagen-eliminar" data-id="${escaparCatalogo(producto.id)}">Eliminar imagen</button>` : ""}
                <button class="boton-peligro" data-accion="eliminar" data-id="${escaparCatalogo(producto.id)}">Eliminar</button>
            </div>
        </article>
    `;
    }).join("") : "<p>No hay productos en esta categoría.</p>";
    catalogo.querySelectorAll("img.imagen-producto").forEach(imagen => {
        imagen.addEventListener("error", () => {
            const respaldo = imagen.dataset.respaldo;
            if (respaldo && imagen.src !== new URL(respaldo, location.href).href) {
                imagen.src = respaldo;
                return;
            }
            imagen.remove();
        }, { once: true });
    });
}

function mostrarFiltros() {
    const filtros = document.getElementById("filtros");
    const opciones = ["Todas", ...categorias.filter(categoria => categoria.activa).map(categoria => categoria.nombre)];
    filtros.innerHTML = opciones.map(categoria => `<button class="filtro ${categoria === categoriaActual ? "activo" : ""}" data-categoria="${escaparCatalogo(categoria)}">${escaparCatalogo(categoria)}</button>`).join("");
}

function obtenerDatosFormulario() {
    const ingredientes = document.getElementById("ingredientes").value
        .split(",")
        .map(ingrediente => ingrediente.trim())
        .filter(Boolean);
    return {
        nombre: document.getElementById("nombre").value.trim(),
        precio: document.getElementById("precio").value.trim() === "" ? null : Number(document.getElementById("precio").value),
        categoria: document.getElementById("categoria").value,
        ingredientes,
        receta: analizarReceta(document.getElementById("receta").value),
        imagen: document.getElementById("imagen-producto").value.trim(),
        imagenActiva: document.getElementById("imagen-activa").checked
    };
}

function datosValidos(datos) {
    const categoriaValida = categorias.some(categoria => categoria.nombre === datos.categoria && categoria.activa);
    const imagenValida = !datos.imagen || urlImagenSegura(datos.imagen);
    const precioValido = datos.precio === null || Number.isFinite(datos.precio) && datos.precio >= 0;
    const recetaCompleta = datos.receta && datos.ingredientes.every(ingrediente => {
        const clave = ingrediente.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLocaleLowerCase("es");
        return Number.isFinite(datos.receta[clave]) && datos.receta[clave] > 0;
    }) && Object.keys(datos.receta).length === datos.ingredientes.length;
    return datos.nombre && categoriaValida && datos.ingredientes.length && recetaCompleta && precioValido && imagenValida;
}

function urlImagenSegura(valor) {
    try {
        return ["http:", "https:"].includes(new URL(valor).protocol);
    } catch {
        return false;
    }
}

function crearCopiaImagen(url) {
    return new Promise(resolve => {
        const imagen = new Image();
        imagen.crossOrigin = "anonymous";
        const tiempoLimite = setTimeout(() => {
            imagen.onload = null;
            imagen.onerror = null;
            resolve(null);
        }, 10000);
        imagen.onload = () => {
            clearTimeout(tiempoLimite);
            try {
                const escala = Math.min(1, 1280 / imagen.naturalWidth, 1280 / imagen.naturalHeight);
                const lienzo = document.createElement("canvas");
                lienzo.width = Math.max(1, Math.round(imagen.naturalWidth * escala));
                lienzo.height = Math.max(1, Math.round(imagen.naturalHeight * escala));
                lienzo.getContext("2d").drawImage(imagen, 0, 0, lienzo.width, lienzo.height);
                const copia = lienzo.toDataURL("image/webp", 0.8);
                resolve(copia.startsWith("data:image/") && copia.length <= 1200000 ? copia : null);
            } catch {
                resolve(null);
            }
        };
        imagen.onerror = () => {
            clearTimeout(tiempoLimite);
            resolve(null);
        };
        imagen.src = url;
    });
}

function urlGuardadaDeProducto(producto) {
    const url = producto.imagenUrl || (producto.imagen && !producto.imagen.startsWith("data:image/") && !esRutaImagenBase(producto.imagen) ? producto.imagen : "");
    return urlImagenSegura(url) ? url : "";
}

async function migrarImagenesExistentes() {
    const pendientes = productos
        .map(producto => ({ id: producto.id, url: urlGuardadaDeProducto(producto) }))
        .filter(producto => producto.url);
    if (!pendientes.length) return;

    const copias = await Promise.all(pendientes.map(producto => crearCopiaImagen(producto.url)));
    let guardadas = 0;
    let fallidas = 0;
    pendientes.forEach((pendiente, indice) => {
        const producto = productos.find(item => item.id === pendiente.id);
        if (!producto || urlGuardadaDeProducto(producto) !== pendiente.url) return;
        if (!copias[indice]) {
            fallidas += 1;
            return;
        }
        producto.imagen = copias[indice];
        producto.imagenUrl = pendiente.url;
        guardadas += 1;
    });

    if (guardadas && !guardarProductos()) {
        document.getElementById("mensaje-menu").textContent = "No hay espacio disponible para guardar las copias locales de las imágenes.";
        return;
    }
    if (guardadas) mostrarCatalogo();
    if (fallidas) {
        document.getElementById("mensaje-menu").textContent = `${guardadas} imágenes guardadas localmente; ${fallidas} no pudieron copiarse desde su servidor.`;
    }
}

function nombreArchivoImagen(producto) {
    const nombre = String(producto.nombre || "plato")
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-|-$/g, "") || "plato";
    return `${nombre}-${producto.id}.webp`;
}

async function guardarImagenesEnCarpeta() {
    const boton = document.getElementById("guardar-imagenes-carpeta");
    const mensaje = document.getElementById("mensaje-menu");
    if (!window.showDirectoryPicker) {
        mensaje.textContent = "Este navegador no permite escribir en carpetas. Usa Chrome o Edge actualizado.";
        return;
    }

    let carpeta;
    try {
        carpeta = await window.showDirectoryPicker({ mode: "readwrite" });
    } catch (error) {
        if (error.name !== "AbortError") mensaje.textContent = "No se pudo abrir la carpeta seleccionada.";
        return;
    }

    const imagenes = productos.filter(producto => producto.imagen || producto.imagenUrl);
    if (!imagenes.length) {
        mensaje.textContent = "No hay imágenes para guardar.";
        return;
    }

    boton.disabled = true;
    mensaje.textContent = "Guardando imágenes en la carpeta seleccionada...";
    let guardadas = 0;
    let fallidas = 0;
    for (const producto of imagenes) {
        let copia = producto.imagen.startsWith("data:image/") ? producto.imagen : "";
        const url = urlGuardadaDeProducto(producto);
        if (!copia && url) copia = await crearCopiaImagen(url) || "";
        if (!copia) {
            fallidas += 1;
            continue;
        }

        try {
            const archivo = await carpeta.getFileHandle(nombreArchivoImagen(producto), { create: true });
            const escritor = await archivo.createWritable();
            await escritor.write(await fetch(copia).then(respuesta => respuesta.blob()));
            await escritor.close();
            producto.imagen = copia;
            if (url) producto.imagenUrl = url;
            guardadas += 1;
        } catch {
            fallidas += 1;
        }
    }

    guardarProductos();
    mostrarCatalogo();
    boton.disabled = false;
    mensaje.textContent = fallidas
        ? `${guardadas} imágenes guardadas; ${fallidas} no se pudieron exportar.`
        : `${guardadas} imágenes guardadas en la carpeta seleccionada.`;
}

function actualizarVistaPreviaImagen() {
    const campoImagen = document.getElementById("imagen-producto");
    const contenedor = document.getElementById("previsualizacion-imagen");
    const imagen = document.getElementById("vista-previa-imagen");
    const estado = document.getElementById("estado-imagen");
    const url = campoImagen.value.trim();

    if (!url) {
        contenedor.hidden = true;
        imagen.removeAttribute("src");
        return;
    }

    contenedor.hidden = false;
    imagen.hidden = true;
    estado.textContent = "Comprobando enlace...";
    if (!urlImagenSegura(url)) {
        estado.textContent = "Ingresa un enlace web directo a una imagen.";
        return;
    }

    imagen.onload = () => {
        if (campoImagen.value.trim() !== url) return;
        imagen.hidden = false;
        estado.textContent = "Imagen lista para mostrar.";
    };
    imagen.onerror = () => {
        if (campoImagen.value.trim() !== url) return;
        estado.textContent = "No se pudo cargar. Usa la URL directa del archivo de imagen, no la de una página.";
    };
    imagen.src = url;
}

document.getElementById("formulario-producto").addEventListener("submit", async evento => {
    evento.preventDefault();
    const datos = obtenerDatosFormulario();
    if (!datosValidos(datos)) {
        document.getElementById("mensaje-menu").textContent = "Revisa el nombre, el precio, la imagen y asigna una cantidad válida a cada ingrediente con el formato Ingrediente: cantidad.";
        return;
    }

    const botonGuardar = document.getElementById("guardar-producto");
    botonGuardar.disabled = true;
    document.getElementById("mensaje-menu").textContent = "Guardando copia local de la imagen...";
    const existente = productos.find(producto => producto.id === productoEditando);
    const urlExistente = existente?.imagenUrl || (existente?.imagen && !existente.imagen.startsWith("data:image/") ? existente.imagen : "");
    const mismaImagen = Boolean(existente && datos.imagen === urlExistente);
    let imagenGuardada = "";
    if (datos.imagen && mismaImagen && existente.imagen.startsWith("data:image/")) {
        imagenGuardada = existente.imagen;
    } else if (datos.imagen) {
        imagenGuardada = await crearCopiaImagen(datos.imagen) || (mismaImagen ? existente.imagen : "");
    }
    botonGuardar.disabled = false;
    if (datos.imagen && !imagenGuardada) {
        document.getElementById("mensaje-menu").textContent = "No se pudo guardar una copia local. Usa una URL directa y un servidor que permita guardar la imagen.";
        actualizarVistaPreviaImagen();
        return;
    }

    datos.imagenUrl = datos.imagen;
    datos.imagenEliminada = false;
    datos.imagen = imagenGuardada;

    const productosAnteriores = productos;
    if (productoEditando) {
        productos = productos.map(producto => producto.id === productoEditando
            ? { ...producto, ...datos }
            : producto);
    } else {
        productos.push({ id: Date.now(), activo: true, ...datos });
    }

    if (!guardarProductos()) {
        productos = productosAnteriores;
        document.getElementById("mensaje-menu").textContent = "No hay espacio disponible para guardar la imagen en este navegador.";
        return;
    }
    limpiarFormulario();
    mostrarFiltros();
    mostrarCatalogo();
    document.getElementById("mensaje-menu").textContent = "Cambios guardados.";
});

function editarProducto(id) {
    const producto = productos.find(item => item.id === id);
    if (!producto) return;
    productoEditando = id;
    document.getElementById("nombre").value = producto.nombre;
    document.getElementById("precio").value = producto.precio;
    cargarCategorias(producto.categoria);
    document.getElementById("ingredientes").value = producto.ingredientes.join(", ");
    document.getElementById("receta").value = serializarReceta(producto);
    document.getElementById("imagen-producto").value = producto.imagenUrl || (producto.imagen.startsWith("data:image/") ? "" : producto.imagen);
    document.getElementById("imagen-activa").checked = producto.imagenActiva;
    actualizarVistaPreviaImagen();
    document.getElementById("guardar-producto").textContent = "Guardar cambios";
    document.getElementById("cancelar-edicion").classList.remove("oculto");
    document.getElementById("nombre").focus();
}

function limpiarFormulario() {
    productoEditando = null;
    document.getElementById("formulario-producto").reset();
    document.getElementById("guardar-producto").textContent = "Agregar producto";
    document.getElementById("cancelar-edicion").classList.add("oculto");
    document.getElementById("mensaje-menu").textContent = "";
    cargarCategorias();
    actualizarVistaPreviaImagen();
}

function cargarCategorias(categoriaSeleccionada = "") {
    const disponibles = categorias.filter(categoria => categoria.activa || categoria.nombre === categoriaSeleccionada);
    document.getElementById("categoria").innerHTML = disponibles
        .map(categoria => `<option value="${escaparCatalogo(categoria.nombre)}">${escaparCatalogo(categoria.nombre)}${categoria.activa ? "" : " (inactiva)"}</option>`)
        .join("");
}

function cambiarEstadoProducto(id) {
    productos = productos.map(producto => producto.id === id ? { ...producto, activo: !producto.activo } : producto);
    guardarProductos();
    mostrarCatalogo();
}

function eliminarProductoMenu(id) {
    if (!window.confirm("¿Eliminar este plato del menú? Esta acción no se puede deshacer.")) return;
    productos = productos.filter(producto => producto.id !== id);
    guardarProductos();
    mostrarCatalogo();
}

function alternarImagenProducto(id) {
    productos = productos.map(producto => producto.id === id ? { ...producto, imagenActiva: !producto.imagenActiva } : producto);
    guardarProductos();
    mostrarCatalogo();
}

function eliminarImagenProducto(id) {
    productos = productos.map(producto => producto.id === id ? { ...producto, imagen: "", imagenUrl: "", imagenRespaldo: "", imagenEliminada: true, imagenActiva: false } : producto);
    guardarProductos();
    mostrarCatalogo();
}

function eliminarPrecioProducto(id) {
    productos = productos.map(producto => producto.id === id ? { ...producto, precio: null } : producto);
    guardarProductos();
    mostrarCatalogo();
}

document.getElementById("filtros").addEventListener("click", evento => {
    const boton = evento.target.closest("button[data-categoria]");
    if (!boton) return;
    categoriaActual = boton.dataset.categoria;
    mostrarFiltros();
    mostrarCatalogo();
});

document.getElementById("buscar-producto").addEventListener("input", evento => {
    busqueda = evento.target.value.trim().toLowerCase();
    mostrarCatalogo();
});

document.getElementById("catalogo").addEventListener("click", evento => {
    const boton = evento.target.closest("button[data-accion]");
    if (!boton) return;
    const id = Number(boton.dataset.id);
    if (boton.dataset.accion === "editar") editarProducto(id);
    if (boton.dataset.accion === "estado") cambiarEstadoProducto(id);
    if (boton.dataset.accion === "precio-eliminar") eliminarPrecioProducto(id);
    if (boton.dataset.accion === "eliminar") eliminarProductoMenu(id);
    if (boton.dataset.accion === "imagen") alternarImagenProducto(id);
    if (boton.dataset.accion === "imagen-eliminar") eliminarImagenProducto(id);
});

document.getElementById("cancelar-edicion").addEventListener("click", limpiarFormulario);

document.getElementById("imagen-producto").addEventListener("change", () => {
    const campoImagen = document.getElementById("imagen-producto");
    if (campoImagen.value) document.getElementById("imagen-activa").checked = true;
    actualizarVistaPreviaImagen();
});
document.getElementById("imagen-producto").addEventListener("input", actualizarVistaPreviaImagen);
document.getElementById("guardar-imagenes-carpeta").addEventListener("click", guardarImagenesEnCarpeta);
window.addEventListener(window.RequestyInventario.eventoInventario, mostrarCatalogo);

cargarCategorias();
mostrarFiltros();
mostrarCatalogo();
migrarImagenesExistentes();

document.addEventListener('DOMContentLoaded', () => {
    // Referencias a los contenedores de vista
    const vistaReportes = document.getElementById('vista-reportes');
    const vistaMenu = document.getElementById('vista-menu');
    const vistaPqrs = document.getElementById('vista-pqrs');
    const vistaCuentas = document.getElementById('vista-cuentas');
    const vistaInventario = document.getElementById('vista-inventario');

    // Referencias a los botones
    const btnAdministrarMenu = document.getElementById('btn-administrar-menu');
    const btnVolverMenu = document.getElementById('btn-volver-reportes');
    const btnVerPqrs = document.getElementById('btn-ver-pqrs');
    const btnVolverPqrs = document.getElementById('btn-volver-reportes-pqrs');
    const btnVerCuentas = document.getElementById('btn-ver-cuentas');
    const btnVolverCuentas = document.getElementById('volver-reportes-cuentas');
    const btnAdministrarInventario = document.getElementById('btn-administrar-inventario');
    const btnVolverInventario = document.getElementById('btn-volver-inventario');
    const btnReiniciarProcesos = document.getElementById('btn-reiniciar-procesos');

    // Función para cambiar de vista activa
    function mostrarVista(vistaAMostrar) {
        vistaReportes.classList.add('oculto');
        vistaMenu.classList.add('oculto');
        vistaPqrs.classList.add('oculto');
        vistaCuentas.classList.add('oculto');
        vistaInventario.classList.add('oculto');

        vistaAMostrar.classList.remove('oculto');
        if (vistaAMostrar === vistaCuentas) {
            history.replaceState(null, '', '#cuentas');
        } else if (location.hash === '#cuentas') {
            history.replaceState(null, '', location.pathname);
        }
    }

    // Eventos para Administrar Menú
    if (btnAdministrarMenu) {
        btnAdministrarMenu.addEventListener('click', () => mostrarVista(vistaMenu));
    }
    if (btnVolverMenu) {
        btnVolverMenu.addEventListener('click', () => mostrarVista(vistaReportes));
    }

    // Eventos para Registro de PQRS
    if (btnVerPqrs) {
        btnVerPqrs.addEventListener('click', () => mostrarVista(vistaPqrs));
    }
    if (btnVolverPqrs) {
        btnVolverPqrs.addEventListener('click', () => mostrarVista(vistaReportes));
    }

    if (btnVerCuentas && !btnVerCuentas.hidden) {
        btnVerCuentas.addEventListener('click', () => mostrarVista(vistaCuentas));
    }
    if (btnVolverCuentas) {
        btnVolverCuentas.addEventListener('click', () => mostrarVista(vistaReportes));
    }
    if (btnAdministrarInventario && !btnAdministrarInventario.hidden) {
        btnAdministrarInventario.addEventListener('click', () => mostrarVista(vistaInventario));
    }
    if (btnVolverInventario) {
        btnVolverInventario.addEventListener('click', () => mostrarVista(vistaReportes));
    }
    if (btnReiniciarProcesos) {
        btnReiniciarProcesos.addEventListener('click', reiniciarProcesosRestaurante);
    }

    if (location.hash === '#cuentas' && btnVerCuentas && !btnVerCuentas.hidden) {
        mostrarVista(vistaCuentas);
    }
});

function reiniciarProcesosRestaurante() {
    const datosSesion = sessionStorage.getItem('requestySesion') || localStorage.getItem('requestySesion');
    let sesion;
    try {
        sesion = JSON.parse(datosSesion || 'null');
    } catch {
        sesion = null;
    }
    const rol = String(sesion?.rol || '').trim().normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    if (rol !== 'admin' && rol !== 'administrador') {
        window.alert('Solo un administrador puede reiniciar los procesos.');
        return;
    }

    const confirmado = window.confirm(
        '¿Iniciar un nuevo día? Se eliminarán todos los pedidos, asignaciones de mesas y carritos. Las facturas anteriores se conservarán como historial, pero no se contarán en los totales del nuevo día. También se conservarán las cuentas, el menú y el inventario.'
    );
    if (!confirmado) return;

    const clavePedidos = 'requestyPedidos';
    const claveMesas = 'requestyMesas';
    const claveInicioNuevoDia = 'requestyInicioNuevoDia';
    const prefijoCarrito = 'requestyCarrito:';
    const cambios = new Map([
        [clavePedidos, '[]'],
        [claveInicioNuevoDia, new Date().toISOString()]
    ]);
    let mesasGuardadas;
    try {
        mesasGuardadas = localStorage.getItem(claveMesas);
    } catch (error) {
        window.alert(`No se pudieron leer los datos del restaurante; no se modificó nada. ${error.message || ''}`);
        return;
    }

    if (mesasGuardadas !== null) {
        let mesas;
        try {
            mesas = JSON.parse(mesasGuardadas);
        } catch {
            window.alert('No se pudo reiniciar: los datos de las mesas están dañados y no se modificó nada.');
            return;
        }
        if (!Array.isArray(mesas) || mesas.some(mesa => !mesa || typeof mesa !== 'object' || Array.isArray(mesa))) {
            window.alert('No se pudo reiniciar: los datos de las mesas no tienen un formato válido y no se modificó nada.');
            return;
        }
        cambios.set(claveMesas, JSON.stringify(mesas.map(mesa => ({
            ...mesa,
            estado: 'libre',
            clientes: 0,
            pedidos: 0,
            tiempo: 0,
            clienteNombre: '',
            clienteCorreo: ''
        }))));
    }

    const clavesCarrito = [];
    const anteriores = new Map();
    try {
        for (let indice = 0; indice < localStorage.length; indice += 1) {
            const clave = localStorage.key(indice);
            if (clave && clave.startsWith(prefijoCarrito)) clavesCarrito.push(clave);
        }
        clavesCarrito.forEach(clave => cambios.set(clave, null));
        cambios.forEach((valor, clave) => anteriores.set(clave, localStorage.getItem(clave)));
    } catch (error) {
        window.alert(`No se pudieron preparar los datos para el reinicio; no se modificó nada. ${error.message || ''}`);
        return;
    }

    try {
        cambios.forEach((valor, clave) => {
            if (valor === null) localStorage.removeItem(clave);
            else localStorage.setItem(clave, valor);
        });
    } catch (error) {
        let falloAlRestaurar = false;
        anteriores.forEach((valor, clave) => {
            try {
                if (valor === null) localStorage.removeItem(clave);
                else localStorage.setItem(clave, valor);
            } catch {
                falloAlRestaurar = true;
            }
        });
        const detalle = falloAlRestaurar
            ? ' No fue posible restaurar todos los datos anteriores; verifica el estado de los pedidos y las mesas.'
            : '';
        window.alert(`No se pudieron reiniciar los procesos. ${error.message || 'Error de almacenamiento.'}${detalle}`);
        return;
    }

    window.dispatchEvent(new CustomEvent('requesty-pedidos-actualizados', { detail: [] }));
    if (cambios.has(claveMesas)) {
        window.dispatchEvent(new CustomEvent('requesty-mesas-actualizadas', {
            detail: JSON.parse(cambios.get(claveMesas))
        }));
    }
    window.dispatchEvent(new CustomEvent('requesty-procesos-reiniciados'));
    window.alert('Nuevo día iniciado. Las facturas anteriores se conservaron en el historial y se excluyen del cierre de caja y de los reportes del nuevo día.');
}