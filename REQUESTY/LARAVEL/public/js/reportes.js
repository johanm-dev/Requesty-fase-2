// Módulo de Reportes, Menú/Recetas, PQRS Admin, Asistencia y Auditoría 100% PostgreSQL
(function () {
    let chart = null;
    let reporteActual = null;
    let productosCache = [];
    let categoriasCache = [];
    let pqrsCache = [];
    let productoEditandoId = null;
    let categoriaActual = "Todas";
    let busquedaMenu = "";
    let pqrsEditandoId = null;

    function esc(v) {
        return window.RequestyAPI.escaparHTML(v);
    }

    function formatoDinero(valor) {
        return `$${Number(valor || 0).toLocaleString("es-CO")}`;
    }

    function initializeDates() {
        const today = new Date();
        const sevenDaysAgo = new Date(today.getTime() - 7 * 24 * 60 * 60 * 1000);
        const endEl = document.getElementById("endDate");
        const startEl = document.getElementById("startDate");
        if (endEl) endEl.valueAsDate = today;
        if (startEl) startEl.valueAsDate = sevenDaysAgo;
    }

    function renderChart(serie = []) {
        if (typeof Chart !== "function") return;
        const canvas = document.getElementById("salesChart");
        if (!canvas) return;
        const ctx = canvas.getContext("2d");
        const range = Number(document.getElementById("chartRange")?.value || 7);

        const porDia = new Map(serie.map((s) => [String(s.dia), Number(s.total_ventas || 0)]));
        const labels = [];
        const data = [];
        const today = new Date();
        for (let i = range - 1; i >= 0; i--) {
            const d = new Date(today);
            d.setDate(d.getDate() - i);
            const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
            labels.push(d.toLocaleDateString("es-CO", { month: "short", day: "numeric" }));
            data.push(porDia.get(key) || 0);
        }

        if (chart) chart.destroy();
        chart = new Chart(ctx, {
            type: "line",
            data: {
                labels,
                datasets: [{
                    label: "Ventas ($)",
                    data,
                    borderColor: "#9D2227",
                    backgroundColor: "rgba(157, 34, 39, 0.08)",
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }

    async function cargarReporte(registrarEnBd = false) {
        const startDate = document.getElementById("startDate")?.value;
        const endDate = document.getElementById("endDate")?.value;
        if (!startDate || !endDate || startDate > endDate) {
            document.getElementById("reporte-facturas").innerHTML = '<tr><td colspan="6">Selecciona un rango de fechas válido.</td></tr>';
            return;
        }

        try {
            const res = registrarEnBd
                ? await window.RequestyAPI.post("api/reportes", { fecha_inicio: startDate, fecha_fin: endDate, tipo: "VENTAS_PERIODO" })
                : await window.RequestyAPI.get("api/reportes", { fecha_inicio: startDate, fecha_fin: endDate });
            reporteActual = res?.reporte || null;
            if (!reporteActual) return;

            const k = reporteActual.kpis || {};
            document.getElementById("kpiVentas").textContent = formatoDinero(k.ventas_totales);
            document.getElementById("kpiPedidos").textContent = Number(k.pedidos_cobrados || 0);

            const top = reporteActual.top_productos || [];
            document.getElementById("productos-mas-vendidos").innerHTML = top.length
                ? top.map((p, i) => `<div class="kpi-list-item"><strong>${i + 1}.</strong> ${esc(p.nombre)} · ${Number(p.cantidad_vendida)} vendidos</div>`).join("")
                : '<div class="kpi-list-item">Sin ventas registradas</div>';

            const picos = reporteActual.horas_pico || [];
            document.getElementById("horas-pico-lista").innerHTML = picos.length
                ? picos.map((h) => `<div class="kpi-list-item"><strong>${String(h.hora).padStart(2, "0")}:00–${String((Number(h.hora) + 1) % 24).padStart(2, "0")}:00</strong> · ${Number(h.cantidad_pedidos)} pedidos</div>`).join("")
                : '<div class="kpi-list-item">Sin ventas registradas</div>';

            const facs = reporteActual.facturas || [];
            document.getElementById("reporte-facturas").innerHTML = facs.length
                ? facs.map((f) => `<tr>
                    <td>${esc(f.numero_factura)}</td>
                    <td>#${Number(f.id_pedido)}</td>
                    <td>${esc(f.mesero_nombre || "N/D")}</td>
                    <td>${esc(f.metodo_pago || "—")}</td>
                    <td>${formatoDinero(f.total)}</td>
                    <td><details><summary>Ver</summary><p>Cliente: ${esc(f.cliente_nombre || "Mostrador")}</p><p>Estado DIAN: ${esc(f.estado_dian)}</p><p>Fecha: ${esc(new Date(f.fecha).toLocaleString("es-CO"))}</p></details></td>
                </tr>`).join("")
                : '<tr><td colspan="6">No hay facturas registradas en este periodo.</td></tr>';

            renderChart(reporteActual.serie_diaria || []);
        } catch (err) {
            document.getElementById("reporte-facturas").innerHTML = `<tr><td colspan="6">${esc(err.message || "Error consultando reporte")}</td></tr>`;
        }
    }

    function exportarCsv() {
        const facs = reporteActual?.facturas || [];
        if (!facs.length) {
            window.alert("No hay facturas en el rango seleccionado para exportar.");
            return;
        }
        const filas = [
            ["Factura", "Pedido", "Mesero", "Cliente", "MetodoPago", "Total", "EstadoDIAN", "Fecha"],
            ...facs.map((f) => [f.numero_factura, f.id_pedido, f.mesero_nombre || "", f.cliente_nombre || "", f.metodo_pago || "", f.total, f.estado_dian, f.fecha])
        ];
        const csv = "\uFEFF" + filas.map((r) => r.map((c) => `"${String(c ?? "").replace(/"/g, '""')}"`).join(",")).join("\n");
        const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
        const link = document.createElement("a");
        link.href = URL.createObjectURL(blob);
        link.download = `reporte_requesty_${new Date().toISOString().slice(0, 10)}.csv`;
        link.click();
    }

    // ---------- ADMINISTRAR MENÚ (productos + categorias + producto_ingrediente) ----------
    async function cargarMenuAdmin() {
        try {
            const res = await window.RequestyAPI.get("api/productos", { todos: 1 });
            productosCache = res?.productos || [];
            categoriasCache = res?.categorias || [];
            const catSelect = document.getElementById("categoria");
            if (catSelect) {
                catSelect.innerHTML = categoriasCache.map((c) => `<option value="${esc(c.nombre)}">${esc(c.nombre)}</option>`).join("");
            }
            mostrarFiltrosMenu();
            mostrarCatalogoAdmin();
        } catch (err) {
            document.getElementById("mensaje-menu").textContent = err.message || "Error cargando menú.";
        }
    }

    function mostrarFiltrosMenu() {
        const filtros = document.getElementById("filtros");
        if (!filtros) return;
        const nombres = ["Todas", ...new Set(categoriasCache.map((c) => c.nombre))];
        filtros.innerHTML = nombres.map((c) => `<button type="button" class="filtro ${c === categoriaActual ? "activo" : ""}" data-categoria="${esc(c)}">${esc(c)}</button>`).join("");
    }

    function mostrarCatalogoAdmin() {
        const cont = document.getElementById("catalogo");
        if (!cont) return;
        const visibles = productosCache.filter((p) => {
            const catOk = categoriaActual === "Todas" || p.categoria === categoriaActual;
            const txt = `${p.nombre} ${(p.ingredientes || []).join(" ")}`.toLowerCase();
            return catOk && txt.includes(busquedaMenu);
        });
        document.getElementById("contador-productos").textContent = `${visibles.length} producto(s)`;
        cont.innerHTML = visibles.length ? visibles.map((p) => {
            const recTxt = (p.receta || []).map((r) => `${r.nombre}: ${r.cantidad} ${r.unidad_medida}`).join(", ") || "Sin receta";
            return `<article class="producto">
                <span class="categoria">${esc(p.categoria)}</span>
                <h3>${esc(p.nombre)}</h3>
                <p class="ingredientes"><strong>Receta BD:</strong> ${esc(recTxt)}</p>
                <p class="precio">${formatoDinero(p.precio)}</p>
                <p><small>Estado: ${p.disponible ? "Activo" : "Inactivo"}</small></p>
                <div class="acciones-producto">
                    <button type="button" class="editar" data-accion="editar" data-id="${Number(p.id_producto)}">Editar</button>
                    <button type="button" data-accion="estado" data-id="${Number(p.id_producto)}">${p.disponible ? "Desactivar" : "Activar"}</button>
                </div>
            </article>`;
        }).join("") : "<p>No hay productos.</p>";
    }

    function parseRecetaInputs() {
        const recetaRaw = document.getElementById("receta")?.value.trim() || "";
        const ingRaw = document.getElementById("ingredientes")?.value.trim() || "";
        const entries = [];
        if (recetaRaw.includes(":")) {
            recetaRaw.split(",").forEach((pair) => {
                const [nom, cant] = pair.split(":");
                if (nom && Number(cant) > 0) entries.push({ nombre: nom.trim(), cantidad: Number(cant) });
            });
        } else if (ingRaw) {
            ingRaw.split(",").forEach((nom) => {
                if (nom.trim()) entries.push({ nombre: nom.trim(), cantidad: 1 });
            });
        }
        return entries;
    }

    document.getElementById("formulario-producto")?.addEventListener("submit", async (e) => {
        e.preventDefault();
        const msg = document.getElementById("mensaje-menu");
        msg.textContent = "";
        const payload = {
            nombre: document.getElementById("nombre").value.trim(),
            precio: Number(document.getElementById("precio").value || 0),
            categoria: document.getElementById("categoria").value,
            imagen: document.getElementById("imagen-producto").value.trim() || null,
            receta: parseRecetaInputs()
        };
        try {
            if (productoEditandoId) {
                payload.id_producto = productoEditandoId;
                await window.RequestyAPI.put("api/productos", payload);
                msg.textContent = "Producto actualizado en PostgreSQL.";
            } else {
                await window.RequestyAPI.post("api/productos", payload);
                msg.textContent = "Producto creado en PostgreSQL.";
            }
            productoEditandoId = null;
            document.getElementById("formulario-producto").reset();
            document.getElementById("guardar-producto").textContent = "Agregar producto";
            document.getElementById("cancelar-edicion").classList.add("oculto");
            await cargarMenuAdmin();
        } catch (err) {
            msg.textContent = err.message || "Error guardando producto.";
        }
    });

    document.getElementById("catalogo")?.addEventListener("click", async (e) => {
        const btn = e.target.closest("button[data-accion]");
        if (!btn) return;
        const id = Number(btn.dataset.id);
        const prod = productosCache.find((p) => Number(p.id_producto) === id);
        if (!prod) return;
        if (btn.dataset.accion === "editar") {
            productoEditandoId = id;
            document.getElementById("nombre").value = prod.nombre;
            document.getElementById("precio").value = Number(prod.precio);
            document.getElementById("categoria").value = prod.categoria;
            document.getElementById("imagen-producto").value = prod.imagen || "";
            document.getElementById("ingredientes").value = (prod.ingredientes || []).join(", ");
            document.getElementById("receta").value = (prod.receta || []).map((r) => `${r.nombre}: ${r.cantidad}`).join(", ");
            document.getElementById("guardar-producto").textContent = "Guardar cambios";
            document.getElementById("cancelar-edicion").classList.remove("oculto");
        }
        if (btn.dataset.accion === "estado") {
            await window.RequestyAPI.put("api/productos", { id_producto: id, disponible: !prod.disponible });
            await cargarMenuAdmin();
        }
    });

    document.getElementById("cancelar-edicion")?.addEventListener("click", () => {
        productoEditandoId = null;
        document.getElementById("formulario-producto").reset();
        document.getElementById("guardar-producto").textContent = "Agregar producto";
        document.getElementById("cancelar-edicion").classList.add("oculto");
    });

    document.getElementById("filtros")?.addEventListener("click", (e) => {
        const btn = e.target.closest("button[data-categoria]");
        if (!btn) return;
        categoriaActual = btn.dataset.categoria;
        mostrarFiltrosMenu();
        mostrarCatalogoAdmin();
    });

    document.getElementById("buscar-producto")?.addEventListener("input", (e) => {
        busquedaMenu = e.target.value.trim().toLowerCase();
        mostrarCatalogoAdmin();
    });

    // ---------- PQRS ADMIN ----------
    async function cargarPqrsAdmin() {
        const tbody = document.getElementById("tabla-pqrs");
        if (!tbody) return;
        try {
            const res = await window.RequestyAPI.get("api/pqrs");
            pqrsCache = res?.pqrs || [];
            renderPqrsTabla();
        } catch {
            tbody.innerHTML = '<tr><td colspan="8">No se pudieron cargar los PQRS.</td></tr>';
        }
    }

    function renderPqrsTabla() {
        const tbody = document.getElementById("tabla-pqrs");
        const term = (document.getElementById("buscar")?.value || "").trim().toLowerCase();
        const tipo = document.getElementById("filtro-tipo")?.value || "";
        const estado = document.getElementById("filtro-estado")?.value || "";
        const visibles = pqrsCache.filter((p) =>
            (!tipo || p.tipo === tipo)
            && (!estado || p.estado === estado)
            && `${p.cliente_nombre || ""} ${p.descripcion}`.toLowerCase().includes(term)
        );
        document.getElementById("mensaje-vacio").style.display = visibles.length ? "none" : "block";
        tbody.innerHTML = visibles.map((p) => `<tr>
            <td>#${Number(p.id_pqrs)}</td>
            <td>${esc(p.tipo)}</td>
            <td>${esc(p.cliente_nombre || "Cliente")}</td>
            <td>${p.id_pedido ? `#${Number(p.id_pedido)}` : "—"}</td>
            <td>${esc(p.descripcion)}</td>
            <td>${esc(new Date(p.fecha).toLocaleDateString("es-CO"))}</td>
            <td>${esc(p.estado)}</td>
            <td><button type="button" class="btn-accion" data-pqrs-id="${Number(p.id_pqrs)}">Gestionar</button></td>
        </tr>`).join("");
    }

    document.getElementById("tabla-pqrs")?.addEventListener("click", (e) => {
        const btn = e.target.closest("button[data-pqrs-id]");
        if (!btn) return;
        const p = pqrsCache.find((x) => Number(x.id_pqrs) === Number(btn.dataset.pqrsId));
        if (!p) return;
        pqrsEditandoId = Number(p.id_pqrs);
        document.getElementById("modal-id").textContent = `#${p.id_pqrs}`;
        document.getElementById("modal-tipo").textContent = p.tipo;
        document.getElementById("modal-cliente").textContent = p.cliente_nombre || "Cliente";
        document.getElementById("modal-pedido").textContent = p.id_pedido ? `#${p.id_pedido}` : "Ninguno";
        document.getElementById("modal-fecha").textContent = new Date(p.fecha).toLocaleString("es-CO");
        document.getElementById("modal-descripcion").textContent = p.descripcion;
        document.getElementById("modal-estado").value = p.estado;
        document.getElementById("modal-detalle").style.display = "flex";
    });

    document.getElementById("modal-cerrar")?.addEventListener("click", () => {
        document.getElementById("modal-detalle").style.display = "none";
    });
    document.getElementById("modal-guardar")?.addEventListener("click", async () => {
        if (!pqrsEditandoId) return;
        await window.RequestyAPI.put("api/pqrs", {
            id_pqrs: pqrsEditandoId,
            estado: document.getElementById("modal-estado").value
        });
        document.getElementById("modal-detalle").style.display = "none";
        await cargarPqrsAdmin();
    });
    ["buscar", "filtro-tipo", "filtro-estado"].forEach((id) => {
        document.getElementById(id)?.addEventListener("input", renderPqrsTabla);
        document.getElementById(id)?.addEventListener("change", renderPqrsTabla);
    });

    // ---------- ASISTENCIA Y AUDITORÍA ----------
    async function cargarAsistenciaYActividad() {
        try {
            const [resAs, resAct] = await Promise.all([
                window.RequestyAPI.get("api/asistencia"),
                window.RequestyAPI.get("api/actividad")
            ]);
            const asist = resAs?.asistencia || [];
            document.getElementById("tabla-asistencia").innerHTML = asist.length ? asist.map((a) => `
                <tr>
                    <td>${esc(`${a.nombre} ${a.apellido || ""}`.trim())}</td>
                    <td>${esc(a.rol)}</td>
                    <td>${esc(a.fecha)}</td>
                    <td>${esc(new Date(a.hora_entrada).toLocaleTimeString("es-CO"))}</td>
                    <td>${a.hora_salida ? esc(new Date(a.hora_salida).toLocaleTimeString("es-CO")) : "En turno"}</td>
                    <td>${a.horas_trabajadas ?? "—"}</td>
                    <td>${a.horas_extra ?? "—"}</td>
                    <td>${!a.hora_salida ? `<button type="button" class="boton-secundario" data-salida-asistencia="${Number(a.id_asistencia)}">Registrar salida</button>` : "Cerrado"}</td>
                </tr>`).join("") : '<tr><td colspan="8">Sin registros de asistencia.</td></tr>';

            const acts = resAct?.actividad || [];
            document.getElementById("tabla-actividad").innerHTML = acts.length ? acts.map((ac) => `
                <tr>
                    <td>${esc(new Date(ac.fecha_hora).toLocaleString("es-CO"))}</td>
                    <td>${esc(ac.usuario_nombre || `Usuario #${ac.id_usuario}`)}</td>
                    <td>${esc(ac.modulo)}</td>
                    <td>${esc(ac.accion)}</td>
                    <td>${esc(ac.direccion_ip || "—")}</td>
                </tr>`).join("") : '<tr><td colspan="5">Sin eventos registrados.</td></tr>';
        } catch (err) {
            document.getElementById("mensaje-asistencia").textContent = err.message || "Error consultando asistencia.";
        }
    }

    document.getElementById("btn-marcar-entrada")?.addEventListener("click", async () => {
        try {
            await window.RequestyAPI.post("api/asistencia", { accion: "ENTRADA" });
            document.getElementById("mensaje-asistencia").textContent = "Entrada registrada en PostgreSQL.";
            await cargarAsistenciaYActividad();
        } catch (err) {
            document.getElementById("mensaje-asistencia").textContent = err.message || "No se pudo marcar entrada.";
        }
    });

    document.getElementById("tabla-asistencia")?.addEventListener("click", async (e) => {
        const btn = e.target.closest("button[data-salida-asistencia]");
        if (!btn) return;
        try {
            await window.RequestyAPI.post("api/asistencia", {
                accion: "SALIDA",
                id_asistencia: Number(btn.dataset.salidaAsistencia)
            });
            await cargarAsistenciaYActividad();
        } catch (err) {
            document.getElementById("mensaje-asistencia").textContent = err.message || "No se pudo cerrar jornada.";
        }
    });

    // ---------- NAVEGACIÓN DE SUB-VISTAS EN REPORTES ----------
    function mostrarVista(vistaId) {
        ["vista-reportes", "vista-menu", "vista-inventario", "vista-pqrs", "vista-cuentas", "vista-asistencia"].forEach((id) => {
            document.getElementById(id)?.classList.toggle("oculto", id !== vistaId);
        });
        if (vistaId === "vista-reportes") {
            history.replaceState(null, "", location.pathname + location.search);
            if (chart) chart.resize();
        } else if (vistaId === "vista-menu") {
            history.replaceState(null, "", "#menu");
            cargarMenuAdmin();
        } else if (vistaId === "vista-inventario") {
            history.replaceState(null, "", "#inventario");
            window.RequestyInventario?.renderizarInventario();
        } else if (vistaId === "vista-pqrs") {
            history.replaceState(null, "", "#pqrs");
            cargarPqrsAdmin();
        } else if (vistaId === "vista-cuentas") {
            history.replaceState(null, "", "#cuentas");
        } else if (vistaId === "vista-asistencia") {
            history.replaceState(null, "", "#asistencia");
            cargarAsistenciaYActividad();
        }
    }

    function comprobarHash() {
        const hash = (location.hash || "").toLowerCase();
        if (hash === "#cuentas") mostrarVista("vista-cuentas");
        else if (hash === "#inventario") mostrarVista("vista-inventario");
        else if (hash === "#menu") mostrarVista("vista-menu");
        else if (hash === "#pqrs") mostrarVista("vista-pqrs");
        else if (hash === "#asistencia") mostrarVista("vista-asistencia");
    }

    document.getElementById("btn-generar-reporte")?.addEventListener("click", () => cargarReporte(true));
    document.getElementById("btn-exportar-csv")?.addEventListener("click", exportarCsv);
    document.getElementById("chartRange")?.addEventListener("change", () => cargarReporte(false));
    document.getElementById("btn-administrar-menu")?.addEventListener("click", () => mostrarVista("vista-menu"));
    document.getElementById("btn-volver-reportes")?.addEventListener("click", () => mostrarVista("vista-reportes"));
    document.getElementById("btn-administrar-inventario")?.addEventListener("click", () => mostrarVista("vista-inventario"));
    document.getElementById("btn-volver-inventario")?.addEventListener("click", () => mostrarVista("vista-reportes"));
    document.getElementById("btn-ver-pqrs")?.addEventListener("click", () => mostrarVista("vista-pqrs"));
    document.getElementById("btn-volver-reportes-pqrs")?.addEventListener("click", () => mostrarVista("vista-reportes"));
    document.getElementById("btn-ver-cuentas")?.addEventListener("click", () => mostrarVista("vista-cuentas"));
    document.getElementById("volver-reportes-cuentas")?.addEventListener("click", () => mostrarVista("vista-reportes"));
    document.getElementById("btn-volver-cuentas-top")?.addEventListener("click", () => mostrarVista("vista-reportes"));
    document.getElementById("btn-ver-asistencia")?.addEventListener("click", () => mostrarVista("vista-asistencia"));
    document.getElementById("btn-volver-asistencia")?.addEventListener("click", () => mostrarVista("vista-reportes"));
    window.addEventListener("hashchange", comprobarHash);

    initializeDates();
    const iniciar = async () => {
        await cargarReporte(false);
        comprobarHash();
    };
    if (window.RequestyAPI.leerSesion()) {
        iniciar();
    } else {
        window.addEventListener("requesty-sesion-lista", iniciar, { once: true });
    }
})();
