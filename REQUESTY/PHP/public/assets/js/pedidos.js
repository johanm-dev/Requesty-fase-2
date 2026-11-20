const pedidosIniciales = [];

let ordersData = cargarPedidos();
let activeFilter = "all";
let searchQuery = "";
let draftItems = [];
let carritoOrigenKey = "";
let mensajeInventarioPedido = "";
const idsPedidoReservados = new Set();

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
const eventoPedidos = "requesty-pedidos-actualizados";

function normalizarEstadoPedido(estado) {
  const normalizado = String(estado || "").trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLocaleLowerCase("es");
  if (["nuevo", "pendiente", "en cola"].includes(normalizado)) return "En cola";
  if (["preparando", "en preparacion"].includes(normalizado)) return "En preparación";
  if (["listo", "listo para servir"].includes(normalizado)) return "Listo para servir";
  if (normalizado === "entregado") return "Entregado";
  if (normalizado === "cancelado") return "Cancelado";
  return "En cola";
}

function obtenerAlternativaDisponible(producto, inventario) {
  const grupo = producto?.recetaAlternativas?.[0];
  if (!grupo) return "";
  return grupo.opciones.find(opcion => window.RequestyInventario.productoDisponible(producto, inventario, opcion.ingrediente))?.ingrediente
    || grupo.opciones[0]?.ingrediente
    || "";
}

function normalizarRolPedido(rol) {
  return String(rol || "").trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLocaleLowerCase("es");
}

function cargarMesasPedido(mesaPreseleccionada = "") {
  const esMesero = normalizarRolPedido(leerSesion()?.rol) === "mesero";
  let mesas;
  try {
    mesas = JSON.parse(localStorage.getItem("requestyMesas") || "null");
    if (mesas === null && !esMesero) {
      mesas = Array.from({ length: 15 }, (_, indice) => ({
        id: indice + 1,
        numero: indice + 1,
        estado: "libre"
      }));
    }
    if (mesas === null) mesas = [];
    if (!Array.isArray(mesas)) throw new Error("La lista de mesas no tiene un formato válido.");
  } catch (error) {
    document.getElementById("orderCartMessage").textContent = error.message || "No se pudieron consultar las mesas.";
    mesas = [];
  }
  const disponibles = mesas.filter(mesa => !esMesero || mesa.estado === "ocupada");
  tableInput.innerHTML = `<option value="">${esMesero ? "Selecciona una mesa ocupada" : "Selecciona una mesa"}</option>`
    + disponibles.map(mesa => {
      const cliente = mesa.clienteNombre ? ` · ${mesa.clienteNombre}` : "";
      const estado = mesa.estado === "ocupada" ? "Ocupada" : "Libre";
      return `<option value="${Number(mesa.numero)}" data-cliente-nombre="${escaparHTML(mesa.clienteNombre || "")}" data-cliente-correo="${escaparHTML(mesa.clienteCorreo || "")}">Mesa ${Number(mesa.numero)} · ${estado}${escaparHTML(cliente)}</option>`;
    }).join("");
  if (mesaPreseleccionada && disponibles.some(mesa => String(mesa.numero) === String(mesaPreseleccionada))) {
    tableInput.value = String(mesaPreseleccionada);
  } else {
    tableInput.value = "";
  }
  tableInput.disabled = esMesero && disponibles.length === 0;
  if (esMesero && !disponibles.length) {
    document.getElementById("orderCartMessage").textContent = "No hay mesas ocupadas disponibles. Asigna una mesa antes de crear un pedido como mesero.";
  }
}

function relacionarPedidosConMesas() {
  let mesas;
  try {
    mesas = JSON.parse(localStorage.getItem("requestyMesas") || "null");
    if (!Array.isArray(mesas)) return;
    const pedidosActivos = ordersData.filter(pedido => pedido.tableNumber
      && !["Entregado", "Cancelado"].includes(normalizarEstadoPedido(pedido.status)));
    mesas = mesas.map(mesa => ({
      ...mesa,
      pedidos: pedidosActivos.filter(pedido => Number(pedido.tableNumber) === Number(mesa.numero)).length
    }));
    localStorage.setItem("requestyMesas", JSON.stringify(mesas));
    window.dispatchEvent(new CustomEvent("requesty-mesas-actualizadas", { detail: mesas }));
  } catch {
    document.getElementById("pedido-notificacion").textContent = "El pedido se guardó, pero no se pudo actualizar el contador de pedidos de la mesa.";
  }
}

function cargarPedidos() {
  try {
    const guardados = JSON.parse(localStorage.getItem("requestyPedidos") || "null");
    return Array.isArray(guardados) ? guardados.map(pedido => ({
      ...pedido,
      status: normalizarEstadoPedido(pedido.status || pedido.estado)
    })) : pedidosIniciales.map(pedido => ({
      ...pedido,
      status: normalizarEstadoPedido(pedido.status)
    }));
  } catch {
    return pedidosIniciales;
  }
}

function guardarPedidos() {
  try {
    localStorage.setItem("requestyPedidos", JSON.stringify(ordersData));
    return true;
  } catch {
    document.getElementById("orderCartMessage").textContent = "No se pudo guardar el pedido en este navegador.";
    return false;
  }
}

