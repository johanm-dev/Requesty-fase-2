// Catálogo digital Kiosk + Drawer Derecho conectado 100% a PostgreSQL (api/productos, api/mesas, api/pedidos)
(function () {
    const contenedorCatalogo = document.getElementById("catalogo-menu");
    const filtrosCatalogo = document.getElementById("filtros-menu");
    const campoBusquedaMenu = document.getElementById("buscar-menu");
    const dialogoProducto = document.getElementById("detalle-producto");
    if (!contenedorCatalogo || !filtrosCatalogo) return;

    let productosCache = [];
    let mesaClienteActual = null;
    let filtroActual = "Todas";
    let busquedaActual = "";
    let esCliente = false;
    let puedeCrearPedido = false;
    let detalleProductoActual = null;
    let detalleCantidadActual = 1;

    function esc(v) {
        return window.RequestyAPI.escaparHTML(v);
    }

    function formatearPrecio(precio) {
        return new Intl.NumberFormat("es-CO", { maximumFractionDigits: 0 }).format(Number(precio || 0));
    }

    function normalizarEstadoSeguimiento(estado) {
        const st = String(estado || "").toUpperCase();
        if (st === "PENDIENTE") return "En cola";
        if (st === "EN_PREPARACION") return "En preparación";
        if (st === "LISTO") return "Listo para servir";
        if (st === "ENTREGADO") return "Entregado";
        if (st === "PAGADO") return "Pagado";
        if (st === "CANCELADO") return "Cancelado";
        return st;
    }

    function esImagenSegura(valor) {
        const v = String(valor || "");
        return /^img\/tarjeta\/[a-z0-9-]+\.(?:jpg|jpeg|png|webp)$/i.test(v)
            || /^https?:\/\/.+/i.test(v)
            || /^data:image\/(?:webp|png|jpeg);base64,/i.test(v);
    }

    function obtenerIconoCategoria(categoria) {
        const cat = String(categoria || "").toLowerCase();
        if (cat.includes("toda") || cat.includes("all")) return "fa-utensils";
        if (cat.includes("desayuno")) return "fa-egg";
        if (cat.includes("almuerzo") || cat.includes("comida") || cat.includes("fuerte")) return "fa-bowl-food";
        if (cat.includes("cena")) return "fa-plate-wheat";
        if (cat.includes("bebida") || cat.includes("jugo") || cat.includes("juice")) return "fa-glass-water";
        if (cat.includes("postre") || cat.includes("dulce")) return "fa-cake-candles";
        if (cat.includes("hamburguesa") || cat.includes("burger")) return "fa-burger";
        if (cat.includes("sopa") || cat.includes("caldo") || cat.includes("entrada")) return "fa-mug-hot";
        if (cat.includes("carne") || cat.includes("asado")) return "fa-drumstick-bite";
        return "fa-kitchen-set";
    }

    function renderizarFiltros() {
        const categorias = [...new Set(productosCache.map((p) => p.categoria).filter(Boolean))];
        const opciones = ["Todas", ...categorias];
        if (!opciones.includes(filtroActual)) filtroActual = "Todas";

        const conteos = { Todas: productosCache.length };
        productosCache.forEach((p) => {
            const c = p.categoria || "General";
            conteos[c] = (conteos[c] || 0) + 1;
        });

        filtrosCatalogo.innerHTML = opciones.map((cat) => {
            const esActiva = cat === filtroActual;
            const total = conteos[cat] || 0;
            const icono = obtenerIconoCategoria(cat);
            return `
                <button type="button" class="categoria-kiosk-btn ${esActiva ? "activo" : ""}" data-categoria="${esc(cat)}" aria-pressed="${esActiva}">
                    <div class="categoria-kiosk-icono">
                        <i class="fa-solid ${icono}" aria-hidden="true"></i>
                    </div>
                    <div class="categoria-kiosk-textos">
                        <strong class="categoria-kiosk-nombre">${esc(cat)}</strong>
                        <span class="categoria-kiosk-conteo">${total} ${total === 1 ? "ítem" : "ítems"}</span>
                    </div>
                </button>`;
        }).join("");
    }

    function renderizarCatalogo() {
        renderizarFiltros();
        const visibles = productosCache.filter((producto) => {
            const catOk = filtroActual === "Todas" || producto.categoria === filtroActual;
            const ingTxt = (producto.ingredientes || []).join(" ");
            const txt = `${producto.nombre} ${producto.categoria} ${ingTxt}`.toLocaleLowerCase("es");
            return catOk && txt.includes(busquedaActual);
        });

        const disponiblesCount = visibles.filter((p) => p.disponible_stock).length;
        const conteoEl = document.getElementById("conteo-menu");
        if (conteoEl) {
            conteoEl.textContent = `${visibles.length} ${visibles.length === 1 ? "plato" : "platos"} en el menú · ${disponiblesCount} ${disponiblesCount === 1 ? "disponible" : "disponibles"}`;
        }

        if (!visibles.length) {
            contenedorCatalogo.innerHTML = '<p class="catalogo-vacio">No encontramos productos con esos filtros.</p>';
            return;
        }

        contenedorCatalogo.innerHTML = visibles.map((producto, index) => {
            const disponible = Boolean(producto.disponible_stock);
            const agotados = Array.isArray(producto.ingredientes_agotados) ? producto.ingredientes_agotados : [];
            const msgDisp = disponible
                ? "Disponible"
                : agotados.length
                    ? `No Disponible · Falta stock de: ${agotados.join(", ")}`
                    : "No Disponible";
            const esRecomendado = index % 3 === 0;
            const esDescuento = index === 1;
            const imgHtml = esImagenSegura(producto.imagen)
                ? `<img class="catalogo-imagen" src="${esc(producto.imagen)}" alt="${esc(producto.nombre)}" loading="lazy">`
                : `<div class="catalogo-imagen-placeholder"><i class="fa-solid fa-utensils"></i></div>`;
            const btnPedido = puedeCrearPedido
                ? `<button class="agregar-carrito ${disponible ? "" : "sin-stock"}" type="button" data-agregar-pedido="${Number(producto.id_producto)}" ${disponible ? "" : "disabled"}>${disponible ? "+ Pedir" : "Falta de stock"}</button>`
                : "";
            return `
                <article class="plato ${disponible ? "" : "agotado"}" data-producto-card="${Number(producto.id_producto)}">
                    <div class="plato-imagen-contenedor">
                        ${imgHtml}
                        <div class="plato-overlay-badges">
                            ${esRecomendado && disponible ? '<span class="plato-badge-kiosk badge-recomendado"><i class="fa-solid fa-star"></i> Recomendado</span>' : ""}
                            ${esDescuento && disponible ? '<span class="plato-badge-kiosk badge-descuento"><i class="fa-solid fa-tag"></i> 20% OFF</span>' : ""}
                        </div>
                    </div>
                    <div class="plato-info">
                        <div class="plato-info-top">
                            <span class="plato-categoria">${esc(producto.categoria || "General")}</span>
                            <span class="estado-disponibilidad ${disponible ? "" : "agotado"}" role="status">${esc(msgDisp)}</span>
                        </div>
                        <div class="plato-titulo">
                            <h2 class="${disponible ? "" : "agotado"}">${esc(producto.nombre)}</h2>
                            <span class="plato-precio ${disponible ? "" : "agotado"}">$${formatearPrecio(producto.precio)}</span>
                        </div>
                        <p class="plato-ingredientes"><strong>Ingredientes:</strong> ${esc((producto.ingredientes || []).join(", ") || "Según preparación")}</p>
                        <div class="plato-acciones-kiosk">
                            <button class="plato-detalle" type="button" data-producto="${Number(producto.id_producto)}">
                                <i class="fa-solid fa-bars-staggered"></i> Ver detalle
                            </button>
                            ${btnPedido}
                        </div>
                    </div>
                </article>`;
        }).join("");
    }

    function actualizarTotalesDrawer() {
        if (!detalleProductoActual) return;
        const precioUnitario = Number(detalleProductoActual.precio) || 0;
        const subtotal = precioUnitario * detalleCantidadActual;
        const qtyValEl = document.getElementById("drawer-qty-val");
        const precioBaseEl = document.getElementById("ticket-precio-base");
        const unidadesEl = document.getElementById("ticket-unidades");
        const totalMontoEl = document.getElementById("ticket-total-monto");
        const btnPrecioTotalEl = document.getElementById("drawer-btn-precio-total");

        if (qtyValEl) qtyValEl.textContent = String(detalleCantidadActual);
        if (precioBaseEl) precioBaseEl.textContent = `$${formatearPrecio(precioUnitario)}`;
        if (unidadesEl) unidadesEl.textContent = `${detalleCantidadActual}x`;
        if (totalMontoEl) totalMontoEl.textContent = `$${formatearPrecio(subtotal)}`;
        if (btnPrecioTotalEl) btnPrecioTotalEl.textContent = `$${formatearPrecio(subtotal)}`;
    }

    function mostrarDetalle(id) {
        const producto = productosCache.find((p) => String(p.id_producto) === String(id));
        if (!producto || !dialogoProducto) return;
        detalleProductoActual = producto;
        detalleCantidadActual = 1;

        const disponible = Boolean(producto.disponible_stock);
        const agotados = Array.isArray(producto.ingredientes_agotados) ? producto.ingredientes_agotados : [];
        const sesion = window.RequestyAPI.leerSesion();

        const mesaBadgeEl = document.getElementById("drawer-mesa-badge");
        if (mesaBadgeEl) {
            mesaBadgeEl.textContent = mesaClienteActual?.numero_mesa ? `Mesa ${mesaClienteActual.numero_mesa}` : "Modo Sala";
        }
        const clienteNombreEl = document.getElementById("drawer-cliente-nombre");
        if (clienteNombreEl) {
            clienteNombreEl.textContent = sesion?.nombre || "Cliente en sala";
        }
        const fechaEl = document.getElementById("drawer-fecha");
        if (fechaEl) {
            fechaEl.textContent = new Date().toLocaleDateString("es-CO", {
                weekday: "short",
                month: "short",
                day: "numeric",
                hour: "2-digit",
                minute: "2-digit"
            });
        }

        const catEl = document.getElementById("categoria-detalle-producto");
        if (catEl) catEl.textContent = producto.categoria || "General";

        const estEl = document.getElementById("estado-detalle-producto");
        if (estEl) {
            estEl.textContent = disponible
                ? "Disponible"
                : agotados.length
                    ? `No Disponible · Falta stock: ${agotados.join(", ")}`
                    : "No Disponible";
            estEl.className = `estado-disponibilidad ${disponible ? "" : "agotado"}`;
        }

        const nomEl = document.getElementById("nombre-detalle-producto");
        if (nomEl) {
            nomEl.textContent = producto.nombre;
            nomEl.classList.toggle("agotado", !disponible);
        }

        const preEl = document.getElementById("precio-detalle-producto");
        if (preEl) {
            preEl.textContent = `$${formatearPrecio(producto.precio)}`;
            preEl.classList.toggle("agotado", !disponible);
        }

        const imagenWrap = document.getElementById("drawer-imagen-wrap");
        if (imagenWrap) {
            imagenWrap.innerHTML = esImagenSegura(producto.imagen)
                ? `<img class="drawer-imagen-destacada" src="${esc(producto.imagen)}" alt="${esc(producto.nombre)}">`
                : '<div class="drawer-imagen-placeholder"><i class="fa-solid fa-utensils"></i></div>';
        }

        const tagsContainer = document.getElementById("drawer-tags-ingredientes");
        if (tagsContainer) {
            const listaIng = Array.isArray(producto.ingredientes) && producto.ingredientes.length
                ? producto.ingredientes
                : ["Según preparación"];
            tagsContainer.innerHTML = listaIng.map((ing) =>
                `<span class="drawer-tag-chip"><i class="fa-solid fa-check"></i> ${esc(ing)}</span>`
            ).join("");
        }

        actualizarTotalesDrawer();

        const btnAccion = document.getElementById("drawer-btn-accion");
        if (btnAccion) {
            btnAccion.disabled = !disponible || !puedeCrearPedido;
            const btnTexto = document.getElementById("drawer-btn-texto");
            if (btnTexto) {
                btnTexto.innerHTML = disponible
                    ? '<i class="fa-solid fa-cart-plus"></i> Agregar al pedido'
                    : '<i class="fa-solid fa-ban"></i> Falta stock';
            }
        }

        if (typeof dialogoProducto.showModal === "function") {
            dialogoProducto.showModal();
        } else {
            dialogoProducto.setAttribute("open", "");
        }
    }

    async function cargarTodo() {
        const sesion = window.RequestyAPI.leerSesion() || await window.RequestyAPI.cargarSesion();
        const rol = String(sesion?.rol || "").toUpperCase();
        esCliente = rol === "CLIENTE";
        puedeCrearPedido = ["MESERO", "ADMIN", "CAJERO"].includes(rol);

        try {
            const [resProd, resMesas] = await Promise.all([
                window.RequestyAPI.get("api/productos"),
                window.RequestyAPI.get("api/mesas")
            ]);
            productosCache = Array.isArray(resProd?.productos) ? resProd.productos : [];
            const mesas = Array.isArray(resMesas?.mesas) ? resMesas.mesas : [];
            mesaClienteActual = esCliente ? (mesas[0] || null) : null;

            const asignada = !esCliente || Boolean(mesaClienteActual);
            const bloqueoEl = document.getElementById("bloqueo-cliente-mesa");
            if (bloqueoEl) {
                if (esCliente && mesaClienteActual) {
                    bloqueoEl.hidden = false;
                    bloqueoEl.textContent = `Mesa ${mesaClienteActual.numero_mesa} asignada${mesaClienteActual.cliente_nombre ? ` a ${mesaClienteActual.cliente_nombre}` : ""}. El mesero registra tu pedido desde su terminal.`;
                } else {
                    bloqueoEl.hidden = asignada;
                }
            }
            const controlesEl = document.querySelector(".catalogo-controles");
            if (controlesEl) controlesEl.hidden = !asignada;
            const conteoEl = document.getElementById("conteo-menu");
            if (conteoEl) conteoEl.hidden = !asignada;
            contenedorCatalogo.hidden = !asignada;

            renderizarCatalogo();

            const segSection = document.getElementById("seguimiento-pedidos");
            if (segSection) {
                if (esCliente) {
                    segSection.hidden = false;
                    const resPed = await window.RequestyAPI.get("api/pedidos");
                    const pedidos = Array.isArray(resPed?.pedidos) ? resPed.pedidos : [];
                    document.getElementById("lista-seguimiento-pedidos").innerHTML = pedidos.length
                        ? pedidos.map((ped) => {
                            const est = normalizarEstadoSeguimiento(ped.estado);
                            const pendientePago = ped.estado === "ENTREGADO" && Number(ped.saldo_pendiente || 0) > 0;
                            const irCaja = pendientePago
                                ? `<a class="seguimiento-ir-caja" href="/caja?pedido=${Number(ped.id_pedido)}">Ir a caja para pagar</a>`
                                : "";
                            return `<article class="seguimiento-pedido"><strong>Pedido #${Number(ped.id_pedido)}</strong><span>Mesa ${esc(ped.numero_mesa || "—")} · $${formatearPrecio(ped.total)}</span><span class="seguimiento-estado">${esc(est)}</span>${irCaja}</article>`;
                        }).join("")
                        : "<p>Aún no tienes pedidos registrados.</p>";
                } else {
                    segSection.hidden = true;
                }
            }
        } catch (err) {
            const msgEl = document.getElementById("mensaje-menu-inventario");
            if (msgEl) msgEl.textContent = err.message || "No se pudo cargar el menú desde PostgreSQL.";
        }
    }

    filtrosCatalogo.addEventListener("click", (e) => {
        const btn = e.target.closest("button[data-categoria]");
        if (!btn) return;
        filtroActual = btn.dataset.categoria;
        renderizarCatalogo();
    });

    campoBusquedaMenu?.addEventListener("input", () => {
        busquedaActual = campoBusquedaMenu.value.trim().toLocaleLowerCase("es");
        renderizarCatalogo();
    });

    contenedorCatalogo.addEventListener("click", (e) => {
        const agregar = e.target.closest("button[data-agregar-pedido]");
        if (agregar) {
            window.location.href = `/pedidos?agregar=${encodeURIComponent(agregar.dataset.agregarPedido)}`;
            return;
        }
        const btn = e.target.closest("button[data-producto]");
        if (btn) mostrarDetalle(btn.dataset.producto);
    });

    document.getElementById("cerrar-detalle-producto")?.addEventListener("click", () => dialogoProducto?.close());
    dialogoProducto?.addEventListener("click", (e) => {
        if (e.target === dialogoProducto) dialogoProducto.close();
    });

    document.getElementById("btn-qty-minus")?.addEventListener("click", () => {
        if (detalleCantidadActual > 1) {
            detalleCantidadActual -= 1;
            actualizarTotalesDrawer();
        }
    });

    document.getElementById("btn-qty-plus")?.addEventListener("click", () => {
        if (detalleCantidadActual < 99) {
            detalleCantidadActual += 1;
            actualizarTotalesDrawer();
        }
    });

    document.getElementById("drawer-btn-accion")?.addEventListener("click", () => {
        if (!puedeCrearPedido || !detalleProductoActual || !detalleProductoActual.disponible_stock) return;
        dialogoProducto?.close();
        window.location.href = `/pedidos?agregar=${encodeURIComponent(detalleProductoActual.id_producto)}&cantidad=${encodeURIComponent(detalleCantidadActual)}`;
    });

    if (window.RequestyAPI.leerSesion()) {
        cargarTodo();
    } else {
        window.addEventListener("requesty-sesion-lista", cargarTodo, { once: true });
    }
})();
