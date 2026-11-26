const productosDeEjemplo = [
    { id: 1, nombre: "Caldo de costilla", precio: 8000, categoria: "Desayuno", imagen: "img/tarjeta/caldo-de-costilla.jpg", ingredientes: ["Costilla", "Papa", "Cilantro", "Agua Filtrada", "Sal"], receta: { costilla: 200, papa: 250, cilantro: 5, "agua filtrada": 0.5, sal: 3 } },
    { id: 3, nombre: "Huevos pericos", precio: 7000, categoria: "Desayuno", imagen: "img/tarjeta/huevos-pericos.jpg", ingredientes: ["Huevos", "Tomate", "Cebolla", "Sal"], receta: { huevos: 2, tomate: 60, cebolla: 40, sal: 3 } },
    { id: 4, nombre: "Bandeja paisa", precio: 30000, categoria: "Almuerzo", imagen: "img/tarjeta/bandeja-paisa.jpg", ingredientes: ["Arroz", "Plátano", "Tocineta", "Carne molida", "Arepa", "Aguacate", "Chorizo", "Huevos", "Fríjoles"], receta: { arroz: 150, platano: 100, tocineta: 50, "carne molida": 100, arepa: 1, aguacate: 80, chorizo: 1, huevos: 1, frijoles: 150 } },
    { id: 2, nombre: "Jugo de mora", precio: 6000, categoria: "Bebida", imagen: "img/tarjeta/jugo-de-mora.jpg", ingredientes: ["Moras", "Azúcar"], receta: { moras: 80, azucar: 20 }, recetaAlternativas: [{ nombre: "Base líquida", opciones: [{ ingrediente: "Agua Filtrada", cantidad: 0.25 }, { ingrediente: "Leche", cantidad: 0.25 }] }] }
];

const equivalenciasCategoria = {
    desayunos: "Desayuno",
    almuerzos: "Almuerzo",
    bebidas: "Bebida"
};
const contenedorCatalogo = document.getElementById("catalogo-menu");
const filtrosCatalogo = document.getElementById("filtros-menu");
const campoBusquedaMenu = document.getElementById("buscar-menu");
const dialogoProducto = document.getElementById("detalle-producto");
const sesionCatalogo = typeof leerSesion === "function" ? leerSesion() : null;
const rolCatalogo = window.RequestyInventario.normalizarNombre(sesionCatalogo?.rol);
const puedeComprar = rolCatalogo === "mesero";
let mesaClienteActual = null;
let carrito = [];
let filtroActual = "Todas";
let busquedaActual = "";