function leerProductosPedido(incluirNoDisponibles = false) {
  const catalogoGuardado = localStorage.getItem("productos-restaurante");
  if (catalogoGuardado !== null) {
    let productos;
    try {
      productos = JSON.parse(catalogoGuardado);
    } catch {
      document.getElementById("orderCartMessage").textContent = "No se pudo leer el catálogo de productos.";
      return [];
    }
    if (!Array.isArray(productos)) {
      document.getElementById("orderCartMessage").textContent = "El catálogo de productos tiene un formato inválido.";
      return [];
    }
    try {
      return productos.filter(producto => producto.activo !== false && producto.precio !== null
        && producto.precio !== "" && Number.isFinite(Number(producto.precio))
        && (incluirNoDisponibles || window.RequestyInventario.productoDisponible(producto)));
    } catch (error) {
      document.getElementById("orderCartMessage").textContent = error.message || "No se pudo comprobar el inventario.";
      return [];
    }
  }

  try {
    const productosIniciales = [
    { id: 1, nombre: "Caldo de costilla", precio: 8000, ingredientes: ["Costilla", "Papa", "Cilantro", "Agua Filtrada", "Sal"], receta: { costilla: 200, papa: 250, cilantro: 5, "agua filtrada": 0.5, sal: 3 } },
    { id: 2, nombre: "Jugo de mora", precio: 6000, ingredientes: ["Moras", "Azúcar"], receta: { moras: 80, azucar: 20 }, recetaAlternativas: [{ nombre: "Base líquida", opciones: [{ ingrediente: "Agua Filtrada", cantidad: 0.25 }, { ingrediente: "Leche", cantidad: 0.25 }] }] },
    { id: 3, nombre: "Huevos pericos", precio: 7000, ingredientes: ["Huevos", "Tomate", "Cebolla", "Sal"] },
    { id: 4, nombre: "Bandeja paisa", precio: 30000, ingredientes: ["Arroz", "Plátano", "Tocineta", "Carne molida", "Arepa", "Aguacate", "Chorizo", "Huevos", "Fríjoles"] }
    ];
    return productosIniciales.filter(producto => incluirNoDisponibles || window.RequestyInventario.productoDisponible(producto));
  } catch (error) {
    document.getElementById("orderCartMessage").textContent = error.message || "No se pudo comprobar el inventario.";
    return [];
  }
}

function leerCarritoMesero() {
  const correo = leerSesion()?.correo?.toLowerCase();
  if (!correo) return { key: "", items: [] };
  const key = `requestyCarrito:${encodeURIComponent(correo)}`;
  try {
    const guardados = JSON.parse(localStorage.getItem(key) || "[]");
    if (!Array.isArray(guardados)) return { key, items: [] };
    const productos = leerProductosPedido(true);
    const items = guardados.flatMap(carritoItem => {
      const producto = productos.find(actual => String(actual.id) === String(carritoItem.id));
      if (!producto) return [];
      return [{
        id: producto.id,
        nombre: producto.nombre,
        precio: Number(producto.precio),
        cantidad: Math.max(1, Math.min(99, Math.floor(Number(carritoItem.cantidad) || 1))),
        ingredientes: Array.isArray(producto.ingredientes) ? producto.ingredientes : String(producto.ingredientes || "").split(",").map(item => item.trim()).filter(Boolean),
        alternativaReceta: obtenerAlternativaDisponible(producto, window.RequestyInventario.leerInventario()),
        terminoCoccion: String(carritoItem.terminoCoccion || ""),
        ingredientesExcluidos: Array.isArray(carritoItem.ingredientesExcluidos) ? carritoItem.ingredientesExcluidos : [],
        extras: Array.isArray(carritoItem.extras) ? carritoItem.extras : [],
        optionsOpen: false
      }];
    });
    return { key, items };
  } catch {
    return { key, items: [] };
  }
}

function escaparHTML(valor) {
  return String(valor ?? "").replace(/[&<>"']/g, caracter => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
  })[caracter]);
}

function formatCurrency(amount) {
  return "$" + Number(amount).toLocaleString("es-CO");
}

function getChannelIcon(channel) {
  if (channel === "Mesa") return "fa-utensils";
  if (channel === "Domicilio") return "fa-motorcycle";
  return "fa-bag-shopping";
}

function getStatusClass(status) {
  return ({
    "En cola": "status-nuevo",
    "En preparación": "status-preparando",
    "Listo para servir": "status-listo",
    Entregado: "status-entregado",
    Cancelado: "status-cancelado"
  })[normalizarEstadoPedido(status)] || "status-nuevo";
}

function esMeseroSesion() {
  return normalizarRolPedido(leerSesion()?.rol) === "mesero";
}

function formatOrderItems(items = []) {
  return items.map(item => `${item.nombre}${item.alternativaReceta ? ` (${item.alternativaReceta})` : ""} × ${item.cantidad}`).join(", ") || "—";
}

