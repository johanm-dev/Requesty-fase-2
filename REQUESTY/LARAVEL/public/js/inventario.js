// Módulo de Inventario y Kardex conectado 100% a PostgreSQL (api/inventario)
(function () {
    const eventoInventario = "requesty-inventario-actualizado";
    let ingredientesCache = [];
    let movimientosCache = [];

    function normalizarNombre(nombre) {
        return String(nombre || "")
            .trim()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .toLocaleLowerCase("es");
    }

    function obtenerIngredientesAgotados(producto) {
        if (Array.isArray(producto?.ingredientes_agotados)) {
            return producto.ingredientes_agotados;
        }
        const receta = Array.isArray(producto?.receta) ? producto.receta : [];
        return receta
            .filter((r) => Number(r.stock_actual || 0) < Number(r.cantidad || 0) || r.estado !== "ACTIVO")
            .map((r) => r.nombre);
    }

    function productoDisponible(producto) {
        if (typeof producto?.disponible_stock === "boolean") {
            return producto.disponible_stock;
        }
        return producto?.disponible !== false && obtenerIngredientesAgotados(producto).length === 0;
    }

    async function cargar(forzar = true) {
        if (!window.RequestyAPI?.leerSesion() && forzar) {
            return { ingredientes: ingredientesCache, movimientos: movimientosCache };
        }
        const res = await window.RequestyAPI.get("api/inventario");
        ingredientesCache = Array.isArray(res?.ingredientes) ? res.ingredientes : [];
        movimientosCache = Array.isArray(res?.movimientos) ? res.movimientos : [];
        window.dispatchEvent(new CustomEvent(eventoInventario, { detail: ingredientesCache }));
        return { ingredientes: ingredientesCache, movimientos: movimientosCache };
    }

    async function renderizarInventario() {
        const tabla = document.getElementById("tabla-inventario");
        if (!tabla) return;
        const tablaKardex = document.getElementById("tabla-kardex");
        const mensaje = document.getElementById("mensaje-inventario");
        const esc = window.RequestyAPI.escaparHTML;

        try {
            const { ingredientes, movimientos } = await cargar(true);
            if (!ingredientes.length) {
                tabla.innerHTML = '<tr><td colspan="7">No hay insumos registrados en la base de datos.</td></tr>';
            } else {
                tabla.innerHTML = ingredientes.map((ins) => {
                    const stock = Number(ins.stock_actual || 0);
                    const min = Number(ins.stock_minimo || 0);
                    const agotado = stock <= 0 || ins.estado === "AGOTADO";
                    const bajo = !agotado && stock <= min;
                    const estadoTxt = agotado ? "Sin stock" : bajo ? "Crítico · abastecer" : "Disponible";
                    const platos = Array.isArray(ins.platos_relacionados) && ins.platos_relacionados.length
                        ? ins.platos_relacionados.map(esc).join(", ")
                        : "Sin platos asociados";
                    return `<tr>
                        <td>${esc(ins.nombre)}</td>
                        <td>${stock} ${esc(ins.unidad_medida)}</td>
                        <td>${min} ${esc(ins.unidad_medida)}</td>
                        <td><span class="estado-inventario ${agotado || bajo ? "alerta" : ""}">${estadoTxt}</span></td>
                        <td>Según receta BD</td>
                        <td>${platos}</td>
                        <td><button type="button" class="boton-secundario" data-editar-insumo="${Number(ins.id_ingrediente)}">Actualizar / Ajustar</button></td>
                    </tr>`;
                }).join("");
            }

            if (tablaKardex) {
                tablaKardex.innerHTML = movimientos.length ? movimientos.map((m) => `
                    <tr>
                        <td>${esc(new Date(m.fecha).toLocaleString("es-CO"))}</td>
                        <td>${esc(m.ingrediente)}</td>
                        <td>${esc(m.tipo_movimiento)}</td>
                        <td>${Number(m.cantidad)} ${esc(m.unidad_medida)}</td>
                        <td>${esc(m.motivo)}</td>
                        <td>${esc(m.usuario_nombre || "Sistema")}</td>
                    </tr>
                `).join("") : '<tr><td colspan="6">Sin movimientos registrados.</td></tr>';
            }
        } catch (error) {
            if (mensaje) mensaje.textContent = error.message || "No se pudo consultar el inventario.";
        }
    }

    function inicializarGestionInventario() {
        const formulario = document.getElementById("formulario-inventario");
        const tabla = document.getElementById("tabla-inventario");
        if (!formulario || !tabla) return;

        const campoNombre = document.getElementById("nombre-insumo");
        const campoStock = document.getElementById("stock-insumo");
        const campoMinimo = document.getElementById("minimo-insumo");
        const campoUnidad = document.getElementById("unidad-insumo");
        const botonGuardar = document.getElementById("guardar-insumo");
        const botonCancelar = document.getElementById("cancelar-edicion-insumo");
        const mensaje = document.getElementById("mensaje-inventario");
        let idEditando = null;

        campoUnidad?.addEventListener("change", () => {
            const paso = campoUnidad.value === "unidad" ? "1" : "0.001";
            if (campoStock) campoStock.step = paso;
            if (campoMinimo) campoMinimo.step = paso;
        });

        function cancelarEdicion() {
            idEditando = null;
            formulario.reset();
            if (campoUnidad) campoUnidad.disabled = false;
            if (botonGuardar) botonGuardar.textContent = "Registrar insumo";
            if (botonCancelar) botonCancelar.hidden = true;
        }

        formulario.addEventListener("submit", async (evento) => {
            evento.preventDefault();
            if (mensaje) mensaje.textContent = "";

            const nombre = campoNombre?.value.trim() || "";
            const stockVal = Number(campoStock?.value ?? 0);
            const minimoVal = Number(campoMinimo?.value ?? 0);
            const unidad = campoUnidad?.value || "g";

            if (!nombre || !Number.isFinite(stockVal) || stockVal < 0 || !Number.isFinite(minimoVal) || minimoVal < 0) {
                if (mensaje) mensaje.textContent = "Ingresa nombre y cantidades válidas no negativas.";
                return;
            }

            try {
                if (idEditando !== null) {
                    await window.RequestyAPI.put("api/inventario", {
                        id_ingrediente: idEditando,
                        nombre,
                        stock_objetivo: stockVal,
                        stock_minimo: minimoVal,
                        motivo: "Ajuste manual desde panel de inventario"
                    });
                    if (mensaje) mensaje.textContent = `Insumo "${nombre}" actualizado en Kardex PostgreSQL.`;
                } else {
                    await window.RequestyAPI.post("api/inventario", {
                        nombre,
                        unidad_medida: unidad,
                        stock_inicial: stockVal,
                        stock_minimo: minimoVal,
                        motivo: "Entrada inicial desde panel de inventario"
                    });
                    if (mensaje) mensaje.textContent = `Insumo "${nombre}" registrado en PostgreSQL.`;
                }
                cancelarEdicion();
                await renderizarInventario();
            } catch (err) {
                if (mensaje) mensaje.textContent = err.message || "No se pudo guardar el insumo.";
            }
        });

        tabla.addEventListener("click", (evento) => {
            const boton = evento.target.closest("button[data-editar-insumo]");
            if (!boton) return;
            const id = Number(boton.dataset.editarInsumo);
            const insumo = ingredientesCache.find((i) => Number(i.id_ingrediente) === id);
            if (!insumo) return;

            idEditando = id;
            if (campoNombre) campoNombre.value = insumo.nombre;
            if (campoStock) campoStock.value = Number(insumo.stock_actual);
            if (campoMinimo) campoMinimo.value = Number(insumo.stock_minimo);
            if (campoUnidad) {
                campoUnidad.value = insumo.unidad_medida;
                campoUnidad.disabled = true;
            }
            if (botonGuardar) botonGuardar.textContent = "Guardar ajuste";
            if (botonCancelar) botonCancelar.hidden = false;
            campoStock?.focus();
        });

        botonCancelar?.addEventListener("click", cancelarEdicion);
        if (window.RequestyAPI.leerSesion()) {
            renderizarInventario();
        } else {
            window.addEventListener("requesty-sesion-lista", renderizarInventario, { once: true });
        }
    }

    window.RequestyInventario = Object.freeze({
        eventoInventario,
        normalizarNombre,
        leerInventario: () => ingredientesCache,
        cargar,
        obtenerIngredientesAgotados,
        productoDisponible,
        renderizarInventario
    });

    inicializarGestionInventario();
})();