function leerCatalogoProductos() {
    try {
        const datosGuardados = localStorage.getItem("productos-restaurante");
        const guardados = datosGuardados === null ? productosDeEjemplo : JSON.parse(datosGuardados);
        if (!Array.isArray(guardados)) throw new Error("El catálogo guardado no tiene un formato válido.");
        const inventario = window.RequestyInventario.leerInventario();
        document.getElementById("mensaje-menu-inventario").textContent = "";
        const categoriasGuardadas = JSON.parse(localStorage.getItem("categorias-restaurante") || "null");
        const categoriasActivas = Array.isArray(categoriasGuardadas)
            ? new Set(categoriasGuardadas.filter((categoria) => categoria.activa !== false).map((categoria) => categoria.nombre))
            : new Set(productosDeEjemplo.map((producto) => producto.categoria));
        return guardados.map((producto, indice) => {
            const categoriaOriginal = String(producto.categoria || "Otros");
            const categoria = equivalenciasCategoria[categoriaOriginal.toLowerCase()] || categoriaOriginal;
            const productoBase = productosDeEjemplo.find((base) => String(base.id) === String(producto.id));
            const imagenGuardada = String(producto.imagen || "");
            const imagenUrl = String(producto.imagenUrl || (imagenGuardada.startsWith("data:image/") || imagenGuardada.startsWith("img/tarjeta/") ? "" : imagenGuardada));
            const imagenRespaldo = producto.imagenEliminada ? "" : productoBase?.imagen || "";
            const ingredientes = Array.isArray(producto.ingredientes)
                ? producto.ingredientes
                : String(producto.ingredientes || "").split(",");
            const datosDisponibilidad = { ...producto, ingredientes };
            return {
                id: producto.id ?? indice,
                nombre: String(producto.nombre || "Producto"),
                precio: producto.precio === null || producto.precio === "" ? null : Number(producto.precio),
                categoria,
                ingredientes: ingredientes.map((ingrediente) => String(ingrediente).trim()).filter(Boolean),
                ingredientesAgotados: window.RequestyInventario.obtenerIngredientesAgotados(datosDisponibilidad, inventario),
                disponible: window.RequestyInventario.productoDisponible(datosDisponibilidad, inventario),
                imagen: (esImagenLocalSegura(imagenGuardada) || esRutaImagenBase(imagenGuardada)
                    ? imagenGuardada
                    : esURLImagenSegura(imagenUrl)
                        ? imagenUrl
                    : "") || imagenRespaldo,
                imagenRespaldo,
                imagenActiva: producto.imagenActiva !== false,
                activo: producto.activo !== false,
                receta: producto.receta,
                recetaAlternativas: producto.recetaAlternativas || []
            };
        }).filter((producto) => producto.nombre && producto.ingredientes.length && producto.activo
            && categoriasActivas.has(producto.categoria));
    } catch (error) {
        document.getElementById("mensaje-menu-inventario").textContent = error.message || "No se pudo verificar la disponibilidad. Intenta de nuevo más tarde.";
        return productosDeEjemplo.map(producto => ({ ...producto, disponible: false, ingredientesAgotados: [] }));
    }
}

function esURLImagenSegura(valor) {
    if (!valor) return false;
    try {
        return ["http:", "https:"].includes(new URL(valor).protocol);
    } catch {
        return false;
    }
}

function esImagenLocalSegura(valor) {
    return /^data:image\/(?:webp|png|jpeg);base64,[a-z0-9+/=]+$/i.test(String(valor || ""));
}

function esRutaImagenBase(valor) {
    return /^img\/tarjeta\/[a-z0-9-]+\.(?:jpg|jpeg|png|webp)$/i.test(String(valor || ""));
}