function renderOrders() {
  const filteredOrders = ordersData.filter(order => {
    const searchable = `${order.id} ${order.destination} ${formatOrderItems(order.items)}`.toLocaleLowerCase("es");
    return (activeFilter === "all" || order.channel === activeFilter) && searchable.includes(searchQuery.toLocaleLowerCase("es"));
  });
  if (!filteredOrders.length) {
    ordersTbody.innerHTML = '<tr><td colspan="8" class="orders-empty">No se encontraron pedidos.</td></tr>';
    return;
  }

  ordersTbody.innerHTML = filteredOrders.map(order => `
    <tr>
      <td class="id-col">${escaparHTML(order.id)}</td>
      <td>${escaparHTML(order.destination)}</td>
      <td><span class="badge-channel"><i class="fa-solid ${escaparHTML(order.channelIcon || getChannelIcon(order.channel))}"></i> ${escaparHTML(order.channel)}</span></td>
      <td>${escaparHTML(order.time)}</td>
      <td class="order-items-summary">${escaparHTML(formatOrderItems(order.items))}</td>
      <td class="price-col">${formatCurrency(order.rawTotal)}</td>
      <td><span class="badge-status ${escaparHTML(order.statusClass || getStatusClass(order.status))}">${escaparHTML(order.status)}</span></td>
      <td class="text-right">${esMeseroSesion() && normalizarEstadoPedido(order.status) === "Listo para servir" ? `<button class="action-btn deliver" type="button" data-order-deliver="${escaparHTML(order.id)}" title="Entregar pedido al cliente" aria-label="Entregar pedido ${escaparHTML(order.id)}">Entregar</button>` : ""}<button class="action-btn edit" type="button" data-order-edit="${escaparHTML(order.id)}" title="Editar pedido" aria-label="Editar pedido ${escaparHTML(order.id)}"><i class="fa-regular fa-pen-to-square"></i></button><button class="action-btn delete" type="button" data-order-delete="${escaparHTML(order.id)}" title="Eliminar pedido" aria-label="Eliminar pedido ${escaparHTML(order.id)}"><i class="fa-regular fa-trash-can"></i></button></td>
    </tr>`).join("");
}

function generarIdPedido() {
  const ahora = new Date();
  const fechaHora = [
    ahora.getFullYear(),
    String(ahora.getMonth() + 1).padStart(2, "0"),
    String(ahora.getDate()).padStart(2, "0")
  ].join("") + `-${String(ahora.getHours()).padStart(2, "0")}${String(ahora.getMinutes()).padStart(2, "0")}${String(ahora.getSeconds()).padStart(2, "0")}`;
  const facturasGuardadas = localStorage.getItem("requestyFacturas");
  let facturas = [];
  if (facturasGuardadas !== null) {
    facturas = JSON.parse(facturasGuardadas);
    if (!Array.isArray(facturas)) throw new Error("El historial de facturas no tiene un formato válido.");
  }
  const idsUsados = new Set([
    ...ordersData.map(pedido => String(pedido.id)),
    ...facturas.map(factura => String(factura.pedidoId)),
    ...idsPedidoReservados
  ]);
  let consecutivo = 1;
  let id = `#${fechaHora}-${String(consecutivo).padStart(2, "0")}`;
  while (idsUsados.has(id)) {
    consecutivo += 1;
    id = `#${fechaHora}-${String(consecutivo).padStart(2, "0")}`;
  }
  idsPedidoReservados.add(id);
  return id;
}

function cargarProductosSelect() {
  const productos = leerProductosPedido(true);
  const mensajePedido = document.getElementById("orderCartMessage");
  if (mensajePedido.textContent === mensajeInventarioPedido) mensajePedido.textContent = "";
  mensajeInventarioPedido = "";
  let inventario = [];
  let errorInventario = "";
  try {
    inventario = window.RequestyInventario.leerInventario();
  } catch (error) {
    errorInventario = error.message || "No se pudo comprobar el inventario.";
  }
  const productosDisponibles = productos.filter(producto => !errorInventario
    && window.RequestyInventario.productoDisponible(producto, inventario)).length;
  productSelect.innerHTML = `<option value="" selected disabled>Selecciona un producto</option>`
    + productos.map(producto => {
      const disponible = !errorInventario && window.RequestyInventario.productoDisponible(producto, inventario);
      const ingredientesAgotados = disponible ? [] : window.RequestyInventario.obtenerIngredientesAgotados(producto, inventario);
      const estado = disponible
        ? ""
        : errorInventario
          ? ` · ${errorInventario}`
          : ingredientesAgotados.length
            ? ` · SIN STOCK: ${ingredientesAgotados.join(", ")}`
            : " · NO DISPONIBLE";
      return `<option value="${escaparHTML(producto.id)}" class="${disponible ? "" : "producto-agotado"}" ${disponible ? "" : "disabled"}>${escaparHTML(producto.nombre)} · ${formatCurrency(producto.precio)}${escaparHTML(estado)}</option>`;
    }).join("");
  productSelect.value = "";
  document.getElementById("btnAddProduct").disabled = true;
  if (errorInventario) {
    mensajePedido.textContent = errorInventario;
    mensajeInventarioPedido = errorInventario;
  } else if (productos.length && !productosDisponibles) {
    mensajeInventarioPedido = "No hay productos disponibles para agregar. Los platos sin stock se muestran en rojo y tachados.";
    mensajePedido.textContent = mensajeInventarioPedido;
  } else if (productos.length && productosDisponibles < productos.length) {
    mensajeInventarioPedido = "Los productos sin stock se muestran en rojo y tachados y no se pueden agregar.";
    mensajePedido.textContent = mensajeInventarioPedido;
  } else if (!productos.length && !document.getElementById("orderCartMessage").textContent) {
    mensajeInventarioPedido = "No hay productos activos con precio e ingredientes en el menú.";
    mensajePedido.textContent = mensajeInventarioPedido;
  }
}

function totalItem(item) {
  const extras = (item.extras || []).reduce((total, extra) => total + Number(extra.cantidad || 0) * Number(extra.precio || 0), 0);
  return (Number(item.precio) + extras) * Number(item.cantidad);
}

