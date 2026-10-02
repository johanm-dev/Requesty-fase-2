const clavePedidosCaja = "requestyPedidos";
const claveFacturasCaja = "requestyFacturas";
const claveInicioDiaCaja = "requestyInicioNuevoDia";
const estadosCobro = new Set(["entregado"]);
let pedidoSeleccionado = null;

function normalizarTextoCaja(valor) {
    return String(valor || "").trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLocaleLowerCase("es");
}

const modoClienteCaja = normalizarTextoCaja(leerSesion()?.rol) === "cliente";

function normalizarEstadoPedidoCaja(pedido) {
    const estado = normalizarTextoCaja(pedido?.status || pedido?.estado).replace(/\s+/g, " ");
    if (["entregado", "delivered"].includes(estado)) return "entregado";
    return estado;
}

function pedidoPerteneceAMesa(pedido, mesa) {
    const numeroPedido = Number(String(pedido.tableNumber || "").replace(/\D/g, ""));
    if (numeroPedido) return numeroPedido === Number(mesa.numero);
    if (pedido.tableId && mesa.id && String(pedido.tableId) === String(mesa.id)) return true;
    const destino = normalizarTextoCaja(pedido.destination);
    return new RegExp(`\\bmesa\\s*${Number(mesa.numero)}\\b`).test(destino);
}

