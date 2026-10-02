const rolesDisponibles = ["Administrador", "Cajero", "Mesero", "Cocina", "Cliente"];
const interfaces = [
    ["menu", "Catálogo"],
    ["mesas", "Mesas"],
    ["pedidos", "Pedidos"],
    ["cocina", "Cocina"],
    ["caja", "Pagos/Caja"],
    ["pqrs", "PQRS"],
    ["reportes", "Reportes"]
];
const permisosBase = {
    administrador: interfaces.map(([clave]) => clave),
    cajero: ["menu", "mesas", "pedidos", "caja"],
    mesero: ["menu", "mesas", "pedidos"],
    cocina: ["menu", "pedidos", "cocina"],
    cliente: ["menu", "pqrs"]
};

const form = document.getElementById("formCuenta");
const modal = document.getElementById("modalCuenta");
const tbody = document.getElementById("listaCuentas");
const busquedaCuenta = document.getElementById("buscarCuenta");
const campoRol = document.getElementById("rolCuenta");
const campoId = document.getElementById("idCuenta");
const campoContrasena = document.getElementById("contrasenaCuenta");
const mensaje = document.getElementById("mensajeCuenta");

function leerUsuarios() {
    try {
        const usuarios = JSON.parse(localStorage.getItem("requestyUsuarios") || "[]");
        return Array.isArray(usuarios) ? usuarios : [];
    } catch {
        return [];
    }
}

function guardarUsuarios(usuarios) {
    localStorage.setItem("requestyUsuarios", JSON.stringify(usuarios));
}

function claveRolCuenta(rol) {
    return String(rol || "").trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
}

function permisosDeRol(rol) {
    return permisosBase[claveRolCuenta(rol)] || [];
}

function permisosSeleccionados() {
    return Array.from(document.querySelectorAll("#listaPermisos input:checked"), (control) => control.value);
}

function dibujarPermisos(permisos = permisosDeRol(campoRol.value)) {
    const contenedor = document.getElementById("listaPermisos");
    contenedor.replaceChildren();
    interfaces.forEach(([clave, etiqueta]) => {
        const label = document.createElement("label");
        label.className = "permiso-opcion";
        const checkbox = document.createElement("input");
        checkbox.type = "checkbox";
        checkbox.value = clave;
        checkbox.checked = permisos.includes(clave);
        const texto = document.createElement("span");
        texto.textContent = etiqueta;
        label.append(checkbox, texto);
        contenedor.append(label);
    });
}

function escapar(valor) {
    return String(valor ?? "").replace(/[&<>"']/g, (caracter) => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
    })[caracter]);
}

function renderizarCuentas() {
    const usuarios = leerUsuarios();
    const termino = busquedaCuenta.value.trim().toLowerCase();
    const visibles = usuarios.filter((usuario) =>
        `${usuario.nombre} ${usuario.correo}`.toLowerCase().includes(termino)
    );

    document.getElementById("totalCuentas").textContent = usuarios.length;
    document.getElementById("cuentasActivas").textContent = usuarios.filter((usuario) => usuario.activo !== false).length;
    document.getElementById("cuentasInactivas").textContent = usuarios.filter((usuario) => usuario.activo === false).length;
    document.getElementById("sinCuentas").hidden = visibles.length > 0;

    tbody.innerHTML = visibles.map((usuario) => {
        const correo = escapar(usuario.correo);
        const permisos = Array.isArray(usuario.permisos) ? usuario.permisos : permisosDeRol(usuario.rol);
        const estado = usuario.activo === false ? "Inactiva" : "Activa";
        return `<tr>
            <td><strong>${escapar(usuario.nombre)}</strong></td>
            <td>${correo}</td>
            <td>${escapar(usuario.rol)}</td>
            <td><span class="estado-cuenta ${usuario.activo === false ? "inactivo" : ""}">${estado}</span></td>
            <td>${permisos.length} de ${interfaces.length}</td>
            <td><div class="acciones-cuenta">
                <button class="btn-accion" type="button" data-accion="editar" data-correo="${correo}">Editar</button>
                <button class="btn-accion ${usuario.activo === false ? "" : "peligro"}" type="button" data-accion="estado" data-correo="${correo}">${usuario.activo === false ? "Activar" : "Inactivar"}</button>
                <button class="btn-accion peligro" type="button" data-accion="eliminar" data-correo="${correo}" aria-label="Eliminar cuenta ${correo}">Eliminar</button>
            </div></td>
        </tr>`;
    }).join("");
}