function renderDraftItems() {
  let inventario = [];
  let productosCatalogo = [];
  let errorInventario = "";
  try {
    inventario = window.RequestyInventario.leerInventario();
    productosCatalogo = leerProductosPedido(true);
  } catch (error) {
    errorInventario = error.message || "No se pudo comprobar el inventario.";
    document.getElementById("orderCartMessage").textContent = errorInventario;
  }
  document.getElementById("emptyOrderCart").hidden = draftItems.length > 0;
  orderCartItems.innerHTML = draftItems.map(item => {
    const producto = productosCatalogo.find(actual => String(actual.id) === String(item.id));
    const disponible = !errorInventario && producto && window.RequestyInventario.productoDisponible(producto, inventario, item.alternativaReceta);
    const ingredientesAgotados = producto
    ? window.RequestyInventario.obtenerIngredientesAgotados(producto, inventario, item.alternativaReceta)
      : [];
    const mensajeNoDisponible = errorInventario || !producto
      ? errorInventario || "Este producto ya no está disponible en el menú."
      : ingredientesAgotados.length
        ? `No disponible · Falta stock de: ${ingredientesAgotados.join(", ")}`
        : "No disponible";
    return `
    <article class="order-item ${disponible ? "" : "no-stock"}" data-order-item="${escaparHTML(item.id)}">
      <header class="order-item-header"><div><h4 class="${disponible ? "" : "no-stock-text"}">${escaparHTML(item.nombre)}</h4><p>${formatCurrency(item.precio)} c/u</p>${disponible ? "" : `<p class="order-item-stock-message">${escaparHTML(mensajeNoDisponible)}</p>`}</div><button type="button" class="action-btn delete" data-item-remove="${escaparHTML(item.id)}" aria-label="Quitar ${escaparHTML(item.nombre)} del pedido"><i class="fa-regular fa-trash-can"></i></button></header>
      <div class="order-item-controls"><label>Cantidad<input type="number" min="1" max="99" value="${item.cantidad}" data-item-quantity="${escaparHTML(item.id)}" ${disponible ? "" : "disabled"}></label><strong>${formatCurrency(totalItem(item))}</strong></div>
      ${(producto?.recetaAlternativas || []).map(grupo => `<label class="recipe-option-label">${escaparHTML(grupo.nombre)}<select data-item-recipe-option="${escaparHTML(item.id)}" ${disponible ? "" : "disabled"}>${(grupo.opciones || []).map(opcion => {
        const disponibleOpcion = window.RequestyInventario.productoDisponible(producto, inventario, opcion.ingrediente);
        return `<option value="${escaparHTML(opcion.ingrediente)}" ${String(item.alternativaReceta || "") === String(opcion.ingrediente) ? "selected" : ""} ${disponibleOpcion ? "" : "disabled"}>${escaparHTML(opcion.ingrediente)}${disponibleOpcion ? "" : " · sin stock suficiente"}</option>`;
      }).join("")}</select></label>`).join("")}
      <button type="button" class="customize-order-item" data-item-options="${escaparHTML(item.id)}" ${disponible ? "" : "disabled"}>${item.optionsOpen ? "Cerrar personalización" : "Personalizar"}</button>
      ${item.optionsOpen && disponible ? `<div class="order-item-options">
        <label>Término de cocción<select data-item-cooking="${escaparHTML(item.id)}"><option value="">No aplica</option>${["Poco cocido", "Término medio", "Tres cuartos", "Bien cocido"].map(term => `<option value="${term}" ${item.terminoCoccion === term ? "selected" : ""}>${term}</option>`).join("")}</select></label>
        <fieldset><legend>Excluir ingredientes</legend>${(item.ingredientes || []).length ? item.ingredientes.map(ingredient => `<label><input type="checkbox" value="${escaparHTML(ingredient)}" data-item-exclude="${escaparHTML(item.id)}" ${item.ingredientesExcluidos.includes(ingredient) ? "checked" : ""}>${escaparHTML(ingredient)}</label>`).join("") : "<p>Sin ingredientes configurados.</p>"}</fieldset>
        <div class="order-extras"><h5>Porciones extra</h5><div class="order-extra-form"><label>Extra<input type="text" maxlength="60" data-extra-name="${escaparHTML(item.id)}" placeholder="Ej. Huevo extra"></label><label>Cantidad<input type="number" min="1" max="20" value="1" data-extra-quantity="${escaparHTML(item.id)}"></label><label>Precio unitario<input type="number" min="0" step="100" value="0" data-extra-price="${escaparHTML(item.id)}"></label><button type="button" data-extra-add="${escaparHTML(item.id)}">Agregar extra</button></div>
          ${(item.extras || []).length ? `<ul>${item.extras.map((extra, index) => `<li><span>${escaparHTML(extra.nombre)} × ${extra.cantidad} · ${formatCurrency(extra.precio)} c/u</span><button type="button" data-extra-remove="${escaparHTML(item.id)}" data-extra-index="${index}">Quitar</button></li>`).join("")}</ul>` : ""}
        </div>
      </div>` : ""}
    </article>`;
  }).join("");
  document.getElementById("orderTotal").textContent = formatCurrency(draftItems.reduce((total, item) => total + totalItem(item), 0));
}

function actualizarDestino() {
  const esMesa = channelSelect.value === "Mesa";
  const esDomicilio = channelSelect.value === "Domicilio";
  tableGroup.hidden = !esMesa;
  tableInput.required = esMesa;
  addressGroup.hidden = !esDomicilio;
  destinationInput.required = esDomicilio;
  tableInput.required = esMesa;
  if (esMesa) cargarMesasPedido(tableInput.value);
}