function escaparCaja(valor) {
    return String(valor ?? "").replace(/[&<>"']/g, caracter => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    })[caracter]);
}

function formatearDinero(valor) {
    return `$${Number(valor || 0).toLocaleString("es-CO")}`;
}

function facturasDelDiaCaja() {
    const facturas = leerListaLocal(claveFacturasCaja, "facturas");
    const inicioDia = Date.parse(localStorage.getItem(claveInicioDiaCaja) || "");
    if (!Number.isFinite(inicioDia)) return facturas;
    return facturas.filter(factura => {
        const fecha = Date.parse(factura.fecha || "");
        return Number.isFinite(fecha) && fecha >= inicioDia;
    });
}

function leerListaLocal(clave, nombre) {
    try {
        const datos = JSON.parse(localStorage.getItem(clave) || "[]");
        if (!Array.isArray(datos)) throw new Error(`Los datos de ${nombre} no tienen un formato válido.`);
        return datos;
    } catch (error) {
        mostrarMensaje(error.message || `No se pudieron consultar ${nombre}.`, "error");
        return [];
    }
}

function mostrarMensaje(texto, tipo = "success") {
    const mensaje = document.getElementById("mensaje-venta");
    mensaje.textContent = texto;
    mensaje.className = `mensaje-venta ${tipo === "error" ? "error" : ""}`.trim();
}

function precioTotalPedido(pedido) {
    return Number.isFinite(Number(pedido.rawTotal))
        ? Number(pedido.rawTotal)
        : (pedido.items || []).reduce((total, item) => {
            const extras = (item.extras || []).reduce((suma, extra) => suma + Number(extra.precio || 0) * Number(extra.cantidad || 0), 0);
            return total + (Number(item.precio || 0) + extras) * Number(item.cantidad || 1);
        }, 0);
}

function pedidosPendientesDeCobro(mesa) {
    const facturas = leerListaLocal(claveFacturasCaja, "facturas");
    const facturados = new Set(facturas.map(factura => String(factura.pedidoId)));
    return leerListaLocal(clavePedidosCaja, "pedidos")
        .filter(pedido => pedidoPerteneceAMesa(pedido, mesa)
            && estadosCobro.has(normalizarEstadoPedidoCaja(pedido))
            && !pedido.paidAt
            && !pedido.invoiceId
            && !facturados.has(String(pedido.id)))
        .sort((a, b) => Date.parse(a.receivedAt || a.createdAt || 0) - Date.parse(b.receivedAt || b.createdAt || 0));
}

function buscarClienteYMesa(idPedidoPreferido = "") {
    pedidoSeleccionado = null;
    document.getElementById("mesa-venta").value = "";
    document.getElementById("order-select").innerHTML = '<option value="">Busca al cliente por nombre y correo</option>';
    document.getElementById("order-select").disabled = true;
    document.getElementById("pedido-resumen").innerHTML = "<p>Ingresa el nombre y el correo asociados a una mesa ocupada.</p>";
    actualizarTotales(0);

    const nombre = normalizarTextoCaja(document.getElementById("client-name").value);
    const correo = normalizarTextoCaja(document.getElementById("client-email").value);
    if (!nombre || !correo) {
        mostrarMensaje("");
        return;
    }
    const mesa = leerListaLocal("requestyMesas", "mesas").find(actual =>
        actual.estado === "ocupada"
        && normalizarTextoCaja(actual.clienteNombre) === nombre
        && normalizarTextoCaja(actual.clienteCorreo) === correo
    );
    if (!mesa) {
        mostrarMensaje("No encontramos una mesa ocupada con ese nombre y correo.", "error");
        return;
    }
    document.getElementById("mesa-venta").value = `Mesa ${mesa.numero}`;
    document.getElementById("client-name").value = mesa.clienteNombre || document.getElementById("client-name").value.trim();
    const pedidos = pedidosPendientesDeCobro(mesa);
    if (!pedidos.length) {
        mostrarMensaje("La mesa fue identificada, pero todavía no tiene pedidos entregados pendientes de pago.", "error");
        return;
    }
    const selector = document.getElementById("order-select");
    selector.innerHTML = pedidos.map(pedido =>
        `<option value="${escaparCaja(pedido.id)}">Pedido ${escaparCaja(pedido.id)} · ${formatearDinero(precioTotalPedido(pedido))}</option>`
    ).join("");
    selector.disabled = false;
    const idSeleccionado = pedidos.some(pedido => String(pedido.id) === String(idPedidoPreferido))
        ? idPedidoPreferido
        : pedidos[0].id;
    seleccionarPedidoCaja(idSeleccionado);
    mostrarMensaje(`${pedidos.length} pedido(s) listo(s) para cobrar.`);
}

function seleccionarPedidoCaja(id) {
    const mesaNumero = Number(document.getElementById("mesa-venta").value.replace(/\D/g, ""));
    const mesa = leerListaLocal("requestyMesas", "mesas").find(actual => Number(actual.numero) === mesaNumero);
    if (!mesa) return;
    pedidoSeleccionado = pedidosPendientesDeCobro(mesa).find(pedido => String(pedido.id) === String(id)) || null;
    if (!pedidoSeleccionado) {
        document.getElementById("pedido-resumen").innerHTML = "<p>El pedido ya fue cobrado o dejó de estar disponible.</p>";
        actualizarTotales(0);
        return;
    }
    const items = Array.isArray(pedidoSeleccionado.items) ? pedidoSeleccionado.items : [];
    const filas = items.map(item => {
        const cantidad = Math.max(1, Number(item.cantidad) || 1);
        const extras = (item.extras || []).reduce((total, extra) => total + Number(extra.precio || 0) * Number(extra.cantidad || 0), 0);
        const unitario = Number(item.precio || 0) + extras;
        return `<tr><td>${escaparCaja(item.nombre)}</td><td>${cantidad}</td><td>${formatearDinero(unitario)}</td><td>${formatearDinero(unitario * cantidad)}</td></tr>`;
    }).join("");
    document.getElementById("pedido-resumen").innerHTML = `
        <p><strong>Pedido:</strong> ${escaparCaja(pedidoSeleccionado.id)}</p>
        <p><strong>Cliente:</strong> ${escaparCaja(pedidoSeleccionado.customerName || mesa.clienteNombre || "")}</p>
        <p><strong>Mesa:</strong> ${Number(mesa.numero)}</p>
        <div class="tabla-pedido-scroll"><table><thead><tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Total</th></tr></thead><tbody>${filas}</tbody></table></div>`;
    actualizarTotales(precioTotalPedido(pedidoSeleccionado));
}

function actualizarTotales(subtotal) {
    const propina = Math.max(0, Number(document.getElementById("propina")?.value || 0));
    document.getElementById("txt-subtotal").textContent = formatearDinero(subtotal);
    document.getElementById("txt-iva").textContent = formatearDinero(0);
    document.getElementById("txt-propina").textContent = formatearDinero(propina);
    document.getElementById("txt-total").textContent = formatearDinero(subtotal + propina);
}

function guardarCobro(pedido, factura) {
    const pedidos = leerListaLocal(clavePedidosCaja, "pedidos");
    const indice = pedidos.findIndex(actual => String(actual.id) === String(pedido.id));
    if (indice < 0) throw new Error("El pedido ya no existe.");
    if (!estadosCobro.has(normalizarEstadoPedidoCaja(pedidos[indice]))) {
        throw new Error("El pedido debe estar entregado antes de registrar el pago.");
    }
    if (pedidos[indice].paidAt || pedidos[indice].invoiceId) throw new Error("Este pedido ya tiene un cobro registrado.");
    const facturas = leerListaLocal(claveFacturasCaja, "facturas");
    if (facturas.some(actual => String(actual.pedidoId) === String(pedido.id))) throw new Error("Ya existe una factura para este pedido.");

    const pedidosAnteriores = localStorage.getItem(clavePedidosCaja);
    const facturasAnteriores = localStorage.getItem(claveFacturasCaja);
    pedidos[indice] = { ...pedidos[indice], invoiceId: factura.numero, paidAt: factura.fecha, paymentMethod: factura.metodo };
    try {
        localStorage.setItem(claveFacturasCaja, JSON.stringify([...facturas, factura]));
        localStorage.setItem(clavePedidosCaja, JSON.stringify(pedidos));
    } catch {
        try {
            if (facturasAnteriores === null) localStorage.removeItem(claveFacturasCaja);
            else localStorage.setItem(claveFacturasCaja, facturasAnteriores);
            if (pedidosAnteriores === null) localStorage.removeItem(clavePedidosCaja);
            else localStorage.setItem(clavePedidosCaja, pedidosAnteriores);
        } catch (errorRollback) {
            throw new Error(`No se pudo completar el cobro y falló la reversión de almacenamiento: ${errorRollback.message}`);
        }
        throw new Error("No se pudo guardar la factura y el pedido. No se confirmó el cobro.");
    }
    window.dispatchEvent(new StorageEvent("storage", { key: claveFacturasCaja, newValue: JSON.stringify([...facturas, factura]) }));
    window.dispatchEvent(new StorageEvent("storage", { key: clavePedidosCaja, newValue: JSON.stringify(pedidos) }));
}

function procesarCobro() {
    if (modoClienteCaja) return;
    if (!pedidoSeleccionado) {
        mostrarMensaje("Busca al cliente y selecciona un pedido listo antes de cobrar.", "error");
        return;
    }
    if (!estadosCobro.has(normalizarEstadoPedidoCaja(pedidoSeleccionado))) {
        mostrarMensaje("El pedido debe estar entregado antes de registrar el pago.", "error");
        return;
    }
    const propina = Number(document.getElementById("propina").value || 0);
    if (!Number.isFinite(propina) || propina < 0) {
        mostrarMensaje("Ingresa una propina válida.", "error");
        return;
    }
    const facturas = leerListaLocal(claveFacturasCaja, "facturas");
    const consecutivo = Math.max(1000, ...facturas.map(factura => Number(String(factura.numero || "").replace(/\D/g, "")) || 0)) + 1;
    const fecha = new Date().toISOString();
    const correoMesero = String(pedidoSeleccionado.meseroEmail || pedidoSeleccionado.createdByEmail || "");
    const mesero = leerListaLocal("requestyUsuarios", "usuarios").find(usuario => normalizarTextoCaja(usuario.correo) === normalizarTextoCaja(correoMesero));
    const factura = {
        numero: `FE-${consecutivo}`,
        pedidoId: pedidoSeleccionado.id,
        fecha,
        clienteNombre: pedidoSeleccionado.customerName || document.getElementById("client-name").value.trim(),
        clienteCorreo: pedidoSeleccionado.customerEmail || document.getElementById("client-email").value.trim().toLocaleLowerCase("es"),
        mesa: pedidoSeleccionado.tableNumber,
        meseroEmail: correoMesero,
        meseroNombre: mesero?.nombre || correoMesero || "No registrado",
        items: pedidoSeleccionado.items || [],
        subtotal: precioTotalPedido(pedidoSeleccionado),
        impuestos: 0,
        propina,
        total: precioTotalPedido(pedidoSeleccionado) + propina,
        metodo: document.getElementById("payment-method").value,
        estado: "Pendiente de validación DIAN",
        referenciaDIAN: ""
    };
    const pedidoIdCobrado = pedidoSeleccionado.id;
    try {
        guardarCobro(pedidoSeleccionado, factura);
        renderizarFacturas();
        buscarClienteYMesa();
        mostrarMensaje(`Cobro registrado para el pedido ${pedidoIdCobrado}. Factura ${factura.numero} pendiente de validación DIAN.`, "success");
    } catch (error) {
        mostrarMensaje(error.message || "No se pudo registrar el cobro.", "error");
    }
}

function renderizarFacturas() {
    const facturas = leerListaLocal(claveFacturasCaja, "facturas").sort((a, b) => Date.parse(b.fecha) - Date.parse(a.fecha));
    document.getElementById("facturas-historial").innerHTML = facturas.length ? facturas.map(factura => {
        const detalleItems = (factura.items || []).map(item => {
            const total = (Number(item.precio || 0) + (item.extras || []).reduce((suma, extra) => suma + Number(extra.precio || 0) * Number(extra.cantidad || 0), 0)) * Number(item.cantidad || 1);
            return `<li>${Number(item.cantidad) || 1} × ${escaparCaja(item.nombre)} — ${formatearDinero(total)}</li>`;
        }).join("");
        return `<tr>
            <td><strong>${escaparCaja(factura.numero)}</strong></td><td>${escaparCaja(factura.pedidoId)}</td>
            <td>${escaparCaja(factura.clienteNombre)}<br>${escaparCaja(factura.clienteCorreo)}</td>
            <td>${escaparCaja(factura.mesa)}</td><td>${formatearDinero(factura.total)}</td><td>${escaparCaja(factura.metodo)}</td>
            <td>${escaparCaja(factura.estado)}</td><td><details><summary>Ver factura</summary>
                <p>Mesero: ${escaparCaja(factura.meseroNombre)}</p><p>Correo mesero: ${escaparCaja(factura.meseroEmail)}</p>
                <p>Fecha: ${escaparCaja(new Date(factura.fecha).toLocaleString("es-CO"))}</p>
                <ul>${detalleItems}</ul><p>Subtotal: ${formatearDinero(factura.subtotal)}</p>
                <p>Propina: ${formatearDinero(factura.propina)}</p><p>Total: ${formatearDinero(factura.total)}</p>
                <p>${escaparCaja(factura.estado)}${factura.referenciaDIAN ? ` · ${escaparCaja(factura.referenciaDIAN)}` : ""}</p>
            </details></td></tr>`;
    }).join("") : '<tr><td colspan="8">Todavía no hay facturas registradas.</td></tr>';
    actualizarDatosCierre();
}

function actualizarDatosCierre() {
    const facturas = facturasDelDiaCaja();
    const efectivo = facturas.filter(factura => factura.metodo === "Efectivo").reduce((total, factura) => total + Number(factura.total || 0), 0);
    const electronico = facturas.filter(factura => factura.metodo !== "Efectivo").reduce((total, factura) => total + Number(factura.total || 0), 0);
    document.getElementById("cierre-efectivo-sistema").textContent = formatearDinero(efectivo);
    document.getElementById("cierre-electronico-sistema").textContent = formatearDinero(electronico);
}

function ejecutarCierreCaja() {
    const efectivo = Number(document.getElementById("efectivo-manual").value);
    if (document.getElementById("efectivo-manual").value === "" || !Number.isFinite(efectivo)) {
        mostrarMensaje("Ingresa una cantidad válida para el cierre.", "error");
        return;
    }
    const facturas = facturasDelDiaCaja();
    const sistema = facturas.filter(factura => factura.metodo === "Efectivo").reduce((total, factura) => total + Number(factura.total || 0), 0);
    const total = facturas.reduce((suma, factura) => suma + Number(factura.total || 0), 0);
    const diferencia = efectivo - sistema;
    const estado = diferencia === 0 ? "Cuadrada" : diferencia < 0
        ? `Faltante de ${formatearDinero(Math.abs(diferencia))}` : `Sobrante de ${formatearDinero(diferencia)}`;
    document.getElementById("resultado-cierre-box").innerHTML = `<h4>REPORTE DE CIERRE</h4><p>Total ventas: ${formatearDinero(total)}</p><p>Efectivo reportado: ${formatearDinero(efectivo)}</p><p>Efectivo según sistema: ${formatearDinero(sistema)}</p><p><strong>Resultado: ${escaparCaja(estado)}</strong></p>`;
}

function switchTab(tabId) {
    if (modoClienteCaja) return;
    ["ventas", "facturas", "cierre"].forEach(id => { document.getElementById(id).style.display = id === tabId ? "block" : "none"; });
    if (tabId === "facturas") renderizarFacturas();
    if (tabId === "cierre") actualizarDatosCierre();
}

function actualizarCajaSinPerderSeleccion() {
    const seleccionado = document.getElementById("order-select").value;
    buscarClienteYMesa(seleccionado);
}

if (!modoClienteCaja) {
    document.getElementById("client-name").addEventListener("input", buscarClienteYMesa);
    document.getElementById("client-email").addEventListener("input", buscarClienteYMesa);
    document.getElementById("order-select").addEventListener("change", evento => seleccionarPedidoCaja(evento.target.value));
    document.getElementById("propina").addEventListener("input", () => actualizarTotales(pedidoSeleccionado ? precioTotalPedido(pedidoSeleccionado) : 0));
    window.addEventListener("storage", evento => {
        if ([clavePedidosCaja, "requestyMesas", claveFacturasCaja].includes(evento.key)) {
            buscarClienteYMesa();
            renderizarFacturas();
        }
    });
    window.addEventListener("requesty-pedidos-actualizados", actualizarCajaSinPerderSeleccion);
    window.addEventListener("requesty-mesas-actualizadas", actualizarCajaSinPerderSeleccion);
    window.addEventListener("focus", actualizarCajaSinPerderSeleccion);
    document.addEventListener("visibilitychange", () => {
        if (!document.hidden) actualizarCajaSinPerderSeleccion();
    });
    window.setInterval(() => {
        if (!document.hidden) actualizarCajaSinPerderSeleccion();
    }, 2000);
}

document.addEventListener("DOMContentLoaded", () => {
    if (modoClienteCaja) {
        document.querySelector("main > header h2").textContent = "Pago presencial en caja";
        document.querySelector("main > header nav").hidden = true;
        document.getElementById("vista-caja-cliente").hidden = false;
        document.getElementById("ventas").hidden = true;
        document.getElementById("facturas").hidden = true;
        document.getElementById("cierre").hidden = true;
        renderizarPedidosClienteCaja();
        return;
    }
    renderizarFacturas();
    actualizarTotales(0);
});

function renderizarPedidosClienteCaja() {
    if (!modoClienteCaja) return;
    const contenedor = document.getElementById("pedidos-cliente-caja");
    const correo = normalizarTextoCaja(leerSesion()?.correo);
    const pedidoSolicitado = new URLSearchParams(window.location.search).get("pedido");
    if (!correo) {
        contenedor.textContent = "No pudimos identificar tu sesión. Vuelve a iniciar sesión para consultar tus pedidos.";
        return;
    }
    const facturados = new Set(leerListaLocal(claveFacturasCaja, "facturas").map(factura => String(factura.pedidoId)));
    const pendientes = leerListaLocal(clavePedidosCaja, "pedidos").filter(pedido =>
        normalizarTextoCaja(pedido.customerEmail) === correo
        && estadosCobro.has(normalizarEstadoPedidoCaja(pedido))
        && !pedido.paidAt
        && !pedido.invoiceId
        && !facturados.has(String(pedido.id))
        && (!pedidoSolicitado || String(pedido.id) === String(pedidoSolicitado))
    );
    if (!pendientes.length) {
        contenedor.textContent = pedidoSolicitado
            ? "Este pedido ya fue pagado o no pertenece a tu cuenta."
            : "No tienes pedidos entregados pendientes de pago. Tu historial completo está en el menú.";
        return;
    }
    contenedor.innerHTML = pendientes.map(pedido => {
        const productos = (pedido.items || []).map(item => {
            const cantidad = Math.max(1, Number(item.cantidad) || 1);
            const extras = (item.extras || []).reduce((total, extra) => total + Number(extra.precio || 0) * Number(extra.cantidad || 0), 0);
            const total = (Number(item.precio || 0) + extras) * cantidad;
            return `<li>${cantidad} × ${escaparCaja(item.nombre)} — ${formatearDinero(total)}</li>`;
        }).join("");
        return `<article class="pedido-caja-cliente">
            <h4>Pedido ${escaparCaja(pedido.id)} · Mesa ${escaparCaja(pedido.tableNumber || "—")}</h4>
            <ul>${productos || "<li>Sin productos</li>"}</ul>
            <p><strong>Total pendiente:</strong> ${formatearDinero(precioTotalPedido(pedido))}</p>
            <p>Estado: entregado · pendiente de pago presencial</p>
        </article>`;
    }).join("");
}

if (modoClienteCaja) {
    window.addEventListener("storage", evento => {
        if ([clavePedidosCaja, claveFacturasCaja].includes(evento.key)) renderizarPedidosClienteCaja();
    });
    window.addEventListener("requesty-pedidos-actualizados", renderizarPedidosClienteCaja);
    window.addEventListener("focus", renderizarPedidosClienteCaja);
}
