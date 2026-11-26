(function () {
    const claveInventario = "inventario-restaurante";
    const eventoInventario = "requesty-inventario-actualizado";
    const claveMigracionStock = "requesty-inventario-stock-base-v5";
    const claveMigracionRecetas = "requesty-recetas-inventario-v4";
    const productosIniciales = [
        { id: 1, nombre: "Caldo de costilla", precio: 8000, categoria: "Desayuno", imagen: "img/tarjeta/caldo-de-costilla.jpg", ingredientes: ["Costilla", "Papa", "Cilantro", "Agua Filtrada", "Sal"], receta: { costilla: 200, papa: 250, cilantro: 5, "agua filtrada": 0.5, sal: 3 } },
        { id: 2, nombre: "Jugo de mora", precio: 6000, categoria: "Bebida", imagen: "img/tarjeta/jugo-de-mora.jpg", ingredientes: ["Moras", "Azúcar"], receta: { moras: 80, azucar: 20 }, recetaAlternativas: [{ nombre: "Base líquida", opciones: [{ ingrediente: "Agua Filtrada", cantidad: 0.25 }, { ingrediente: "Leche", cantidad: 0.25 }] }] },
        { id: 3, nombre: "Huevos pericos", precio: 7000, categoria: "Desayuno", imagen: "img/tarjeta/huevos-pericos.jpg", ingredientes: ["Huevos", "Tomate", "Cebolla", "Sal"], receta: { huevos: 2, tomate: 60, cebolla: 40, sal: 3 } },
        { id: 4, nombre: "Bandeja paisa", precio: 30000, categoria: "Almuerzo", imagen: "img/tarjeta/bandeja-paisa.jpg", ingredientes: ["Arroz", "Plátano", "Tocineta", "Carne molida", "Arepa", "Aguacate", "Chorizo", "Huevos", "Fríjoles"], receta: { arroz: 150, platano: 100, tocineta: 50, "carne molida": 100, arepa: 1, aguacate: 80, chorizo: 1, huevos: 1, frijoles: 150 } }
    ];
    const unidadesIngredientes = new Map([
        ["costilla", "g"], ["papa", "g"], ["cilantro", "g"], ["agua filtrada", "L"], ["sal", "g"],
        ["moras", "g"], ["leche", "L"], ["azucar", "g"], ["huevos", "unidad"],
        ["tomate", "g"], ["cebolla", "g"], ["arroz", "g"], ["platano", "g"],
        ["tocineta", "g"], ["carne molida", "g"], ["arepa", "unidad"], ["aguacate", "g"],
        ["chorizo", "unidad"], ["frijoles", "g"]
    ]);
    const nombresIngredientes = new Map([
        ["costilla", "Costilla"], ["papa", "Papa"], ["cilantro", "Cilantro"], ["agua filtrada", "Agua Filtrada"],
        ["sal", "Sal"], ["moras", "Moras"], ["leche", "Leche"], ["azucar", "Azúcar"],
        ["huevos", "Huevos"], ["tomate", "Tomate"], ["cebolla", "Cebolla"], ["arroz", "Arroz"],
        ["platano", "Plátano"], ["tocineta", "Tocineta"], ["carne molida", "Carne molida"],
        ["arepa", "Arepa"], ["aguacate", "Aguacate"], ["chorizo", "Chorizo"], ["frijoles", "Fríjoles"]
    ]);
    const stockInicialPorInsumo = [
        ["Costilla", 9000, 3000, "g"], ["Papa", 11250, 3750, "g"], ["Cilantro", 225, 75, "g"],
        ["Agua Filtrada", 33.75, 11.25, "L"], ["Sal", 270, 90, "g"], ["Huevos", 135, 45, "unidad"],
        ["Tomate", 2700, 900, "g"], ["Cebolla", 1800, 600, "g"], ["Arroz", 6750, 2250, "g"],
        ["Plátano", 4500, 1500, "g"], ["Tocineta", 2250, 750, "g"], ["Carne molida", 4500, 1500, "g"],
        ["Arepa", 45, 15, "unidad"], ["Aguacate", 3600, 1200, "g"], ["Chorizo", 45, 15, "unidad"],
        ["Fríjoles", 6750, 2250, "g"], ["Moras", 3600, 1200, "g"], ["Azúcar", 900, 300, "g"], ["Leche", 11.25, 3.75, "L"]
    ];
    function normalizarNombre(nombre) {
        const clave = String(nombre || "").trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLocaleLowerCase("es");
        return ({ mora: "moras", agua: "agua filtrada", "leche o agua": "leche" })[clave] || clave;
    }

    function validarInsumo(insumo) {
        const nombre = String(insumo?.nombre || "").trim();
        const stockActual = Number(insumo?.stockActual);
        const stockMinimo = Number(insumo?.stockMinimo);
        const unidad = ["g", "L", "unidad"].includes(insumo?.unidad)
            ? insumo.unidad
            : unidadesIngredientes.get(normalizarNombre(nombre)) || "g";
        if (!nombre || !Number.isFinite(stockActual) || stockActual < 0
            || !Number.isFinite(stockMinimo) || stockMinimo < 0
            || unidad === "unidad" && (!Number.isInteger(stockActual) || !Number.isInteger(stockMinimo))) {
            throw new Error("El inventario guardado contiene un insumo inválido.");
        }
        return { nombre, stockActual, stockMinimo, unidad };
    }

    function leerInventario() {
        const guardado = localStorage.getItem(claveInventario);
        if (guardado === null) return [];
        const inventario = JSON.parse(guardado);
        if (!Array.isArray(inventario)) throw new Error("El inventario guardado no tiene un formato válido.");
        return inventario.map(validarInsumo);
    }

    function guardarInventario(inventario) {
        if (!Array.isArray(inventario)) throw new Error("No se pudo guardar el inventario: los datos no son válidos.");
        const validado = inventario.map(validarInsumo);
        const claves = validado.map(insumo => normalizarNombre(insumo.nombre));
        if (new Set(claves).size !== claves.length) throw new Error("Hay insumos repetidos en el inventario.");
        localStorage.setItem(claveInventario, JSON.stringify(validado));
        window.dispatchEvent(new CustomEvent(eventoInventario));
    }

    function obtenerIngredientesAgotados(producto, inventario = leerInventario(), alternativaSeleccionada = "") {
        const stockPorNombre = new Map(inventario.map(insumo => [normalizarNombre(insumo.nombre), insumo.stockActual]));
        const agotados = [];
        const receta = producto?.receta;
        if (receta && typeof receta === "object") {
            Object.entries(receta).forEach(([ingrediente, cantidad]) => {
                const clave = normalizarNombre(ingrediente);
                const requerido = Number(cantidad);
                if (!Number.isFinite(requerido) || requerido <= 0 || !stockPorNombre.has(clave) || stockPorNombre.get(clave) < requerido) {
                    agotados.push(ingrediente);
                }
            });
        } else {
            (Array.isArray(producto?.ingredientes) ? producto.ingredientes : []).forEach(ingrediente => {
                const clave = normalizarNombre(ingrediente);
                if (!stockPorNombre.has(clave) || stockPorNombre.get(clave) <= 0) agotados.push(ingrediente);
            });
        }
        for (const grupo of producto?.recetaAlternativas || []) {
            const opciones = grupo.opciones || [];
            const disponibles = opciones.filter(opcion => {
                const clave = normalizarNombre(opcion.ingrediente);
                return stockPorNombre.has(clave) && stockPorNombre.get(clave) >= Number(opcion.cantidad);
            });
            if (alternativaSeleccionada) {
                const elegida = opciones.find(opcion => normalizarNombre(opcion.ingrediente) === normalizarNombre(alternativaSeleccionada));
                if (!elegida || !disponibles.some(opcion => normalizarNombre(opcion.ingrediente) === normalizarNombre(elegida.ingrediente))) {
                    agotados.push(elegida?.ingrediente || alternativaSeleccionada);
                }
            } else if (!disponibles.length) {
                agotados.push(opciones.map(opcion => opcion.ingrediente).join(" o "));
            }
        }
        return [...new Set(agotados)];
    }

    function productoDisponible(producto, inventario = leerInventario(), alternativaSeleccionada = "") {
        return producto?.activo !== false && obtenerIngredientesAgotados(producto, inventario, alternativaSeleccionada).length === 0;
    }

    function validarDisponibilidadPedido(items, productos, inventario = leerInventario()) {
        const stock = new Map(inventario.map(insumo => [normalizarNombre(insumo.nombre), insumo.stockActual]));
        const nombres = new Map(inventario.map(insumo => [normalizarNombre(insumo.nombre), insumo.nombre]));
        const consumo = new Map();
        for (const item of items) {
            const producto = productos.find(actual => String(actual.id) === String(item.id));
            if (!producto) return [{ ingrediente: item.nombre || "Producto", requerido: 1, disponible: 0 }];
            const porciones = Math.max(1, Math.floor(Number(item.cantidad) || 1));
            const excluidos = new Set((item.ingredientesExcluidos || []).map(normalizarNombre));
            const receta = producto.receta;
            if (receta && typeof receta === "object") {
                for (const [ingrediente, cantidad] of Object.entries(receta)) {
                    const clave = normalizarNombre(ingrediente);
                    if (excluidos.has(clave)) continue;
                    consumo.set(clave, (consumo.get(clave) || 0) + Number(cantidad) * porciones);
                }
            }
            for (const grupo of producto.recetaAlternativas || []) {
                const opcion = (grupo.opciones || []).find(actual =>
                    normalizarNombre(actual.ingrediente) === normalizarNombre(item.alternativaReceta)
                );
                if (!opcion) return [{ ingrediente: grupo.nombre || "Opción de receta", requerido: 1, disponible: 0 }];
                const clave = normalizarNombre(opcion.ingrediente);
                consumo.set(clave, (consumo.get(clave) || 0) + Number(opcion.cantidad) * porciones);
            }
            for (const extra of item.extras || []) {
                const clave = normalizarNombre(extra.nombre);
                if (stock.has(clave)) consumo.set(clave, (consumo.get(clave) || 0) + Number(extra.cantidad || 0) * porciones);
            }
        }
        return [...consumo]
            .filter(([clave, cantidad]) => !stock.has(clave) || stock.get(clave) < cantidad)
            .map(([clave, requerido]) => ({
                ingrediente: nombres.get(clave) || clave,
                requerido,
                disponible: stock.get(clave) || 0
            }));
    }

    function obtenerProductosCatalogo() {
        const guardados = localStorage.getItem("productos-restaurante");
        if (guardados === null) return productosIniciales;
        const productos = JSON.parse(guardados);
        if (!Array.isArray(productos)) throw new Error("El catálogo guardado no tiene un formato válido.");
        return productos.map(producto => {
            const base = productosIniciales.find(inicial =>
                normalizarNombre(inicial.nombre) === normalizarNombre(producto.nombre)
                && Array.isArray(producto.ingredientes)
                && producto.ingredientes.length === inicial.ingredientes.length
                && inicial.ingredientes.every(ingrediente =>
                    producto.ingredientes.some(actual => normalizarNombre(actual) === normalizarNombre(ingrediente))
                )
            );
            return base && (!producto.receta || typeof producto.receta !== "object")
                ? { ...producto, receta: { ...base.receta } }
                : producto;
        });
    }

    function obtenerConsumos(productos, claveInsumo) {
        return productos.flatMap(producto => {
            const receta = producto?.receta;
            const consumos = [];
            if (receta && typeof receta === "object") {
                const entrada = Object.entries(receta).find(([ingrediente, cantidad]) =>
                    normalizarNombre(ingrediente) === claveInsumo
                    && Number.isFinite(Number(cantidad))
                    && Number(cantidad) > 0
                );
                if (entrada) {
                    const unidad = unidadesIngredientes.get(claveInsumo) || "g";
                    const cantidad = Number(entrada[1]);
                    consumos.push(`${producto.nombre}: ${cantidad} ${unidad === "unidad" && cantidad !== 1 ? "unidades" : unidad}`);
                }
            }
            (producto?.recetaAlternativas || []).forEach(grupo => {
                (grupo.opciones || []).filter(opcion => normalizarNombre(opcion.ingrediente) === claveInsumo).forEach(opcion => {
                    const unidad = unidadesIngredientes.get(claveInsumo) || "g";
                    consumos.push(`${producto.nombre} (en ${opcion.ingrediente === "Leche" ? "leche" : "agua"}): ${opcion.cantidad} ${unidad}`);
                });
            });
            return consumos;
        });
    }

    function formatearCantidad(insumo, cantidad) {
        const unidad = insumo.unidad === "unidad" && cantidad !== 1 ? "unidades" : insumo.unidad;
        return `${cantidad} ${unidad}`;
    }

    function renderizarInventario() {
        const tabla = document.getElementById("tabla-inventario");
        if (!tabla) return;

        const mensaje = document.getElementById("mensaje-inventario");
        try {
            const inventario = leerInventario();
            const productos = obtenerProductosCatalogo();
            const inventarioPorClave = new Map(inventario.map(insumo => [normalizarNombre(insumo.nombre), insumo]));
            const nombresInsumosReceta = new Map();
            productos.forEach(producto => {
                const ingredientes = Array.isArray(producto?.ingredientes) ? producto.ingredientes : [];
                ingredientes.forEach(nombre => {
                    const clave = normalizarNombre(nombre);
                    if (clave && !nombresInsumosReceta.has(clave)) {
                        nombresInsumosReceta.set(clave, nombresIngredientes.get(clave) || nombre);
                    }
                });
                if (producto?.receta && typeof producto.receta === "object") Object.keys(producto.receta).forEach(nombre => {
                    const clave = normalizarNombre(nombre);
                    if (clave && !nombresInsumosReceta.has(clave)) nombresInsumosReceta.set(clave, nombresIngredientes.get(clave) || nombre);
                });
                (producto?.recetaAlternativas || []).forEach(grupo => (grupo.opciones || []).forEach(opcion => {
                    const clave = normalizarNombre(opcion.ingrediente);
                    if (clave && !nombresInsumosReceta.has(clave)) nombresInsumosReceta.set(clave, nombresIngredientes.get(clave) || opcion.ingrediente);
                }));
            });
            const nombresVisibles = new Map([
                ...inventario.map(insumo => [normalizarNombre(insumo.nombre), insumo.nombre]),
                ...nombresInsumosReceta
            ]);
            if (!nombresVisibles.size) {
                tabla.innerHTML = '<tr><td colspan="7">Todavía no hay insumos ni recetas registradas.</td></tr>';
            } else {
                tabla.innerHTML = [...nombresVisibles].map(([clave, nombre]) => {
                    const insumo = inventarioPorClave.get(clave);
                    const afectados = productos.filter(producto => {
                        const ingredientes = Array.isArray(producto.ingredientes) ? producto.ingredientes : [];
                        const recetaIncluye = Object.keys(producto.receta || {}).some(ingrediente => normalizarNombre(ingrediente) === clave);
                        const alternativaIncluye = (producto.recetaAlternativas || []).some(grupo =>
                            (grupo.opciones || []).some(opcion => normalizarNombre(opcion.ingrediente) === clave)
                        );
                        return recetaIncluye || alternativaIncluye || ingredientes.some(ingrediente => normalizarNombre(ingrediente) === clave);
                    }).map(producto => producto.nombre);
                    const consumos = obtenerConsumos(productos, clave);
                    const agotado = insumo && insumo.stockActual <= 0;
                    const bajo = insumo && !agotado && insumo.stockActual < insumo.stockMinimo;
                    const requeridoPorPorcion = productos.flatMap(producto => [
                        ...Object.entries(producto.receta || {}).filter(([ingrediente]) => normalizarNombre(ingrediente) === clave).map(([, cantidad]) => Number(cantidad)),
                        ...(producto.recetaAlternativas || []).flatMap(grupo =>
                            (grupo.opciones || []).filter(opcion => normalizarNombre(opcion.ingrediente) === clave).map(opcion => Number(opcion.cantidad))
                        )
                    ]).filter(cantidad => Number.isFinite(cantidad) && cantidad > 0);
                    const insuficientePorPorcion = insumo && requeridoPorPorcion.length
                        && insumo.stockActual < Math.min(...requeridoPorPorcion);
                    const estado = !insumo ? "Sin registrar"
                        : agotado ? "Sin stock"
                            : bajo ? "Crítico · abastecer"
                                : insuficientePorPorcion ? "Stock insuficiente"
                                    : "Disponible";
                    const accion = insumo
                        ? `<button type="button" class="boton-secundario" data-editar-insumo="${escaparInventario(insumo.nombre)}">Actualizar</button> <button type="button" class="boton-eliminar-insumo" data-eliminar-insumo="${escaparInventario(insumo.nombre)}">Borrar</button>`
                        : `<button type="button" class="boton-secundario" data-registrar-insumo="${escaparInventario(nombre)}">Registrar</button>`;
                    return `<tr>
                        <td>${escaparInventario(nombre)}</td>
                        <td>${insumo ? formatearCantidad(insumo, insumo.stockActual) : "—"}</td>
                        <td>${insumo ? formatearCantidad(insumo, insumo.stockMinimo) : "—"}</td>
                        <td><span class="estado-inventario ${!insumo || agotado || bajo || insuficientePorPorcion ? "alerta" : ""}">${estado}</span></td>
                        <td>${consumos.length ? consumos.map(escaparInventario).join("<br>") : "—"}</td>
                        <td>${afectados.length ? afectados.map(escaparInventario).join(", ") : "Sin platos asociados"}</td>
                        <td>${accion}</td>
                    </tr>`;
                }).join("");
            }
            mensaje.textContent = "";
        } catch (error) {
            mensaje.textContent = error.message || "No se pudo consultar el inventario.";
        }
    }

    function escaparInventario(valor) {
        return String(valor ?? "").replace(/[&<>"']/g, caracter => ({
            "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
        })[caracter]);
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
        let claveEditando = "";
        campoUnidad.addEventListener("change", () => {
            const paso = campoUnidad.value === "unidad" ? "1" : "0.001";
            campoStock.step = paso;
            campoMinimo.step = paso;
        });

        function cancelarEdicion() {
            claveEditando = "";
            formulario.reset();
            campoUnidad.disabled = false;
            campoStock.step = campoUnidad.value === "unidad" ? "1" : "0.001";
            campoMinimo.step = campoStock.step;
            botonGuardar.textContent = "Registrar insumo";
            botonCancelar.hidden = true;
            mensaje.textContent = "";
        }

        formulario.addEventListener("submit", evento => {
            evento.preventDefault();
            const nombre = campoNombre.value.trim();
            const stockActual = Number(campoStock.value);
            const stockMinimo = Number(campoMinimo.value);
            const unidad = campoUnidad.value;
            if (!nombre || !Number.isFinite(stockActual) || stockActual < 0
                || !Number.isFinite(stockMinimo) || stockMinimo < 0
                || unidad === "unidad" && (!Number.isInteger(stockActual) || !Number.isInteger(stockMinimo))
                || !["g", "L", "unidad"].includes(unidad)) {
                mensaje.textContent = "Ingresa un nombre, cantidades no negativas y selecciona g, L o unidad. Las unidades deben ser enteras.";
                return;
            }

            try {
                const inventario = leerInventario();
                const clave = claveEditando || normalizarNombre(nombre);
                const existente = inventario.find(insumo => normalizarNombre(insumo.nombre) === clave);
                const nombreEnUso = inventario.some(insumo =>
                    normalizarNombre(insumo.nombre) === normalizarNombre(nombre)
                    && normalizarNombre(insumo.nombre) !== clave
                );
                if (nombreEnUso) {
                    mensaje.textContent = "Ya existe otro insumo con ese nombre.";
                    return;
                }

                const actualizado = existente
                    ? inventario.map(insumo => normalizarNombre(insumo.nombre) === clave
                        ? { nombre, stockActual, stockMinimo, unidad: insumo.unidad }
                        : insumo)
                    : [...inventario, { nombre, stockActual, stockMinimo, unidad }];
                guardarInventario(actualizado);
                cancelarEdicion();
                renderizarInventario();
            } catch (error) {
                mensaje.textContent = error.message || "No se pudo guardar el insumo.";
            }
        });

        tabla.addEventListener("click", evento => {
            const borrar = evento.target.closest("button[data-eliminar-insumo]");
            if (borrar) {
                const sesion = typeof leerSesion === "function" ? leerSesion() : null;
                const rol = normalizarNombre(sesion?.rol);
                if (!["admin", "administrador"].includes(rol)) {
                    mensaje.textContent = "Solo un administrador puede borrar insumos.";
                    return;
                }
                const nombre = borrar.dataset.eliminarInsumo;
                const productosAfectados = obtenerProductosCatalogo().filter(producto =>
                    (producto.ingredientes || []).some(ingrediente => normalizarNombre(ingrediente) === normalizarNombre(nombre))
                    || Object.keys(producto.receta || {}).some(ingrediente => normalizarNombre(ingrediente) === normalizarNombre(nombre))
                    || (producto.recetaAlternativas || []).some(grupo =>
                        (grupo.opciones || []).some(opcion => normalizarNombre(opcion.ingrediente) === normalizarNombre(nombre))
                    )
                ).map(producto => producto.nombre);
                const consecuencia = productosAfectados.length
                    ? `\n\nSe recalculará la disponibilidad de estos platos: ${productosAfectados.join(", ")}. Las recetas que necesiten este insumo no podrán solicitarse hasta registrarlo de nuevo.`
                    : "";
                if (!window.confirm(`¿Borrar el insumo "${nombre}" del inventario?${consecuencia}`)) return;
                try {
                    const inventario = leerInventario();
                    const actualizado = inventario.filter(insumo => normalizarNombre(insumo.nombre) !== normalizarNombre(nombre));
                    if (actualizado.length === inventario.length) throw new Error("El insumo seleccionado ya no existe en el inventario.");
                    guardarInventario(actualizado);
                    if (claveEditando === normalizarNombre(nombre)) cancelarEdicion();
                    renderizarInventario();
                    mensaje.textContent = `Se borró "${nombre}" del inventario.${productosAfectados.length ? " Se recalculó la disponibilidad de los platos relacionados." : ""}`;
                } catch (error) {
                    mensaje.textContent = error.message || "No se pudo borrar el insumo.";
                }
                return;
            }
            const registrar = evento.target.closest("button[data-registrar-insumo]");
            if (registrar) {
                cancelarEdicion();
                campoNombre.value = registrar.dataset.registrarInsumo;
                const stockInicial = stockInicialPorInsumo.find(([nombre]) =>
                    normalizarNombre(nombre) === normalizarNombre(registrar.dataset.registrarInsumo)
                );
                campoStock.value = stockInicial?.[1] ?? 45;
                campoMinimo.value = stockInicial?.[2] ?? 15;
                campoUnidad.value = unidadesIngredientes.get(normalizarNombre(registrar.dataset.registrarInsumo)) || "g";
                campoUnidad.disabled = false;
                campoStock.step = campoUnidad.value === "unidad" ? "1" : "0.001";
                campoMinimo.step = campoStock.step;
                mensaje.textContent = "Ingresa las existencias reales en la unidad indicada en “Consumo por porción”.";
                campoStock.focus();
                return;
            }
            const boton = evento.target.closest("button[data-editar-insumo]");
            if (!boton) return;
            try {
                const insumo = leerInventario().find(item => normalizarNombre(item.nombre) === normalizarNombre(boton.dataset.editarInsumo));
                if (!insumo) throw new Error("No se encontró el insumo seleccionado.");
                claveEditando = normalizarNombre(insumo.nombre);
                campoNombre.value = insumo.nombre;
                campoStock.value = insumo.stockActual;
                campoMinimo.value = insumo.stockMinimo;
                campoUnidad.value = insumo.unidad;
                campoUnidad.disabled = true;
                campoStock.step = campoUnidad.value === "unidad" ? "1" : "0.001";
                campoMinimo.step = campoStock.step;
                botonGuardar.textContent = "Guardar cambios";
                botonCancelar.hidden = false;
                mensaje.textContent = "";
                campoStock.focus();
            } catch (error) {
                mensaje.textContent = error.message || "No se pudo cargar el insumo.";
            }
        });

        botonCancelar.addEventListener("click", cancelarEdicion);
        renderizarInventario();
    }

    window.RequestyInventario = Object.freeze({
        claveInventario,
        eventoInventario,
        normalizarNombre,
        leerInventario,
        guardarInventario,
        obtenerIngredientesAgotados,
        productoDisponible,
        validarDisponibilidadPedido,
        obtenerProductosCatalogo,
        renderizarInventario
    });

    function migrarRecetasBase() {
        if (localStorage.getItem(claveMigracionRecetas) === "true") return;
        const guardados = localStorage.getItem("productos-restaurante");
        if (guardados === null) {
            localStorage.setItem("productos-restaurante", JSON.stringify(productosIniciales.map(producto => ({ ...producto }))));
        } else {
            const productos = JSON.parse(guardados);
            if (!Array.isArray(productos)) throw new Error("El catálogo guardado no tiene un formato válido.");
            const actualizados = productos.map(producto => {
                const base = productosIniciales.find(inicial => normalizarNombre(inicial.nombre) === normalizarNombre(producto.nombre));
                return base ? { ...producto, ingredientes: [...base.ingredientes], receta: { ...base.receta },
                    ...(base.recetaAlternativas ? { recetaAlternativas: base.recetaAlternativas } : { recetaAlternativas: undefined }) } : producto;
            });
            localStorage.setItem("productos-restaurante", JSON.stringify(actualizados));
        }
        localStorage.setItem(claveMigracionRecetas, "true");
    }

    function inicializarStockBase() {
        if (localStorage.getItem(claveMigracionStock) === "true") return;
        try {
            const existentes = leerInventario();
            const inventarioActualizado = [...existentes];
            stockInicialPorInsumo.forEach(([nombre, stockActual, stockMinimo, unidad]) => {
                const clave = normalizarNombre(nombre);
                const indice = inventarioActualizado.findIndex(insumo => normalizarNombre(insumo.nombre) === clave);
                const valor = { nombre, stockActual, stockMinimo, unidad };
                if (indice === -1) inventarioActualizado.push(valor);
                else inventarioActualizado[indice] = valor;
            });
            guardarInventario(inventarioActualizado);
            localStorage.setItem(claveMigracionStock, "true");
        } catch (error) {
            const mensaje = document.getElementById("mensaje-inventario");
            if (mensaje) mensaje.textContent = error.message || "No se pudo inicializar el stock de apertura.";
        }
    }

    window.addEventListener(eventoInventario, renderizarInventario);
    window.addEventListener("storage", evento => {
        if (evento.key === claveInventario || evento.key === "productos-restaurante") renderizarInventario();
    });
    inicializarGestionInventario();
    try {
        migrarRecetasBase();
        inicializarStockBase();
    } catch (error) {
        const mensaje = document.getElementById("mensaje-inventario");
        if (mensaje) mensaje.textContent = error.message || "No se pudo actualizar el inventario y las recetas.";
    }
})();