function iniciarPedido() {
  let nuevoId;
  try {
    nuevoId = generarIdPedido();
  } catch (error) {
    document.getElementById("pedido-notificacion").textContent = error.message || "No se pudo generar un ID único para el pedido.";
    return;
  }
  orderForm.reset();
  editOrderId.value = "";
  orderIdInput.value = nuevoId;
  modalTitle.textContent = "Nuevo pedido";
  const carritoMesero = leerCarritoMesero();
  carritoOrigenKey = carritoMesero.key;
  draftItems = carritoMesero.items;
  document.getElementById("orderCartMessage").textContent = draftItems.length
    ? `Se importaron ${draftItems.length} producto(s) del carrito.`
    : "";
  statusSelect.value = "En cola";
  customerEmailInput.value = "";
  customerNameInput.value = "";
  channelSelect.value = "Mesa";
  cargarMesasPedido();
  actualizarDestino();
  cargarProductosSelect();
  renderDraftItems();
  orderModal.classList.add("active");
}

function cerrarModalPedido() {
  orderModal.classList.remove("active");
  orderForm.reset();
  editOrderId.value = "";
  draftItems = [];
  carritoOrigenKey = "";
}

function editarPedido(id) {
  const pedido = ordersData.find(order => order.id === id);
  if (!pedido) return;
  orderForm.reset();
  editOrderId.value = pedido.id;
  carritoOrigenKey = "";
  orderIdInput.value = pedido.id;
  statusSelect.value = normalizarEstadoPedido(pedido.status);
  customerEmailInput.value = pedido.customerEmail || "";
  customerNameInput.value = pedido.customerName || "";
  channelSelect.value = pedido.channel;
  const numeroMesa = pedido.tableNumber || Number(String(pedido.destination).match(/\d+/)?.[0] || "");
  cargarMesasPedido(numeroMesa);
  destinationInput.value = pedido.channel === "Domicilio" ? pedido.destination : "";
  draftItems = Array.isArray(pedido.items) ? pedido.items.map(item => ({
    ...item,
    ingredientes: Array.isArray(item.ingredientes) ? item.ingredientes : [],
    ingredientesExcluidos: Array.isArray(item.ingredientesExcluidos) ? item.ingredientesExcluidos : [],
    extras: Array.isArray(item.extras) ? item.extras : [],
    alternativaReceta: item.alternativaReceta || obtenerAlternativaDisponible(leerProductosPedido(true).find(producto => String(producto.id) === String(item.id)), window.RequestyInventario.leerInventario()),
    terminoCoccion: item.terminoCoccion || "",
    optionsOpen: false
  })) : [];
  modalTitle.textContent = `Editar pedido ${pedido.id}`;
  document.getElementById("orderCartMessage").textContent = "";
  actualizarDestino();
  cargarProductosSelect();
  renderDraftItems();
  orderModal.classList.add("active");
}

function guardarExtraDeLinea(id) {
  const article = orderCartItems.querySelector(`[data-order-item="${CSS.escape(String(id))}"]`);
  const nombre = article.querySelector(`[data-extra-name="${CSS.escape(String(id))}"]`).value.trim();
  const cantidad = Math.floor(Number(article.querySelector(`[data-extra-quantity="${CSS.escape(String(id))}"]`).value));
  const precio = Number(article.querySelector(`[data-extra-price="${CSS.escape(String(id))}"]`).value);
  if (!nombre || !Number.isInteger(cantidad) || cantidad < 1 || cantidad > 20 || !Number.isFinite(precio) || precio < 0) {
    document.getElementById("orderCartMessage").textContent = "Completa el extra, la cantidad y un precio unitario válido.";
    return;
  }
  const item = draftItems.find(line => String(line.id) === String(id));
  item.extras.push({ nombre, cantidad, precio });
  document.getElementById("orderCartMessage").textContent = "";
  renderDraftItems();
}

function renderOrders() {
  const filteredOrders = ordersData.filter(order => {
    const searchable = `${order.id} ${order.destination} ${formatOrderItems(order.items)}`.toLocaleLowerCase("es");
    return (activeFilter === "all" || order.channel === activeFilter) && searchable.includes(searchQuery.toLocaleLowerCase("es"));
  });
  if (!filteredOrders.length) {
    ordersTbody.innerHTML = '<tr><td colspan="8" class="orders-empty">No se encontraron pedidos.</td></tr>';
    return;
  }

  ordersTbody.innerHTML = filteredOrders.map(order => `
    <tr>
      <td class="id-col">${escaparHTML(order.id)}</td>
      <td>${escaparHTML(order.destination)}</td>
      <td><span class="badge-channel"><i class="fa-solid ${escaparHTML(order.channelIcon || getChannelIcon(order.channel))}"></i> ${escaparHTML(order.channel)}</span></td>
      <td>${escaparHTML(order.time)}</td>
      <td class="order-items-summary">${escaparHTML(formatOrderItems(order.items))}</td>
      <td class="price-col">${formatCurrency(order.rawTotal)}</td>
      <td><span class="badge-status ${escaparHTML(order.statusClass || getStatusClass(order.status))}">${escaparHTML(order.status)}</span></td>
      <td class="text-right">${esMeseroSesion() && normalizarEstadoPedido(order.status) === "Listo para servir" ? `<button class="action-btn deliver" type="button" data-order-deliver="${escaparHTML(order.id)}" title="Entregar pedido al cliente" aria-label="Entregar pedido ${escaparHTML(order.id)}">Entregar</button>` : ""}<button class="action-btn edit" type="button" data-order-edit="${escaparHTML(order.id)}" aria-label="Editar pedido ${escaparHTML(order.id)}"><i class="fa-regular fa-pen-to-square"></i></button><button class="action-btn delete" type="button" data-order-delete="${escaparHTML(order.id)}" aria-label="Eliminar pedido ${escaparHTML(order.id)}"><i class="fa-regular fa-trash-can"></i></button></td>
    </tr>`).join("");
}