function escaparHTML(valor) {
    return String(valor).replace(/[&<>"']/g, (caracter) => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    })[caracter]);
}

function normalizarEstadoSeguimiento(estado) {
    const normalizado = String(estado || "").trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLocaleLowerCase("es");
    if (["nuevo", "pendiente", "en cola"].includes(normalizado)) return "En cola";
    if (["preparando", "en preparacion"].includes(normalizado)) return "En preparación";
    if (["listo", "listo para servir"].includes(normalizado)) return "Listo para servir";
    if (normalizado === "entregado") return "Entregado";
    if (normalizado === "cancelado") return "Cancelado";
    return "En cola";
}

function buscarMesaCliente() {
    const correo = String(sesionCatalogo?.correo || "").trim().toLocaleLowerCase("es");
    if (rolCatalogo !== "cliente" || !correo) return null;
    try {
        const mesas = JSON.parse(localStorage.getItem("requestyMesas") || "[]");
        if (!Array.isArray(mesas)) throw new Error("La lista de mesas tiene un formato inválido.");
        return mesas.find(mesa => mesa.estado === "ocupada"
            && String(mesa.clienteCorreo || "").trim().toLocaleLowerCase("es") === correo) || null;
    } catch (error) {
        document.getElementById("mensaje-menu-inventario").textContent = error.message || "No se pudo verificar la asignación de mesa.";
        return null;
    }
}

function actualizarAccesoCliente() {
    mesaClienteActual = buscarMesaCliente();
    const asignada = rolCatalogo !== "cliente" || Boolean(mesaClienteActual);
    document.getElementById("bloqueo-cliente-mesa").hidden = asignada;
    document.querySelector(".catalogo-controles").hidden = !asignada;
    document.getElementById("conteo-menu").hidden = !asignada;
    document.getElementById("catalogo-menu").hidden = !asignada;
    if (rolCatalogo === "cliente") document.getElementById("seguimiento-pedidos").hidden = !mesaClienteActual;
    if (rolCatalogo === "cliente" && mesaClienteActual) {
        document.getElementById("bloqueo-cliente-mesa").textContent =
            `Mesa ${mesaClienteActual.numero} asignada${mesaClienteActual.clienteNombre ? ` a ${mesaClienteActual.clienteNombre}` : ""}. Puedes consultar el menú y recibir avisos; el mesero registra tu pedido.`;
    }
    return asignada;
}

let estadosPedidosAnteriores = new Map();

function renderizarSeguimientoPedidos(datosGuardados = localStorage.getItem("requestyPedidos"), anunciarCambios = false) {
    const seccion = document.getElementById("seguimiento-pedidos");
    if (rolCatalogo !== "cliente") {
        seccion.hidden = true;
        return;
    }
    seccion.hidden = !mesaClienteActual;
    if (!mesaClienteActual) return;
    const correo = String(sesionCatalogo?.correo || "").trim().toLocaleLowerCase("es");
    try {
        const pedidos = JSON.parse(datosGuardados || "[]");
        if (!Array.isArray(pedidos)) throw new Error("La lista de pedidos no tiene un formato válido.");
        const propios = pedidos.filter(pedido => {
            const correoPedido = String(pedido.customerEmail || "").trim().toLocaleLowerCase("es");
            return correoPedido === correo
                || (!correoPedido && Number(pedido.tableNumber) === Number(mesaClienteActual.numero));
        });
        const nuevosEstados = new Map(propios.map(pedido => [String(pedido.id), normalizarEstadoSeguimiento(pedido.status || pedido.estado)]));
        const mensaje = document.getElementById("mensaje-seguimiento-pedidos");
        const pedidoListo = propios.find(pedido => nuevosEstados.get(String(pedido.id)) === "Listo para servir");
        const pedidoEntregado = propios.find(pedido => nuevosEstados.get(String(pedido.id)) === "Entregado"
            && !pedido.paidAt && !pedido.invoiceId);
        if (pedidoListo) {
            const cambioAListo = estadosPedidosAnteriores.get(String(pedidoListo.id)) !== "Listo para servir";
            mensaje.textContent = `¡Tu pedido ${pedidoListo.id} está listo para servir! El mesero ya puede entregártelo.`;
            if (anunciarCambios && cambioAListo && "Notification" in window && Notification.permission === "granted") {
                new Notification("Restaurante Aqui es", { body: `Tu pedido ${pedidoListo.id} está listo para servir.` });
            }
        } else if (pedidoEntregado) {
            mensaje.textContent = `El pedido ${pedidoEntregado.id} fue entregado. Ya puedes ir a caja para pagarlo.`;
        } else if (propios.some(pedido => nuevosEstados.get(String(pedido.id)) === "Entregado")) {
            mensaje.textContent = "";
        }
        estadosPedidosAnteriores = nuevosEstados;
        document.getElementById("lista-seguimiento-pedidos").innerHTML = propios.length
            ? propios.map(pedido => {
                const estado = normalizarEstadoSeguimiento(pedido.status || pedido.estado);
                const clase = estado === "Listo para servir" ? "estado-listo"
                    : estado === "Cancelado" ? "estado-cancelado" : "";
                const pendienteDePago = estado === "Entregado" && !pedido.paidAt && !pedido.invoiceId;
                const historialEstado = estado === "Entregado" && (pedido.paidAt || pedido.invoiceId)
                    ? "Pagado"
                    : estado;
                const irACaja = pendienteDePago
                    ? `<a class="seguimiento-ir-caja" href="index.php?page=caja&pedido=${encodeURIComponent(pedido.id)}">Ir a caja para pagar</a>`
                    : "";
                return `<article class="seguimiento-pedido"><strong>Pedido ${escaparHTML(pedido.id)}</strong><span>${escaparHTML(pedido.destination || pedido.channel || "")}</span><span class="seguimiento-estado ${clase}">${escaparHTML(historialEstado)}</span>${irACaja}</article>`;
            }).join("")
            : "<p>Aún no hay pedidos asociados a tu correo.</p>";
    } catch (error) {
        document.getElementById("mensaje-seguimiento-pedidos").textContent = error.message || "No se pudo consultar el estado de tus pedidos.";
        document.getElementById("lista-seguimiento-pedidos").replaceChildren();
    }
}

let detalleProductoActual = null;
let detalleCantidadActual = 1;

function formatearPrecio(precio) {
    return new Intl.NumberFormat("es-CO", { maximumFractionDigits: 0 }).format(precio);
}

function obtenerIconoCategoria(categoria) {
    const cat = String(categoria || "").toLowerCase();
    if (cat.includes("toda") || cat.includes("all")) return "fa-utensils";
    if (cat.includes("desayuno")) return "fa-egg";
    if (cat.includes("almuerzo") || cat.includes("comida")) return "fa-bowl-food";
    if (cat.includes("cena")) return "fa-plate-wheat";
    if (cat.includes("bebida") || cat.includes("jugo") || cat.includes("juice")) return "fa-glass-water";
    if (cat.includes("postre") || cat.includes("dulce")) return "fa-cake-candles";
    if (cat.includes("hamburguesa") || cat.includes("burger")) return "fa-burger";
    if (cat.includes("sopa") || cat.includes("caldo")) return "fa-mug-hot";
    if (cat.includes("carne") || cat.includes("asado")) return "fa-drumstick-bite";
    return "fa-kitchen-set";
}

function renderizarFiltros(productos) {
    const categorias = [...new Set(productos.map((producto) => producto.categoria))];
    const opciones = ["Todas", ...categorias];
    if (!opciones.includes(filtroActual)) filtroActual = "Todas";

    const conteos = {
        "Todas": productos.length
    };
    productos.forEach(p => {
        conteos[p.categoria] = (conteos[p.categoria] || 0) + 1;
    });

    filtrosCatalogo.innerHTML = opciones.map((categoria) => {
        const esActiva = categoria === filtroActual;
        const total = conteos[categoria] || 0;
        const icono = obtenerIconoCategoria(categoria);
        return `
            <button type="button" class="categoria-kiosk-btn ${esActiva ? "activo" : ""}" data-categoria="${escaparHTML(categoria)}" aria-pressed="${esActiva}">
                <div class="categoria-kiosk-icono">
                    <i class="fa-solid ${icono}" aria-hidden="true"></i>
                </div>
                <div class="categoria-kiosk-textos">
                    <strong class="categoria-kiosk-nombre">${escaparHTML(categoria)}</strong>
                    <span class="categoria-kiosk-conteo">${total} ${total === 1 ? "ítem" : "ítems"}</span>
                </div>
            </button>
        `;
    }).join("");
}

function renderizarCatalogo() {
    const productos = leerCatalogoProductos();
    renderizarFiltros(productos);
    const visibles = productos.filter((producto) => {
        const categoriaCoincide = filtroActual === "Todas" || producto.categoria === filtroActual;
        const texto = `${producto.nombre} ${producto.categoria} ${producto.ingredientes.join(" ")}`.toLocaleLowerCase("es");
        return categoriaCoincide && texto.includes(busquedaActual);
    });

    const cantidadDisponibles = visibles.filter(producto => producto.disponible).length;
    const conteoEl = document.getElementById("conteo-menu");
    if (conteoEl) {
        conteoEl.textContent = `${visibles.length} ${visibles.length === 1 ? "plato" : "platos"} en el menú · ${cantidadDisponibles} ${cantidadDisponibles === 1 ? "disponible" : "disponibles"}`;
    }

    if (!visibles.length) {
        contenedorCatalogo.innerHTML = '<p class="catalogo-vacio">No encontramos productos con esos filtros.</p>';
        return;
    }

    contenedorCatalogo.innerHTML = visibles.map((producto, index) => {
        const faltaStock = producto.ingredientesAgotados.length > 0;
        const mensajeDisponibilidad = producto.disponible
            ? "Disponible"
            : faltaStock
                ? `No Disponible · Falta stock de: ${producto.ingredientesAgotados.join(", ")}`
                : "No Disponible · No se pudo verificar el inventario";
        
        const esRecomendado = index % 3 === 0;
        const esDescuento = index === 1;

        return `
            <article class="plato ${producto.disponible ? "" : "agotado"}" data-producto-card="${escaparHTML(producto.id)}">
                <div class="plato-imagen-contenedor">
                    ${producto.imagen && producto.imagenActiva ? `<img class="catalogo-imagen" src="${escaparHTML(producto.imagen)}" data-respaldo="${escaparHTML(producto.imagenRespaldo)}" alt="${escaparHTML(producto.nombre)}" loading="lazy">` : `<div class="catalogo-imagen-placeholder"><i class="fa-solid fa-utensils"></i></div>`}
                    <div class="plato-overlay-badges">
                        ${esRecomendado && producto.disponible ? `<span class="plato-badge-kiosk badge-recomendado"><i class="fa-solid fa-star"></i> Recomendado</span>` : ""}
                        ${esDescuento && producto.disponible ? `<span class="plato-badge-kiosk badge-descuento"><i class="fa-solid fa-tag"></i> 20% OFF</span>` : ""}
                    </div>
                </div>
                <div class="plato-info">
                    <div class="plato-info-top">
                        <span class="plato-categoria">${escaparHTML(producto.categoria)}</span>
                        <span class="estado-disponibilidad ${producto.disponible ? "" : "agotado"}" role="status">${escaparHTML(mensajeDisponibilidad)}</span>
                    </div>
                    <div class="plato-titulo">
                        <h2 class="${producto.disponible ? "" : "agotado"}">${escaparHTML(producto.nombre)}</h2>
                        <span class="plato-precio ${producto.disponible ? "" : "agotado"}">${producto.precio === null ? "Sin precio" : `$${formatearPrecio(producto.precio)}`}</span>
                    </div>
                    <p class="plato-ingredientes"><strong>Ingredientes:</strong> ${escaparHTML(producto.ingredientes.join(", "))}</p>
                    <div class="plato-acciones-kiosk">
                        <button class="plato-detalle" type="button" data-producto="${escaparHTML(producto.id)}">
                            <i class="fa-solid fa-bars-staggered"></i> Ver detalle
                        </button>
                        ${puedeComprar ? `<button class="agregar-carrito ${producto.disponible ? "" : "sin-stock"}" type="button" data-agregar-carrito="${escaparHTML(producto.id)}" ${producto.disponible ? "" : "disabled"} aria-label="${escaparHTML(producto.disponible ? `Agregar ${producto.nombre} al pedido` : `${producto.nombre} no disponible. ${mensajeDisponibilidad}`)}" title="${escaparHTML(mensajeDisponibilidad)}">${producto.disponible ? "+ Pedir" : "Falta de stock"}</button>` : ""}
                    </div>
                </div>
            </article>`;
    }).join("");

    contenedorCatalogo.querySelectorAll(".catalogo-imagen").forEach((imagen) => {
        imagen.addEventListener("error", () => {
            const respaldo = imagen.dataset.respaldo;
            if (respaldo && imagen.src !== new URL(respaldo, location.href).href) {
                imagen.src = respaldo;
                return;
            }
            const aviso = document.createElement("p");
            aviso.className = "catalogo-imagen-error";
            aviso.textContent = "Imagen no disponible";
            imagen.replaceWith(aviso);
        }, { once: true });
    });
}

function actualizarTotalesDrawer() {
    if (!detalleProductoActual) return;
    const precioUnitario = Number(detalleProductoActual.precio) || 0;
    const subtotal = precioUnitario * detalleCantidadActual;
    const precioBaseEl = document.getElementById("ticket-precio-base");
    const unidadesEl = document.getElementById("ticket-unidades");
    const totalMontoEl = document.getElementById("ticket-total-monto");
    const btnPrecioTotalEl = document.getElementById("drawer-btn-precio-total");
    const qtyValEl = document.getElementById("drawer-qty-val");

    if (qtyValEl) qtyValEl.textContent = detalleCantidadActual;
    if (precioBaseEl) precioBaseEl.textContent = `$${formatearPrecio(precioUnitario)}`;
    if (unidadesEl) unidadesEl.textContent = `${detalleCantidadActual}x`;
    if (totalMontoEl) totalMontoEl.textContent = `$${formatearPrecio(subtotal)}`;
    if (btnPrecioTotalEl) btnPrecioTotalEl.textContent = `$${formatearPrecio(subtotal)}`;
}

function mostrarDetalle(id) {
    const producto = leerCatalogoProductos().find((item) => String(item.id) === String(id));
    if (!producto) return;
    detalleProductoActual = producto;
    detalleCantidadActual = 1;

    // Header del drawer: Mesa, Cliente y Fecha/Hora
    const mesaBadgeEl = document.getElementById("drawer-mesa-badge");
    if (mesaBadgeEl) {
        mesaBadgeEl.textContent = mesaClienteActual ? `Mesa ${mesaClienteActual}` : (sesionCatalogo?.rol === "mesero" ? "Modo Mesero" : "Mesa 1");
    }
    const clienteNombreEl = document.getElementById("drawer-cliente-nombre");
    if (clienteNombreEl) {
        clienteNombreEl.textContent = sesionCatalogo?.nombre || "Cliente en sala";
    }
    const fechaEl = document.getElementById("drawer-fecha");
    if (fechaEl) {
        const ahora = new Date();
        const opcionesHora = { weekday: "short", month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" };
        fechaEl.textContent = ahora.toLocaleDateString("es-CO", opcionesHora);
    }

    // Categoría y Estado
    const categoriaEl = document.getElementById("categoria-detalle-producto");
    if (categoriaEl) categoriaEl.textContent = producto.categoria;
    
    const estadoEl = document.getElementById("estado-detalle-producto");
    if (estadoEl) {
        estadoEl.textContent = producto.disponible
            ? "Disponible"
            : producto.ingredientesAgotados.length
                ? `No Disponible · Falta stock: ${producto.ingredientesAgotados.join(", ")}`
                : "No Disponible · Sin inventario";
        estadoEl.className = `estado-disponibilidad ${producto.disponible ? "" : "agotado"}`;
    }

    // Título y Precio
    const nombreEl = document.getElementById("nombre-detalle-producto");
    if (nombreEl) {
        nombreEl.textContent = producto.nombre;
        nombreEl.classList.toggle("agotado", !producto.disponible);
    }
    const precioEl = document.getElementById("precio-detalle-producto");
    if (precioEl) {
        precioEl.textContent = producto.precio === null ? "Sin precio" : `$${formatearPrecio(producto.precio)}`;
        precioEl.classList.toggle("agotado", !producto.disponible);
    }

    // Imagen en el contenedor del drawer
    const imagenWrap = document.getElementById("drawer-imagen-wrap");
    if (imagenWrap) {
        imagenWrap.innerHTML = "";
        if (producto.imagen && producto.imagenActiva) {
            const img = document.createElement("img");
            img.className = "drawer-imagen-destacada";
            img.src = producto.imagen;
            img.alt = producto.nombre;
            img.dataset.respaldo = producto.imagenRespaldo;
            img.addEventListener("error", () => {
                if (img.dataset.respaldo && img.src !== new URL(img.dataset.respaldo, location.href).href) {
                    img.src = img.dataset.respaldo;
                }
            }, { once: true });
            imagenWrap.appendChild(img);
        } else {
            imagenWrap.innerHTML = `<div class="drawer-imagen-placeholder"><i class="fa-solid fa-utensils"></i></div>`;
        }
    }

    // Ingredientes en chips (estilo tablet POS)
    const tagsContainer = document.getElementById("drawer-tags-ingredientes");
    if (tagsContainer) {
        tagsContainer.innerHTML = producto.ingredientes.map(ing => `
            <span class="drawer-tag-chip"><i class="fa-solid fa-check"></i> ${escaparHTML(ing)}</span>
        `).join("");
    }

    // Actualizar ticket financiero
    actualizarTotalesDrawer();

    // Estado del botón de acción
    const btnAccion = document.getElementById("drawer-btn-accion");
    if (btnAccion) {
        btnAccion.disabled = !producto.disponible || !puedeComprar;
        btnAccion.title = !puedeComprar ? "Solo los meseros pueden pedir directamente desde el menú" : (!producto.disponible ? "Producto agotado" : "Agregar pedido");
        const btnTexto = document.getElementById("drawer-btn-texto");
        if (btnTexto) {
            btnTexto.innerHTML = producto.disponible ? `<i class="fa-solid fa-cart-plus"></i> Agregar al pedido` : `<i class="fa-solid fa-ban"></i> Falta stock`;
        }
    }

    if (dialogoProducto) {
        if (typeof dialogoProducto.showModal === "function") {
            dialogoProducto.showModal();
        } else {
            dialogoProducto.setAttribute("open", "");
        }
    }
}

function claveCarritoCliente() {
    const correo = String(sesionCatalogo?.correo || "").trim().toLocaleLowerCase("es");
    return correo ? `requestyCarrito:${encodeURIComponent(correo)}` : "";
}

function leerCarrito() {
    const clave = claveCarritoCliente();
    if (!clave || !puedeComprar) return [];
    try {
        const guardado = JSON.parse(localStorage.getItem(clave) || "[]");
        if (!Array.isArray(guardado)) throw new Error("El pedido guardado tiene un formato inválido.");
        return guardado.filter(item => item && item.id !== undefined)
            .map(item => ({ id: item.id, cantidad: Math.max(1, Math.min(99, Math.floor(Number(item.cantidad) || 1))) }));
    } catch (error) {
        document.getElementById("mensaje-pedido").textContent = error.message || "No se pudo recuperar tu pedido.";
        return [];
    }
}

function guardarCarrito() {
    const clave = claveCarritoCliente();
    if (!clave) throw new Error("Inicia sesión para guardar tu pedido.");
    localStorage.setItem(clave, JSON.stringify(carrito));
}

function agregarAlCarrito(id) {
    if (!puedeComprar) return;
    const producto = leerCatalogoProductos().find((item) => String(item.id) === String(id));
    if (!producto) return;
    if (!producto.disponible) {
        document.getElementById("mensaje-pedido").textContent = `${producto.nombre} no se puede agregar al pedido: ${producto.ingredientesAgotados.length ? `falta stock de ${producto.ingredientesAgotados.join(", ")}.` : "no se pudo verificar el inventario."}`;
        renderizarCatalogo();
        return;
    }
    if (!Number.isFinite(producto.precio) || producto.precio < 0) {
        document.getElementById("mensaje-pedido").textContent = `${producto.nombre} no tiene un precio válido para agregarse al pedido.`;
        return;
    }
    const item = carrito.find((actual) => String(actual.id) === String(producto.id));
    if (item) item.cantidad = Math.min(99, item.cantidad + 1);
    else carrito.push({ id: producto.id, cantidad: 1, terminoCoccion: "", ingredientesExcluidos: [], extras: [], personalizacionAbierta: false });
    try {
        guardarCarrito();
    } catch (error) {
        if (item) item.cantidad -= 1;
        else carrito.pop();
        document.getElementById("mensaje-pedido").textContent = error.message || "No se pudo guardar el pedido.";
        return;
    }
    document.getElementById("mensaje-pedido").textContent = `${producto.nombre} agregado al pedido. Ve a Pedidos para finalizarlo.`;
}

filtrosCatalogo.addEventListener("click", (evento) => {
    const boton = evento.target.closest("button[data-categoria]");
    if (!boton) return;
    filtroActual = boton.dataset.categoria;
    renderizarCatalogo();
});

campoBusquedaMenu.addEventListener("input", () => {
    busquedaActual = campoBusquedaMenu.value.trim().toLocaleLowerCase("es");
    renderizarCatalogo();
});

contenedorCatalogo.addEventListener("click", (evento) => {
    const agregar = evento.target.closest("button[data-agregar-carrito]");
    if (agregar) {
        agregarAlCarrito(agregar.dataset.agregarCarrito);
        return;
    }
    const boton = evento.target.closest("button[data-producto]");
    if (boton) mostrarDetalle(boton.dataset.producto);
});

document.getElementById("cerrar-detalle-producto").addEventListener("click", () => dialogoProducto.close());
dialogoProducto.addEventListener("click", (evento) => {
    if (evento.target === dialogoProducto) dialogoProducto.close();
});

const btnQtyMinus = document.getElementById("btn-qty-minus");
if (btnQtyMinus) {
    btnQtyMinus.addEventListener("click", () => {
        if (detalleCantidadActual > 1) {
            detalleCantidadActual -= 1;
            actualizarTotalesDrawer();
        }
    });
}

const btnQtyPlus = document.getElementById("btn-qty-plus");
if (btnQtyPlus) {
    btnQtyPlus.addEventListener("click", () => {
        if (detalleCantidadActual < 99) {
            detalleCantidadActual += 1;
            actualizarTotalesDrawer();
        }
    });
}

const btnDrawerAccion = document.getElementById("drawer-btn-accion");
if (btnDrawerAccion) {
    btnDrawerAccion.addEventListener("click", () => {
        if (!puedeComprar || !detalleProductoActual || !detalleProductoActual.disponible) return;
        const producto = detalleProductoActual;
        const item = carrito.find((actual) => String(actual.id) === String(producto.id));
        if (item) item.cantidad = Math.min(99, item.cantidad + detalleCantidadActual);
        else carrito.push({ id: producto.id, cantidad: detalleCantidadActual, terminoCoccion: "", ingredientesExcluidos: [], extras: [], personalizacionAbierta: false });
        try {
            guardarCarrito();
            dialogoProducto.close();
            document.getElementById("mensaje-pedido").textContent = `${detalleCantidadActual}x ${producto.nombre} agregado al pedido. Ve a Pedidos para finalizarlo.`;
        } catch (error) {
            if (item) item.cantidad -= detalleCantidadActual;
            else carrito.pop();
            document.getElementById("mensaje-pedido").textContent = error.message || "No se pudo guardar el pedido.";
        }
    });
}

window.addEventListener("storage", (evento) => {
    if (evento.key === "requestyPedidos") renderizarSeguimientoPedidos(evento.newValue, true);
    if (evento.key === "requestyMesas") {
        if (actualizarAccesoCliente()) renderizarCatalogo();
        renderizarSeguimientoPedidos();
    }
    if (evento.key === "productos-restaurante" || evento.key === "categorias-restaurante" || evento.key === window.RequestyInventario.claveInventario) {
        document.getElementById("mensaje-pedido").textContent = "";
        renderizarCatalogo();
    }
});

window.addEventListener(window.RequestyInventario.eventoInventario, () => {
    document.getElementById("mensaje-pedido").textContent = "";
    renderizarCatalogo();
});
window.addEventListener("requesty-pedidos-actualizados", evento => {
    if (evento.detail) renderizarSeguimientoPedidos(JSON.stringify(evento.detail), true);
});
window.addEventListener("requesty-mesas-actualizadas", () => {
    if (actualizarAccesoCliente()) renderizarCatalogo();
    renderizarSeguimientoPedidos();
});
carrito = leerCarrito();
const puedeVerMenu = actualizarAccesoCliente();
renderizarSeguimientoPedidos();
if (puedeVerMenu) renderizarCatalogo();