function abrirNuevaCuenta() {
    form.reset();
    campoId.value = "";
    document.getElementById("tituloModal").textContent = "Nueva cuenta";
    campoContrasena.required = true;
    document.getElementById("etiquetaContrasena").firstChild.textContent = "Contraseña";
    mensaje.textContent = "";
    dibujarPermisos();
    modal.showModal();
}

function abrirEdicion(correo) {
    const usuario = leerUsuarios().find((cuenta) => cuenta.correo.toLowerCase() === correo.toLowerCase());
    if (!usuario) return;

    form.reset();
    campoId.value = usuario.correo;
    document.getElementById("nombreCuenta").value = usuario.nombre;
    document.getElementById("correoCuenta").value = usuario.correo;
    document.getElementById("telefonoCuenta").value = usuario.telefono || "";
    campoRol.value = rolesDisponibles.find((rol) => claveRolCuenta(rol) === claveRolCuenta(usuario.rol)) || "Administrador";
    campoContrasena.required = false;
    document.getElementById("etiquetaContrasena").firstChild.textContent = "Nueva contraseña (opcional)";
    document.getElementById("tituloModal").textContent = "Editar cuenta";
    mensaje.textContent = "";
    dibujarPermisos(Array.isArray(usuario.permisos) ? usuario.permisos : permisosDeRol(usuario.rol));
    modal.showModal();
}

function cambiarEstado(correo) {
    const usuarios = leerUsuarios();
    const usuario = usuarios.find((cuenta) => cuenta.correo.toLowerCase() === correo.toLowerCase());
    if (!usuario) return;

    const sesion = leerSesion();
    const esLaPropiaCuenta = sesion?.correo?.toLowerCase() === correo.toLowerCase();
    if (esLaPropiaCuenta && usuario.activo !== false) {
        window.alert("No puedes inactivar la cuenta que estás usando.");
        return;
    }
    if (usuario.activo !== false && claveRolCuenta(usuario.rol) === "administrador") {
        const administradoresActivos = usuarios.filter((cuenta) => cuenta.activo !== false && claveRolCuenta(cuenta.rol) === "administrador");
        if (administradoresActivos.length <= 1) {
            window.alert("Debe quedar al menos una cuenta de administrador activa.");
            return;
        }
    }

    usuario.activo = usuario.activo === false;
    guardarUsuarios(usuarios);
    renderizarCuentas();
}

function eliminarCuenta(correo) {
    const usuarios = leerUsuarios();
    const usuario = usuarios.find((cuenta) => cuenta.correo.toLowerCase() === correo.toLowerCase());
    if (!usuario) return;

    const sesion = leerSesion();
    if (sesion?.correo?.toLowerCase() === correo.toLowerCase()) {
        window.alert("No puedes eliminar la cuenta que estás usando.");
        return;
    }
    const administradoresRestantes = usuarios.filter((cuenta) =>
        cuenta.correo.toLowerCase() !== correo.toLowerCase()
        && cuenta.activo !== false
        && ["admin", "administrador"].includes(claveRolCuenta(cuenta.rol))
    );
    if (["admin", "administrador"].includes(claveRolCuenta(usuario.rol)) && administradoresRestantes.length === 0) {
        window.alert("Debe quedar al menos una cuenta de administrador activa.");
        return;
    }
    if (!window.confirm(`¿Eliminar permanentemente la cuenta de ${usuario.nombre} (${usuario.correo})?`)) return;

    try {
        guardarUsuarios(usuarios.filter((cuenta) => cuenta.correo.toLowerCase() !== correo.toLowerCase()));
        renderizarCuentas();
    } catch (error) {
        window.alert(error.message || "No se pudo eliminar la cuenta.");
    }
}