function formatOrderItems(items = []) {
  return items.map(item => `${item.nombre}${item.alternativaReceta ? ` (${item.alternativaReceta})` : ""} × ${item.cantidad}`).join(", ") || "—";
}

function guardarPedido(evento) {
  evento.preventDefault();
  const original = editOrderId.value ? ordersData.find(order => order.id === editOrderId.value) : null;
  if (!draftItems.length) {
    document.getElementById("orderCartMessage").textContent = "Agrega al menos un producto antes de guardar.";
    return;
  }
  let inventario;
  try {
    inventario = window.RequestyInventario.leerInventario();
  } catch (error) {
    document.getElementById("orderCartMessage").textContent = error.message || "No se pudo comprobar el inventario.";
    return;
  }
  const productosActuales = leerProductosPedido(true);
  const productoAgotado = draftItems.find(item => {
    const producto = productosActuales.find(actual => String(actual.id) === String(item.id));
    return !producto || !window.RequestyInventario.productoDisponible(producto, inventario, item.alternativaReceta);
  });
  if (productoAgotado) {
    const producto = productosActuales.find(actual => String(actual.id) === String(productoAgotado.id));
    if (!producto) {
      document.getElementById("orderCartMessage").textContent = `${productoAgotado.nombre} ya no está en el catálogo y no se puede pedir.`;
      return;
    }
    const faltantesDelPedido = window.RequestyInventario.validarDisponibilidadPedido(draftItems, productosActuales, inventario);
    if (faltantesDelPedido.length) {
      document.getElementById("orderCartMessage").textContent = `No se puede guardar el pedido: stock insuficiente de ${faltantesDelPedido.map(faltante =>
        `${faltante.ingrediente} (disponible ${faltante.disponible}, requerido ${faltante.requerido})`
      ).join(", ")}.`;
      return;
    }
    const ingredientesAgotados = window.RequestyInventario.obtenerIngredientesAgotados(producto, inventario, productoAgotado.alternativaReceta);
    document.getElementById("orderCartMessage").textContent = `${productoAgotado.nombre} no se puede pedir: ${ingredientesAgotados.length ? `sin stock de ${ingredientesAgotados.join(", ")}.` : "no está disponible."}`;
    return;
  }
  let mesaPedido = null;
  if (channelSelect.value === "Mesa") {
    let mesas;
    try {
      mesas = JSON.parse(localStorage.getItem("requestyMesas") || "[]");
    } catch {
      document.getElementById("orderCartMessage").textContent = "No se pudo leer la lista de mesas. Revisa la gestión de mesas antes de guardar.";
      return;
    }
    mesaPedido = Array.isArray(mesas) ? mesas.find(mesa => Number(mesa.numero) === Number(tableInput.value)) : null;
    if (!mesaPedido) {
      document.getElementById("orderCartMessage").textContent = "Selecciona una mesa existente.";
      return;
    }
    if (normalizarRolPedido(leerSesion()?.rol) === "mesero" && mesaPedido.estado !== "ocupada") {
      cargarMesasPedido();
      document.getElementById("orderCartMessage").textContent = "Como mesero, solo puedes crear pedidos para mesas ocupadas.";
      return;
    }
  }
  if (channelSelect.value === "Domicilio" && !destinationInput.value.trim()) {
    document.getElementById("orderCartMessage").textContent = "Escribe la dirección de entrega.";
    return;
  }

  const rawTotal = draftItems.reduce((total, item) => total + totalItem(item), 0);
  const ahora = new Date();
  const numeroMesa = channelSelect.value === "Mesa" ? Number(tableInput.value) : null;
  const destinoMesa = mesaPedido && (customerNameInput.value.trim() || customerEmailInput.value.trim())
    ? `Mesa ${numeroMesa} · ${customerNameInput.value.trim() || mesaPedido.clienteNombre || "Cliente"}`
    : `Mesa ${numeroMesa}`;
  const destination = channelSelect.value === "Mesa" ? destinoMesa
    : channelSelect.value === "Domicilio" ? destinationInput.value.trim() : "Para Llevar";
  const pedido = {
    id: orderIdInput.value,
    destination,
    channel: channelSelect.value,
    channelIcon: getChannelIcon(channelSelect.value),
    time: original?.time || ahora.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
    receivedAt: original?.receivedAt || original?.createdAt || ahora.toISOString(),
    createdAt: original?.createdAt || original?.receivedAt || ahora.toISOString(),
    updatedAt: ahora.toISOString(),
    rawTotal,
    total: formatCurrency(rawTotal),
    status: normalizarEstadoPedido(statusSelect.value),
    statusClass: getStatusClass(statusSelect.value),
    customerEmail: customerEmailInput.value.trim().toLocaleLowerCase("es") || original?.customerEmail || "",
    customerName: customerNameInput.value.trim() || mesaPedido?.clienteNombre || original?.customerName || "",
    tableId: mesaPedido?.id ?? original?.tableId ?? null,
    tableNumber: numeroMesa,
    createdByEmail: original?.createdByEmail || leerSesion()?.correo || "",
    meseroEmail: original?.meseroEmail || (normalizarRolPedido(leerSesion()?.rol) === "mesero" ? leerSesion()?.correo || "" : ""),
    items: draftItems.map(item => ({ ...item, optionsOpen: undefined }))
  };
  if (original) ordersData = ordersData.map(order => order.id === original.id ? pedido : order);
  else ordersData.unshift(pedido);
  if (!guardarPedidos()) return;
  relacionarPedidosConMesas();
  window.dispatchEvent(new CustomEvent(eventoPedidos, { detail: ordersData }));
  if (carritoOrigenKey) {
    localStorage.removeItem(carritoOrigenKey);
    carritoOrigenKey = "";
  }
  cerrarModalPedido();
  renderOrders();
}

