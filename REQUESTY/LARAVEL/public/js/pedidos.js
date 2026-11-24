// Gestión de Pedidos conectada 100% a PostgreSQL (api/pedidos, api/mesas, api/productos, api/notificaciones)
(function () {
    const ordersTbody = document.getElementById("ordersTbody");
    const searchInput = document.getElementById("searchInput");
    const filterBtns = document.querySelectorAll(".tab-btn");
    const btnNewOrder = document.getElementById("btnNewOrder");
    const orderModal = document.getElementById("orderModal");
    const btnCloseModal = document.getElementById("btnCloseModal");
    const btnCancelModal = document.getElementById("btnCancelModal");
    const orderForm = document.getElementById("orderForm");
    const modalTitle = document.getElementById("modalTitle");
    const editOrderId = document.getElementById("editOrderId");
    const orderIdInput = document.getElementById("orderIdInput");
    const destinationInput = document.getElementById("destinationInput");
    const tableInput = document.getElementById("tableInput");
    const tableGroup = document.getElementById("tableGroup");
    const addressGroup = document.getElementById("addressGroup");
    const channelSelect = document.getElementById("channelSelect");
    const statusSelect = document.getElementById("statusSelect");
    const productSelect = document.getElementById("productSelect");
    const orderCartItems = document.getElementById("orderCartItems");
    const customerEmailInput = document.getElementById("customerEmailInput");
    const customerNameInput = document.getElementById("customerNameInput");
    if (!ordersTbody) return;

    let ordersData = [];
    let mesasCache = [];
    let productosCache = [];
    let draftItems = [];
    let activeFilter = "all";
    let searchQuery = "";

    function esc(v) {
        return window.RequestyAPI.escaparHTML(v);
    }

    function formatCurrency(amount) {
        return "$" + Number(amount || 0).toLocaleString("es-CO");
    }

    function detectarCanal(order) {
        if (order.numero_mesa || order.id_mesa) return "Mesa";
        const obs = String(order.observaciones || "").toLowerCase();
        if (obs.includes("domicilio")) return "Domicilio";
        return "Para Llevar";
    }

    function etiquetaEstado(st) {
        const map = {
            PENDIENTE: "En cola",
            EN_PREPARACION: "En preparación",
            LISTO: "Listo para servir",
            ENTREGADO: "Entregado",
            PAGADO: "Pagado",
            CANCELADO: "Cancelado"
        };
        return map[String(st || "").toUpperCase()] || st;
    }

    function claseEstado(st) {
        const map = {
            PENDIENTE: "status-nuevo",
            EN_PREPARACION: "status-preparando",
            LISTO: "status-listo",
            ENTREGADO: "status-entregado",
            PAGADO: "status-entregado",
            CANCELADO: "status-cancelado"
        };
        return map[String(st || "").toUpperCase()] || "status-nuevo";
    }

    function iconCanal(canal) {
        if (canal === "Mesa") return "fa-utensils";
        if (canal === "Domicilio") return "fa-motorcycle";
        return "fa-bag-shopping";
    }

    function formatOrderItems(items = []) {
        return items.map((i) => `${i.producto_nombre || i.nombre} × ${i.cantidad}`).join(", ") || "—";
    }

    function renderOrders() {
        const filtered = ordersData.filter((order) => {
            const canal = detectarCanal(order);
            const destino = order.numero_mesa ? `Mesa ${order.numero_mesa}` : (order.observaciones || "Mostrador");
            const searchable = `#${order.id_pedido} ${destino} ${order.cliente_nombre || ""} ${formatOrderItems(order.items)}`.toLocaleLowerCase("es");
            return (activeFilter === "all" || canal === activeFilter) && searchable.includes(searchQuery.toLocaleLowerCase("es"));
        });

        if (!filtered.length) {
            ordersTbody.innerHTML = '<tr><td colspan="8" class="orders-empty">No se encontraron pedidos.</td></tr>';
            return;
        }

        const sesion = window.RequestyAPI.leerSesion();
        const rol = String(sesion?.rol || "").toUpperCase();
        const puedeEntregar = ["MESERO", "ADMIN"].includes(rol);

        ordersTbody.innerHTML = filtered.map((order) => {
            const canal = detectarCanal(order);
            const st = String(order.estado || "PENDIENTE").toUpperCase();
            const destino = order.numero_mesa
                ? `Mesa ${order.numero_mesa}${order.cliente_nombre ? ` · ${order.cliente_nombre}` : ""}`
                : (order.observaciones || "Para Llevar");
            const hora = new Date(order.fecha_hora).toLocaleTimeString("es-CO", { hour: "2-digit", minute: "2-digit" });
            const btnEntregar = (puedeEntregar && st === "LISTO")
                ? `<button class="action-btn deliver" type="button" data-order-deliver="${Number(order.id_pedido)}" title="Entregar pedido">Entregar</button>`
                : "";
            const editable = st === "PENDIENTE";
            const cancelable = !["PAGADO", "CANCELADO"].includes(st);
            return `<tr>
                <td class="id-col">#${Number(order.id_pedido)}</td>
                <td>${esc(destino)}</td>
                <td><span class="badge-channel"><i class="fa-solid ${iconCanal(canal)}"></i> ${esc(canal)}</span></td>
                <td>${esc(hora)}</td>
                <td class="order-items-summary">${esc(formatOrderItems(order.items))}</td>
                <td class="price-col">${formatCurrency(order.total)}</td>
                <td><span class="badge-status ${claseEstado(st)}">${esc(etiquetaEstado(st))}</span></td>
                <td class="text-right">
                    ${btnEntregar}
                    ${editable ? `<button class="action-btn edit" type="button" data-order-edit="${Number(order.id_pedido)}" title="Editar pedido"><i class="fa-regular fa-pen-to-square"></i></button>` : ""}
                    ${cancelable ? `<button class="action-btn delete" type="button" data-order-delete="${Number(order.id_pedido)}" title="Cancelar pedido"><i class="fa-regular fa-trash-can"></i></button>` : ""}
                </td>
            </tr>`;
        }).join("");
    }

    async function cargarTodo() {
        try {
            const [resPed, resMesas, resProd] = await Promise.all([
                window.RequestyAPI.get("api/pedidos"),
                window.RequestyAPI.get("api/mesas"),
                window.RequestyAPI.get("api/productos")
            ]);
            ordersData = Array.isArray(resPed?.pedidos) ? resPed.pedidos : [];
            mesasCache = Array.isArray(resMesas?.mesas) ? resMesas.mesas : [];
            productosCache = Array.isArray(resProd?.productos) ? resProd.productos : [];
            renderOrders();

            const urlParams = new URLSearchParams(window.location.search);
            const agregarId = urlParams.get("agregar");
            if (agregarId) {
                abrirNuevoPedido(Number(agregarId));
                window.history.replaceState(null, "", "/pedidos");
            }
        } catch (err) {
            document.getElementById("pedido-notificacion").textContent = err.message || "Error al consultar pedidos.";
        }
    }

    function cargarMesasSelect(idMesaPre = "") {
        const rol = String(window.RequestyAPI.leerSesion()?.rol || "").toUpperCase();
        const esMesero = rol === "MESERO";
        const lista = mesasCache.filter((m) => !esMesero || m.estado === "OCUPADA");
        tableInput.innerHTML = `<option value="">${esMesero ? "Selecciona mesa ocupada" : "Selecciona una mesa"}</option>` +
            lista.map((m) => `<option value="${Number(m.id_mesa)}" data-cliente-nombre="${esc(m.cliente_nombre || "")}" data-cliente-correo="${esc(m.cliente_correo || "")}">Mesa ${Number(m.numero_mesa)} · ${esc(m.estado)}${m.cliente_nombre ? ` · ${esc(m.cliente_nombre)}` : ""}</option>`).join("");
        if (idMesaPre) tableInput.value = String(idMesaPre);
    }

    function cargarProductosSelect() {
        productSelect.innerHTML = '<option value="">Selecciona un producto...</option>' +
            productosCache.map((p) => {
                const ok = Boolean(p.disponible_stock);
                return `<option value="${Number(p.id_producto)}" ${ok ? "" : "disabled"}>${esc(p.nombre)} · ${formatCurrency(p.precio)}${ok ? "" : " (Sin stock)"}</option>`;
            }).join("");
        document.getElementById("btnAddProduct").disabled = !productSelect.value;
    }

    function totalItem(item) {
        const extras = (item.extras || []).reduce((s, e) => s + Number(e.costo_extra || 0) * Number(e.cantidad || 1), 0);
        return (Number(item.precio || 0) + extras) * Number(item.cantidad || 1);
    }

    function renderDraftItems() {
        const emptyEl = document.getElementById("emptyOrderCart");
        emptyEl.hidden = draftItems.length > 0;
        if (!draftItems.length) {
            orderCartItems.innerHTML = "";
            document.getElementById("orderTotal").textContent = formatCurrency(0);
            return;
        }
        orderCartItems.innerHTML = draftItems.map((item, idx) => {
            const receta = Array.isArray(item.receta) ? item.receta : [];
            const checksHtml = receta.length ? `
                <div style="margin-top:6px;">
                    <small>Excluir ingredientes:</small>
                    <div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:4px;">
                        ${receta.map((r) => `<label style="font-size:12px;"><input type="checkbox" data-item-exclude="${idx}" value="${esc(r.nombre)}" ${(item.ingredientes_excluidos || []).includes(r.nombre) ? "checked" : ""}> Sin ${esc(r.nombre)}</label>`).join("")}
                    </div>
                </div>` : "";
            return `<div class="order-cart-item" style="border:1px solid #e5e7eb; padding:10px; border-radius:8px; margin-bottom:8px;">
                <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                    <strong>${esc(item.nombre)} (${formatCurrency(item.precio)})</strong>
                    <div style="display:flex; align-items:center; gap:6px;">
                        <input type="number" min="1" max="99" value="${Number(item.cantidad)}" data-item-qty="${idx}" style="width:64px;">
                        <button type="button" class="btn-secondary" data-item-opts="${idx}">Opciones</button>
                        <button type="button" class="btn-secondary" data-item-del="${idx}">Quitar</button>
                    </div>
                </div>
                ${item.optionsOpen ? `<div style="margin-top:8px; padding-top:8px; border-top:1px dashed #d1d5db;">
                    <label style="display:block; font-size:12px;">Término / Preparación
                        <input type="text" data-item-termino="${idx}" value="${esc(item.termino_coccion || "")}" placeholder="Ej. Término medio / En leche" style="width:100%; margin-top:4px;">
                    </label>
                    <label style="display:block; font-size:12px; margin-top:6px;">Notas cocina
                        <input type="text" data-item-obs="${idx}" value="${esc(item.observaciones || "")}" placeholder="Sin sal, bien cocido..." style="width:100%; margin-top:4px;">
                    </label>
                    ${checksHtml}
                </div>` : ""}
            </div>`;
        }).join("");
        const total = draftItems.reduce((sum, i) => sum + totalItem(i), 0);
        document.getElementById("orderTotal").textContent = formatCurrency(total);
    }

    function actualizarDestino() {
        const canal = channelSelect.value;
        tableGroup.hidden = canal !== "Mesa";
        addressGroup.hidden = canal !== "Domicilio";
        tableInput.required = canal === "Mesa";
    }

    function abrirNuevoPedido(preselectProdId = null) {
        orderForm.reset();
        editOrderId.value = "";
        modalTitle.textContent = "Nuevo Pedido";
        orderIdInput.value = "Automático (BD)";
        statusSelect.value = "PENDIENTE";
        statusSelect.disabled = true;
        channelSelect.value = "Mesa";
        actualizarDestino();
        cargarMesasSelect();
        cargarProductosSelect();
        draftItems = [];

        if (preselectProdId) {
            const prod = productosCache.find((p) => Number(p.id_producto) === Number(preselectProdId) && p.disponible_stock);
            if (prod) {
                draftItems.push({
                    id_producto: Number(prod.id_producto),
                    nombre: prod.nombre,
                    precio: Number(prod.precio),
                    cantidad: 1,
                    receta: prod.receta || [],
                    termino_coccion: "",
                    observaciones: "",
                    ingredientes_excluidos: [],
                    extras: [],
                    optionsOpen: false
                });
            }
        }
        renderDraftItems();
        document.getElementById("orderCartMessage").textContent = "";
        orderModal.classList.add("active");
    }

    function editarPedido(idPedido) {
        const order = ordersData.find((o) => Number(o.id_pedido) === Number(idPedido));
        if (!order) return;
        orderForm.reset();
        editOrderId.value = String(order.id_pedido);
        modalTitle.textContent = `Editar Pedido #${order.id_pedido}`;
        orderIdInput.value = `#${order.id_pedido}`;
        statusSelect.value = order.estado;
        statusSelect.disabled = false;
        const canal = detectarCanal(order);
        channelSelect.value = canal;
        actualizarDestino();
        cargarMesasSelect(order.id_mesa || "");
        cargarProductosSelect();
        customerNameInput.value = order.cliente_nombre || "";
        customerEmailInput.value = order.cliente_correo || "";
        destinationInput.value = order.observaciones || "";

        draftItems = (order.items || []).map((it) => {
            const prod = productosCache.find((p) => Number(p.id_producto) === Number(it.id_producto));
            return {
                id_producto: Number(it.id_producto),
                nombre: it.producto_nombre,
                precio: Number(it.precio_unitario),
                cantidad: Number(it.cantidad),
                receta: prod?.receta || [],
                termino_coccion: it.termino_coccion || "",
                observaciones: it.observaciones || "",
                ingredientes_excluidos: it.ingredientes_excluidos || [],
                extras: it.extras || [],
                optionsOpen: false
            };
        });
        renderDraftItems();
        orderModal.classList.add("active");
    }

    function cerrarModalPedido() {
        orderModal.classList.remove("active");
    }

    async function guardarPedido(evento) {
        evento.preventDefault();
        const msgEl = document.getElementById("orderCartMessage");
        msgEl.textContent = "";

        if (!draftItems.length) {
            msgEl.textContent = "Agrega al menos un producto al pedido.";
            return;
        }

        const canal = channelSelect.value;
        const idMesa = canal === "Mesa" && tableInput.value ? Number(tableInput.value) : null;
        if (canal === "Mesa" && !idMesa) {
            msgEl.textContent = "Selecciona una mesa para el pedido.";
            return;
        }
        const obs = canal === "Domicilio"
            ? `Domicilio: ${destinationInput.value.trim()}`
            : (canal === "Para Llevar" ? "Para Llevar" : (destinationInput.value.trim() || null));

        const itemsPayload = draftItems.map((it) => ({
            id_producto: Number(it.id_producto),
            cantidad: Number(it.cantidad),
            termino_coccion: it.termino_coccion || null,
            observaciones: it.observaciones || null,
            ingredientes_excluidos: it.ingredientes_excluidos || [],
            extras: it.extras || []
        }));

        try {
            if (editOrderId.value) {
                const idPed = Number(editOrderId.value);
                await window.RequestyAPI.put("api/pedidos", {
                    id_pedido: idPed,
                    id_mesa: idMesa,
                    observaciones: obs,
                    items: itemsPayload
                });
                const orig = ordersData.find((o) => Number(o.id_pedido) === idPed);
                if (orig && statusSelect.value !== orig.estado && statusSelect.value !== "PENDIENTE") {
                    await window.RequestyAPI.post("api/pedidos", { id_pedido: idPed, estado: statusSelect.value });
                }
            } else {
                await window.RequestyAPI.post("api/pedidos", {
                    id_mesa: idMesa,
                    cliente_nombre: customerNameInput.value.trim() || null,
                    cliente_correo: customerEmailInput.value.trim().toLowerCase() || null,
                    observaciones: obs,
                    items: itemsPayload
                });
            }
            cerrarModalPedido();
            await cargarTodo();
        } catch (err) {
            msgEl.textContent = err.message || "No se pudo guardar el pedido.";
        }
    }

    async function entregarPedido(idPedido) {
        try {
            await window.RequestyAPI.post("api/pedidos", { id_pedido: Number(idPedido), estado: "ENTREGADO" });
            document.getElementById("pedido-notificacion").textContent = `Pedido #${idPedido} entregado al cliente.`;
            await cargarTodo();
        } catch (err) {
            document.getElementById("pedido-notificacion").textContent = err.message || "No se pudo entregar el pedido.";
        }
    }

    async function cancelarPedido(idPedido) {
        if (!window.confirm(`¿Cancelar lógicamente el pedido #${idPedido}?`)) return;
        try {
            await window.RequestyAPI.del("api/pedidos", null, { id: idPedido });
            document.getElementById("pedido-notificacion").textContent = `Pedido #${idPedido} cancelado.`;
            await cargarTodo();
        } catch (err) {
            document.getElementById("pedido-notificacion").textContent = err.message || "No se pudo cancelar el pedido.";
        }
    }

    async function verNotificaciones() {
        const notifEl = document.getElementById("pedido-notificacion");
        try {
            const res = await window.RequestyAPI.get("api/notificaciones", { no_leidas: 1 });
            const lista = res?.notificaciones || [];
            if (!lista.length) {
                notifEl.textContent = "Sin notificaciones nuevas pendientes.";
                return;
            }
            notifEl.textContent = lista.map((n) => `• ${n.mensaje}`).slice(0, 3).join(" | ");
            await window.RequestyAPI.put("api/notificaciones", { todas: true });
        } catch {
            notifEl.textContent = "Los cambios de estado de tus pedidos aparecerán aquí.";
        }
    }

    btnNewOrder?.addEventListener("click", () => abrirNuevoPedido());
    btnCloseModal?.addEventListener("click", cerrarModalPedido);
    btnCancelModal?.addEventListener("click", cerrarModalPedido);
    orderForm?.addEventListener("submit", guardarPedido);
    channelSelect?.addEventListener("change", actualizarDestino);

    tableInput?.addEventListener("change", () => {
        const opt = tableInput.selectedOptions[0];
        if (!opt?.value) return;
        customerNameInput.value = opt.dataset.clienteNombre || "";
        customerEmailInput.value = opt.dataset.clienteCorreo || "";
    });

    productSelect?.addEventListener("change", () => {
        document.getElementById("btnAddProduct").disabled = !productSelect.value;
    });

    document.getElementById("btnAddProduct")?.addEventListener("click", () => {
        const idProd = Number(productSelect.value);
        const prod = productosCache.find((p) => Number(p.id_producto) === idProd);
        if (!prod) return;
        const existente = draftItems.find((i) => Number(i.id_producto) === idProd);
        if (existente) {
            existente.cantidad = Math.min(99, existente.cantidad + 1);
        } else {
            draftItems.push({
                id_producto: idProd,
                nombre: prod.nombre,
                precio: Number(prod.precio),
                cantidad: 1,
                receta: prod.receta || [],
                termino_coccion: "",
                observaciones: "",
                ingredientes_excluidos: [],
                extras: [],
                optionsOpen: false
            });
        }
        productSelect.value = "";
        document.getElementById("btnAddProduct").disabled = true;
        renderDraftItems();
    });

    orderCartItems.addEventListener("click", (e) => {
        const del = e.target.closest("[data-item-del]");
        if (del) {
            draftItems.splice(Number(del.dataset.itemDel), 1);
            renderDraftItems();
            return;
        }
        const opts = e.target.closest("[data-item-opts]");
        if (opts) {
            const idx = Number(opts.dataset.itemOpts);
            draftItems[idx].optionsOpen = !draftItems[idx].optionsOpen;
            renderDraftItems();
        }
    });

    orderCartItems.addEventListener("input", (e) => {
        const t = e.target;
        if (t.matches("[data-item-qty]")) {
            const idx = Number(t.dataset.itemQty);
            draftItems[idx].cantidad = Math.max(1, Math.min(99, Number(t.value) || 1));
            document.getElementById("orderTotal").textContent = formatCurrency(draftItems.reduce((s, i) => s + totalItem(i), 0));
        }
        if (t.matches("[data-item-termino]")) draftItems[Number(t.dataset.itemTermino)].termino_coccion = t.value;
        if (t.matches("[data-item-obs]")) draftItems[Number(t.dataset.itemObs)].observaciones = t.value;
    });

    orderCartItems.addEventListener("change", (e) => {
        const t = e.target;
        if (t.matches("[data-item-exclude]")) {
            const item = draftItems[Number(t.dataset.itemExclude)];
            const set = new Set(item.ingredientes_excluidos || []);
            if (t.checked) set.add(t.value);
            else set.delete(t.value);
            item.ingredientes_excluidos = [...set];
        }
    });

    filterBtns.forEach((btn) => btn.addEventListener("click", () => {
        filterBtns.forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        activeFilter = btn.dataset.filter || "all";
        renderOrders();
    }));

    searchInput?.addEventListener("input", () => {
        searchQuery = searchInput.value;
        renderOrders();
    });

    ordersTbody.addEventListener("click", (e) => {
        const deliverBtn = e.target.closest("[data-order-deliver]");
        if (deliverBtn) return entregarPedido(deliverBtn.dataset.orderDeliver);
        const editBtn = e.target.closest("[data-order-edit]");
        if (editBtn) return editarPedido(editBtn.dataset.orderEdit);
        const delBtn = e.target.closest("[data-order-delete]");
        if (delBtn) return cancelarPedido(delBtn.dataset.orderDelete);
    });

    document.getElementById("btnNotifications")?.addEventListener("click", verNotificaciones);

    if (window.RequestyAPI.leerSesion()) {
        cargarTodo();
    } else {
        window.addEventListener("requesty-sesion-lista", cargarTodo, { once: true });
    }
    window.setInterval(cargarTodo, 10000);
})();