function guardarCuenta(evento) {
    evento.preventDefault();
    mensaje.textContent = "";

    const usuarios = leerUsuarios();
    const correo = document.getElementById("correoCuenta").value.trim().toLowerCase();
    const correoOriginal = campoId.value;
    const nombre = document.getElementById("nombreCuenta").value.trim();
    const telefono = document.getElementById("telefonoCuenta").value.trim();
    const contrasena = campoContrasena.value;
    const rol = campoRol.value;
    const permisos = permisosSeleccionados();
    const sesion = leerSesion();
    const existente = correoOriginal
        ? usuarios.find((usuario) => usuario.correo.toLowerCase() === correoOriginal.toLowerCase())
        : null;

    if (usuarios.some((usuario) => usuario.correo.toLowerCase() === correo && usuario !== existente)) {
        mensaje.textContent = "Ya existe una cuenta con ese correo electrónico.";
        return;
    }
    if (!existente && contrasena.length < 6) {
        mensaje.textContent = "La contraseña debe tener al menos 6 caracteres.";
        return;
    }
    if (permisos.length === 0) {
        mensaje.textContent = "Selecciona al menos una interfaz para esta cuenta.";
        return;
    }
    const esPropiaCuenta = existente && existente.correo.toLowerCase() === sesion?.correo?.toLowerCase();
    if (esPropiaCuenta && correo !== existente.correo.toLowerCase()) {
        mensaje.textContent = "No puedes cambiar el correo de la cuenta que estás usando.";
        return;
    }
    if (existente && claveRolCuenta(existente.rol) === "administrador" && claveRolCuenta(rol) !== "administrador") {
        const otrosAdministradores = usuarios.filter((usuario) =>
            usuario !== existente && usuario.activo !== false && claveRolCuenta(usuario.rol) === "administrador"
        );
        if (otrosAdministradores.length === 0) {
            mensaje.textContent = "Debe quedar al menos una cuenta de administrador activa.";
            return;
        }
    }
    if (esPropiaCuenta && claveRolCuenta(rol) !== "administrador") {
        mensaje.textContent = "No puedes cambiar tu propia cuenta a otro rol.";
        return;
    }

    const cuenta = existente || { activo: true };
    cuenta.nombre = nombre;
    cuenta.correo = correo;
    cuenta.telefono = telefono;
    cuenta.rol = rol;
    cuenta.permisos = permisos;
    if (contrasena) cuenta.contrasena = contrasena;

    if (!existente) usuarios.push(cuenta);
    guardarUsuarios(usuarios);
    modal.close();
    renderizarCuentas();
}

document.getElementById("nuevaCuenta").addEventListener("click", abrirNuevaCuenta);
document.getElementById("cerrarModal").addEventListener("click", () => modal.close());
document.getElementById("cancelarCuenta").addEventListener("click", () => modal.close());
campoRol.addEventListener("change", () => dibujarPermisos());
form.addEventListener("submit", guardarCuenta);
busquedaCuenta.addEventListener("input", renderizarCuentas);
tbody.addEventListener("click", (evento) => {
    const boton = evento.target.closest("button[data-accion]");
    if (!boton) return;
    if (boton.dataset.accion === "editar") abrirEdicion(boton.dataset.correo);
    if (boton.dataset.accion === "estado") cambiarEstado(boton.dataset.correo);
    if (boton.dataset.accion === "eliminar") eliminarCuenta(boton.dataset.correo);
});

renderizarCuentas();