window.deleteOrder = function(id) {
  if (!confirm(`¿Eliminar el pedido ${id}?`)) return;
  ordersData = ordersData.filter(order => order.id !== id);
  guardarPedidos();
  relacionarPedidosConMesas();
  renderOrders();
};

function entregarPedido(id) {
  if (!esMeseroSesion()) {
    document.getElementById("pedido-notificacion").textContent = "Solo el mesero responsable puede entregar el pedido.";
    return;
  }
  const pedido = ordersData.find(actual => String(actual.id) === String(id));
  if (!pedido || normalizarEstadoPedido(pedido.status) !== "Listo para servir") {
    document.getElementById("pedido-notificacion").textContent = "El pedido debe estar listo para servir antes de entregarlo.";
    return;
  }
  const correoResponsable = String(pedido.meseroEmail || "").toLocaleLowerCase("es");
  if (correoResponsable && correoResponsable !== String(leerSesion()?.correo || "").toLocaleLowerCase("es")) {
    document.getElementById("pedido-notificacion").textContent = "Este pedido está asignado a otro mesero.";
    return;
  }
  ordersData = ordersData.map(actual => String(actual.id) === String(id)
    ? { ...actual, status: "Entregado", statusClass: getStatusClass("Entregado"), deliveredAt: new Date().toISOString(), updatedAt: new Date().toISOString() }
    : actual);
  if (!guardarPedidos()) return;
  relacionarPedidosConMesas();
  window.dispatchEvent(new CustomEvent(eventoPedidos, { detail: ordersData }));
  document.getElementById("pedido-notificacion").textContent = `Pedido ${id} entregado al cliente.`;
  renderOrders();
}

orderCartItems.addEventListener("click", evento => {
  const removeButton = evento.target.closest("[data-item-remove]");
  if (removeButton) {
    draftItems = draftItems.filter(item => String(item.id) !== removeButton.dataset.itemRemove);
    renderDraftItems();
    return;
  }
  const optionsButton = evento.target.closest("[data-item-options]");
  if (optionsButton) {
    const item = draftItems.find(line => String(line.id) === optionsButton.dataset.itemOptions);
    item.optionsOpen = !item.optionsOpen;
    renderDraftItems();
    return;
  }
  const addExtraButton = evento.target.closest("[data-extra-add]");
  if (addExtraButton) {
    guardarExtraDeLinea(addExtraButton.dataset.extraAdd);
    return;
  }
  const removeExtraButton = evento.target.closest("[data-extra-remove]");
  if (removeExtraButton) {
    const item = draftItems.find(line => String(line.id) === removeExtraButton.dataset.extraRemove);
    item.extras.splice(Number(removeExtraButton.dataset.extraIndex), 1);
    renderDraftItems();
  }
});

orderCartItems.addEventListener("change", evento => {
  const id = evento.target.dataset.itemQuantity || evento.target.dataset.itemCooking || evento.target.dataset.itemExclude || evento.target.dataset.itemRecipeOption;
  if (!id) return;
  const item = draftItems.find(line => String(line.id) === String(id));
  if (!item) return;
  if (evento.target.matches("[data-item-quantity]")) item.cantidad = Math.max(1, Math.min(99, Math.floor(Number(evento.target.value) || 1)));
  if (evento.target.matches("[data-item-cooking]")) item.terminoCoccion = evento.target.value;
  if (evento.target.matches("[data-item-recipe-option]")) item.alternativaReceta = evento.target.value;
  if (evento.target.matches("[data-item-exclude]")) {
    const excluidos = new Set(item.ingredientesExcluidos);
    if (evento.target.checked) excluidos.add(evento.target.value);
    else excluidos.delete(evento.target.value);
    item.ingredientesExcluidos = [...excluidos];
  }
  renderDraftItems();
});

