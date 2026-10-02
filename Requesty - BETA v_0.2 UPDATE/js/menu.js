const categorias = ["Desayuno", "Almuerzo", "Bebida"];
const categoriasAnteriores = {
    Desayunos: "Desayuno",
    Almuerzos: "Almuerzo",
    Bebidas: "Bebida"
};

const productosIniciales = [
    { id: 1, nombre: "Caldo de costilla", precio: 8000, categoria: "Desayuno", ingredientes: ["costilla", "Papa", "Cilantro", "Agua", "Sal"] },
    { id: 2, nombre: "Jugo de mora", precio: 6000, categoria: "Bebida", ingredientes: ["moras", "leche o agua", "azúcar"] },
    { id: 3, nombre: "Huevos pericos", precio: 7000, categoria: "Desayuno", ingredientes: ["Huevos", "Tomate", "Cebolla", "Sal"] },
    { id: 4, nombre: "Bandeja paisa", precio: 30000, categoria: "Almuerzo", ingredientes: ["Arroz", "Platano", "Tocineta", "Carne molida", "Arepa", "Aguacate", "Chorizo", "Huevos", "Frijoles"] }
];

let productos = cargarProductos();
let carrito = [];
let categoriaActual = "Todas";
let busqueda = "";
let productoEditando = null;

function cargarProductos() {
    const guardados = localStorage.getItem("productos-restaurante");
    if (!guardados) return productosIniciales;

    try {
        const lista = JSON.parse(guardados);
        if (!Array.isArray(lista)) return productosIniciales;

        const productosValidos = lista.map(producto => ({
            ...producto,
            categoria: categoriasAnteriores[producto.categoria] || producto.categoria
        })).filter(producto => categorias.includes(producto.categoria));

        return productosValidos.length ? productosValidos : productosIniciales;
    } catch (error) {
        return productosIniciales;
    }
}

function guardarProductos() {
    localStorage.setItem("productos-restaurante", JSON.stringify(productos));
}

function formatearPrecio(precio) {
    return new Intl.NumberFormat("es-CO").format(precio);
}

function mostrarCatalogo() {
    const catalogo = document.getElementById("catalogo");
    const productosVisibles = productos.filter(producto => {
        const coincideCategoria = categoriaActual === "Todas" || producto.categoria === categoriaActual;
        const textoProducto = `${producto.nombre} ${producto.ingredientes.join(" ")}`.toLowerCase();
        return coincideCategoria && textoProducto.includes(busqueda);
    });

    document.getElementById("contador-productos").textContent = `${productosVisibles.length} producto${productosVisibles.length === 1 ? "" : "s"}`;
    catalogo.innerHTML = productosVisibles.length ? productosVisibles.map(producto => `
        <article class="producto">
            <span class="categoria">${producto.categoria}</span>
            <h3>${producto.nombre}</h3>
            <p class="ingredientes"><strong>Ingredientes:</strong> ${producto.ingredientes.join(", ")}</p>
            <p class="precio">$${formatearPrecio(producto.precio)}</p>
            <div class="acciones-producto"><button data-accion="agregar" data-id="${producto.id}">Agregar al carrito</button><button class="editar" data-accion="editar" data-id="${producto.id}">Editar</button></div>
        </article>
    `).join("") : "<p>No hay productos en esta categoría.</p>";
}

function mostrarFiltros() {
    const filtros = document.getElementById("filtros");
    const opciones = ["Todas", ...categorias];
    filtros.innerHTML = opciones.map(categoria => `<button class="filtro ${categoria === categoriaActual ? "activo" : ""}" data-categoria="${categoria}">${categoria}</button>`).join("");
}

function obtenerDatosFormulario() {
    return {
        nombre: document.getElementById("nombre").value.trim(),
        precio: Number(document.getElementById("precio").value),
        categoria: document.getElementById("categoria").value,
        ingredientes: document.getElementById("ingredientes").value
            .split(",")
            .map(ingrediente => ingrediente.trim())
            .filter(Boolean)
    };
}

function datosValidos(datos) {
    return datos.nombre && datos.categoria && categorias.includes(datos.categoria)
        && datos.ingredientes.length && datos.precio >= 0;
}

document.getElementById("formulario-producto").addEventListener("submit", evento => {
    evento.preventDefault();
    const datos = obtenerDatosFormulario();
    if (!datosValidos(datos)) return;

    if (productoEditando) {
        productos = productos.map(producto => producto.id === productoEditando
            ? { ...producto, ...datos }
            : producto);
    } else {
        productos.push({ id: Date.now(), ...datos });
    }

    guardarProductos();
    limpiarFormulario();
    mostrarFiltros();
    mostrarCatalogo();
});

function editarProducto(id) {
    const producto = productos.find(item => item.id === id);
    productoEditando = id;
    document.getElementById("nombre").value = producto.nombre;
    document.getElementById("precio").value = producto.precio;
    document.getElementById("categoria").value = producto.categoria;
    document.getElementById("ingredientes").value = producto.ingredientes.join(", ");
    document.getElementById("guardar-producto").textContent = "Guardar cambios";
    document.getElementById("cancelar-edicion").classList.remove("oculto");
    document.getElementById("nombre").focus();
}

function limpiarFormulario() {
    productoEditando = null;
    document.getElementById("formulario-producto").reset();
    document.getElementById("guardar-producto").textContent = "Agregar producto";
    document.getElementById("cancelar-edicion").classList.add("oculto");
}

function cargarCategorias() {
    document.getElementById("categoria").innerHTML = categorias
        .map(categoria => `<option value="${categoria}">${categoria}</option>`)
        .join("");
}

function agregarAlCarrito(id) {
    const producto = productos.find(item => item.id === id);
    if (!producto) return;

    carrito.push({ nombre: producto.nombre, precio: producto.precio });
    mostrarCarrito();
}

function mostrarCarrito() {
    const listaCarrito = document.getElementById("carrito");
    listaCarrito.innerHTML = "";
    carrito.forEach((producto, indice) => {
        const elemento = document.createElement("li");
        elemento.textContent = `${producto.nombre} - $${formatearPrecio(producto.precio)}`;
        const botonEliminar = document.createElement("button");
        botonEliminar.textContent = "Eliminar";
        botonEliminar.addEventListener("click", () => eliminarProducto(indice));
        elemento.appendChild(botonEliminar);
        listaCarrito.appendChild(elemento);
    });

    const total = carrito.reduce((suma, producto) => suma + producto.precio, 0);
    document.getElementById("total").textContent = formatearPrecio(total);
}

function eliminarProducto(indice) {
    carrito.splice(indice, 1);
    mostrarCarrito();
}

function vaciarCarrito() {
    carrito = [];
    mostrarCarrito();
}

document.getElementById("filtros").addEventListener("click", evento => {
    if (!evento.target.dataset.categoria) return;
    categoriaActual = evento.target.dataset.categoria;
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
    if (boton.dataset.accion === "agregar") agregarAlCarrito(id);
    if (boton.dataset.accion === "editar") editarProducto(id);
});

document.getElementById("cancelar-edicion").addEventListener("click", limpiarFormulario);
document.getElementById("vaciar-carrito").addEventListener("click", vaciarCarrito);

cargarCategorias();
mostrarFiltros();
mostrarCatalogo();
mostrarCarrito();