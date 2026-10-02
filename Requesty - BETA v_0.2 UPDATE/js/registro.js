// ===================================================
// ELEMENTOS DEL FORMULARIO
// ===================================================

const formulario = document.getElementById("formAprendiz");
const mensaje = document.getElementById("formularioMensaje");

const campoNombre = document.getElementById("nombre");
const campoCorreo = document.getElementById("correo");
const campoTelefono = document.getElementById("telefono");
const campoContrasena = document.getElementById("contrasena");
const campoConfirmarContrasena = document.getElementById("confirmar_contrasena");
const campoRol = document.getElementById("rol");

function configurarRegistroInicial() {
    const sesion = leerSesion();
    const rolSesion = String(sesion?.rol || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
    if (rolSesion === "admin" || rolSesion === "administrador") {
        window.location.replace("reportes.html#cuentas");
        return;
    }

    const rolesRegistro = ["Cliente", "Administrador"];
    campoRol.replaceChildren(new Option("Selecciona un rol", "", true, true));
    rolesRegistro.forEach((rol) => campoRol.add(new Option(rol, rol)));
}

// ===================================================
// FUNCIONES PARA BORRAR CAMPOS
// ===================================================

// Borra un solo campo del formulario usando su id
function borrarCampo(idCampo) {
    const campo = document.getElementById(idCampo);
    campo.value = "";
}

// Borra los dos campos de contraseña al mismo tiempo
function borrarContrasenas() {
    campoContrasena.value = "";
    campoConfirmarContrasena.value = "";
}

// Borra todo el formulario y limpia el mensaje
function borrarFormulario() {
    formulario.reset();
    mostrarMensaje("", "");
}

// ===================================================
// FUNCIÓN PARA MOSTRAR MENSAJES AL APRENDIZ
// ===================================================

function mostrarMensaje(texto, tipo) {
    mensaje.textContent = texto;

    // Quita las clases anteriores antes de poner la nueva
    mensaje.classList.remove("exito", "error");

    if (tipo === "exito" || tipo === "error") {
        mensaje.classList.add(tipo);
    }
}

// ===================================================
// VALIDACIÓN DEL FORMULARIO
// ===================================================

function validarFormulario(evento) {
    // Evita que el formulario recargue la página
    evento.preventDefault();

    const nombre = campoNombre.value.trim();
    const correo = campoCorreo.value.trim();
    const telefono = campoTelefono.value.trim();
    const contrasena = campoContrasena.value.trim();
    const confirmarContrasena = campoConfirmarContrasena.value.trim();
    const rol = campoRol.value;

    // Revisa que ningún campo obligatorio esté vacío
    if (nombre === "" || correo === "" || telefono === "" || contrasena === "" || confirmarContrasena === "" || rol === "") {
        mostrarMensaje("Por favor completa todos los campos obligatorios.", "error");
        return;
    }

    // Validación sencilla del correo (debe tener @ y un punto después)
    if (correo.indexOf("@") === -1 || correo.indexOf(".") === -1) {
        mostrarMensaje("Ingresa un correo electrónico válido.", "error");
        return;
    }

    // Validación sencilla del teléfono (solo números, 10 dígitos)
    const soloNumeros = /^[0-9]+$/;
    if (!soloNumeros.test(telefono) || telefono.length !== 10) {
        mostrarMensaje("El teléfono debe tener 10 números.", "error");
        return;
    }

    // Validación de longitud mínima de la contraseña
    if (contrasena.length < 6) {
        mostrarMensaje("La contraseña debe tener al menos 6 caracteres.", "error");
        return;
    }

    // Validación de que las dos contraseñas coincidan
    if (contrasena !== confirmarContrasena) {
        mostrarMensaje("Las contraseñas no coinciden.", "error");
        return;
    }

    let usuarios = [];
    try {
        usuarios = JSON.parse(localStorage.getItem("requestyUsuarios") || "[]");
        if (!Array.isArray(usuarios)) usuarios = [];
    } catch {
        usuarios = [];
    }

    const administradorYaConfigurado = usuarios.some((usuario) => {
        const rolUsuario = String(usuario.rol || "").normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
        return rolUsuario === "admin" || rolUsuario === "administrador";
    });
    if (rol === "Administrador" && administradorYaConfigurado) {
        mostrarMensaje("Ya hay un administrador. Necesitas permiso para crear otra cuenta de administrador.", "error");
        return;
    }
    const rolesPermitidos = ["Cliente", "Administrador"];
    if (!rolesPermitidos.includes(rol)) {
        mostrarMensaje("Selecciona uno de los roles disponibles.", "error");
        return;
    }

    if (usuarios.some((usuario) => usuario.correo.toLowerCase() === correo.toLowerCase())) {
        mostrarMensaje("Ya existe una cuenta con ese correo.", "error");
        return;
    }

    usuarios.push({ nombre, correo, telefono, contrasena, rol, activo: true });
    localStorage.setItem("requestyUsuarios", JSON.stringify(usuarios));
    mostrarMensaje("Registro guardado correctamente.", "exito");
    // Después de 1.5 segundos lleva al inicio de sesión
    setTimeout(function () {
        window.location.href = "index.html";
    }, 1500);
}

// ===================================================
// EVENTOS DE LOS BOTONES
// ===================================================

// Botón grande: borra todo el formulario
document.getElementById("btnBorrarTodo").addEventListener("click", function () {
    borrarFormulario();
});

// Envío del formulario: valida los datos antes de "guardar"
formulario.addEventListener("submit", validarFormulario);
configurarRegistroInicial();