btnNewOrder.addEventListener("click", iniciarPedido);
btnCloseModal.addEventListener("click", cerrarModalPedido);
btnCancelModal.addEventListener("click", cerrarModalPedido);
orderForm.addEventListener("submit", guardarPedido);
channelSelect.addEventListener("change", actualizarDestino);
tableInput.addEventListener("change", () => {
  const opcion = tableInput.selectedOptions[0];
  if (!opcion?.value) return;
  customerNameInput.value = opcion.dataset.clienteNombre || "";
  customerEmailInput.value = opcion.dataset.clienteCorreo || "";
});
document.getElementById("btnAddProduct").addEventListener("click", () => {
  if (!productSelect.value) return;
  const producto = leerProductosPedido().find(item => String(item.id) === productSelect.value);
  if (!producto) {
    document.getElementById("orderCartMessage").textContent = "El producto ya no está disponible para agregar.";
    cargarProductosSelect();
    return;
  }
  const existente = draftItems.find(item => String(item.id) === String(producto.id));
  if (existente) existente.cantidad = Math.min(99, existente.cantidad + 1);
  else draftItems.push({
    id: producto.id,
    nombre: producto.nombre,
    precio: Number(producto.precio),
    cantidad: 1,
    ingredientes: Array.isArray(producto.ingredientes) ? producto.ingredientes : String(producto.ingredientes || "").split(",").map(item => item.trim()).filter(Boolean),
    alternativaReceta: obtenerAlternativaDisponible(producto, window.RequestyInventario.leerInventario()),
    terminoCoccion: "",
    ingredientesExcluidos: [],
    extras: [],
    optionsOpen: false
  });
  productSelect.value = "";
  document.getElementById("btnAddProduct").disabled = true;
  renderDraftItems();
});
productSelect.addEventListener("change", () => {
  const opcionSeleccionada = productSelect.selectedOptions[0];
  document.getElementById("btnAddProduct").disabled = !productSelect.value || opcionSeleccionada.disabled;
});
document.getElementById("orderCartItems").addEventListener("input", evento => {
  if (evento.target.matches("[data-item-quantity]")) {
    const item = draftItems.find(line => String(line.id) === evento.target.dataset.itemQuantity);
    if (item) {
      item.cantidad = Math.max(1, Math.min(99, Math.floor(Number(evento.target.value) || 1)));
      document.getElementById("orderTotal").textContent = formatCurrency(draftItems.reduce((total, line) => total + totalItem(line), 0));
    }
  }
});

filterBtns.forEach(btn => btn.addEventListener("click", () => {
  filterBtns.forEach(tab => tab.classList.remove("active"));
  btn.classList.add("active");
  activeFilter = btn.dataset.filter;
  renderOrders();
}));
searchInput.addEventListener("input", () => {
  searchQuery = searchInput.value;
  renderOrders();
});
ordersTbody.addEventListener("click", evento => {
  const deliverButton = evento.target.closest("[data-order-deliver]");
  if (deliverButton) {
    entregarPedido(deliverButton.dataset.orderDeliver);
    return;
  }
  const editButton = evento.target.closest("[data-order-edit]");
  if (editButton) editarPedido(editButton.dataset.orderEdit);
  const deleteButton = evento.target.closest("[data-order-delete]");
  if (deleteButton) window.deleteOrder(deleteButton.dataset.orderDelete);
});
document.getElementById("btnNotifications").addEventListener("click", () => {
  const notificacion = document.getElementById("pedido-notificacion");
  if (!notificacion.textContent) notificacion.textContent = "Los cambios de estado de tus pedidos aparecerán aquí.";
});
document.getElementById("serverStatus").addEventListener("click", () => {
  const text = document.getElementById("serverStatusText");
  const dot = document.querySelector(".status-dot");
  const pausado = text.textContent === "Servidor Activo";
  text.textContent = pausado ? "Servidor Pausado" : "Servidor Activo";
  dot.style.backgroundColor = pausado ? "#d93838" : "#4caf50";
});

window.addEventListener(window.RequestyInventario.eventoInventario, () => {
  if (orderModal.classList.contains("active")) {
    cargarProductosSelect();
    renderDraftItems();
  }
});
window.addEventListener("storage", evento => {
  if (evento.key === "requestyPedidos") actualizarPedidosDesdeOtraInterfaz(evento.newValue);
  if (evento.key === "requestyMesas" && orderModal.classList.contains("active") && channelSelect.value === "Mesa") {
    cargarMesasPedido(tableInput.value);
  }
  if (evento.key === window.RequestyInventario.claveInventario && orderModal.classList.contains("active")) {
    cargarProductosSelect();
    renderDraftItems();
  }
});
window.addEventListener("requesty-mesas-actualizadas", () => {
  if (orderModal.classList.contains("active") && channelSelect.value === "Mesa") cargarMesasPedido(tableInput.value);
});

function actualizarPedidosDesdeOtraInterfaz(datos) {
  try {
    const pedidosNuevos = JSON.parse(datos || "[]");
    if (!Array.isArray(pedidosNuevos)) throw new Error("La lista de pedidos sincronizada no es válida.");
    const anteriores = new Map(ordersData.map(pedido => [String(pedido.id), normalizarEstadoPedido(pedido.status)]));
    const sesion = leerSesion();
    const correoSesion = String(sesion?.correo || "").toLocaleLowerCase("es");
    const rolSesion = normalizarRolPedido(sesion?.rol);
    const cambios = pedidosNuevos.filter(pedido => {
      const estadoNuevo = normalizarEstadoPedido(pedido.status || pedido.estado);
      const responsable = rolSesion === "mesero" ? pedido.meseroEmail || pedido.createdByEmail : pedido.createdByEmail;
      return String(responsable || "").toLocaleLowerCase("es") === correoSesion
        && anteriores.has(String(pedido.id))
        && anteriores.get(String(pedido.id)) !== estadoNuevo;
    });
    ordersData = pedidosNuevos.map(pedido => ({ ...pedido, status: normalizarEstadoPedido(pedido.status || pedido.estado) }));
    renderOrders();
    if (cambios.length) {
      const pedido = cambios[cambios.length - 1];
      const mensaje = `El pedido ${pedido.id} cambió a ${normalizarEstadoPedido(pedido.status || pedido.estado)}.`;
      const notificacion = document.getElementById("pedido-notificacion");
      notificacion.textContent = mensaje;
      window.setTimeout(() => {
        if (notificacion.textContent === mensaje) notificacion.textContent = "";
      }, 8000);
    }
  } catch (error) {
    document.getElementById("pedido-notificacion").textContent = error.message || "No se pudieron sincronizar los pedidos.";
  }
}

renderOrders();