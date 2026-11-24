// Registro público conectado a PostgreSQL (AuthController::registro)
(function () {
    const formulario = document.getElementById("formAprendiz");
    const mensaje = document.getElementById("formularioMensaje");
    const campoNombre = document.getElementById("nombre");
    const campoDocumento = document.getElementById("documento");
    const campoCorreo = document.getElementById("correo");
    const campoTelefono = document.getElementById("telefono");
    const campoContrasena = document.getElementById("contrasena");
    const campoConfirmarContrasena = document.getElementById("confirmar_contrasena");

    if (!formulario) return;

    function mostrarMensaje(texto, tipo) {
        if (!mensaje) return;
        mensaje.textContent = texto;
        mensaje.classList.remove("exito", "error");
        if (tipo === "exito" || tipo === "error") {
            mensaje.classList.add(tipo);
        }
    }

    function borrarFormulario() {
        formulario.reset();
        mostrarMensaje("", "");
    }

    async function enviarRegistro(evento) {
        evento.preventDefault();

        const nombre = campoNombre?.value.trim() || "";
        const documento = campoDocumento?.value.trim() || "";
        const correo = campoCorreo?.value.trim().toLowerCase() || "";
        const telefono = campoTelefono?.value.trim() || "";
        const contrasena = campoContrasena?.value || "";
        const confirmar = campoConfirmarContrasena?.value || "";

        if (!nombre || !documento || !correo || !contrasena || !confirmar) {
            mostrarMensaje("Por favor completa todos los campos obligatorios.", "error");
            return;
        }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo)) {
            mostrarMensaje("Ingresa un correo electrónico válido.", "error");
            return;
        }
        if (telefono && (!/^[0-9]{7,15}$/.test(telefono))) {
            mostrarMensaje("El teléfono debe contener solo dígitos (7 a 15).", "error");
            return;
        }
        if (contrasena.length < 6) {
            mostrarMensaje("La contraseña debe tener al menos 6 caracteres.", "error");
            return;
        }
        if (contrasena !== confirmar) {
            mostrarMensaje("Las contraseñas no coinciden.", "error");
            return;
        }

        const formData = new FormData(formulario);
        try {
            const resp = await fetch(formulario.getAttribute("action") || "index.php?page=registro", {
                method: "POST",
                body: formData,
                headers: {
                    "X-Requested-With": "XMLHttpRequest",
                    "Accept": "application/json"
                },
                credentials: "same-origin"
            });
            const data = await resp.json();
            if (!resp.ok || !data?.success) {
                mostrarMensaje(data?.message || "No fue posible registrar la cuenta.", "error");
                return;
            }
            mostrarMensaje(data.message || "Registro guardado correctamente.", "exito");
            setTimeout(() => {
                window.location.href = data.redirectUrl || "index.php";
            }, 900);
        } catch {
            mostrarMensaje("Error de conexión al registrar la cuenta.", "error");
        }
    }

    document.getElementById("btnBorrarTodo")?.addEventListener("click", borrarFormulario);
    formulario.addEventListener("submit", enviarRegistro);
})();
