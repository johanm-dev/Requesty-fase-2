const permisosPorRol = {
    administrador: ["menu", "mesas", "pedidos", "cocina", "caja", "pqrs", "reportes"],
    admin: ["menu", "mesas", "pedidos", "cocina", "caja", "pqrs", "reportes"],
    cajero: ["menu", "mesas", "pedidos", "caja"],
    mesero: ["menu", "mesas", "pedidos"],
    cocina: ["menu", "pedidos", "cocina"],
    cliente: ["menu", "pqrs"]
};

const paginaPorEnlace = {
    "index.php?page=menu": "menu",
    "index.php?page=mesas": "mesas",
    "index.php?page=pedidos": "pedidos",
    "index.php?page=cocina": "cocina",
    "index.php?page=caja": "caja",
    "index.php?page=pqrs": "pqrs",
    "index.php?page=reportes": "reportes"
};

const loginUrl = "index.php";

const paginaInicialPorRol = {
    administrador: "index.php?page=mesas",
    admin: "index.php?page=mesas",
    cajero: "index.php?page=caja",
    mesero: "index.php?page=mesas",
    cocina: "index.php?page=cocina",
    cliente: "index.php?page=menu"
};

const archivoPorPermiso = {
    menu: "index.php?page=menu",
    mesas: "index.php?page=mesas",
    pedidos: "index.php?page=pedidos",
    cocina: "index.php?page=cocina",
    caja: "index.php?page=caja",
    pqrs: "index.php?page=pqrs",
    reportes: "index.php?page=reportes"
};
    pqrs: esEntornoPhp ? "pqrs.php" : "Pqrs.html",
    reportes: esEntornoPhp ? "reportes.php" : "reportes.html"
};

function claveRol(rol) {
    return String(rol || "").trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
}

function leerSesion() {
    const datos = sessionStorage.getItem("requestySesion") || localStorage.getItem("requestySesion");
    if (!datos) return null;

    try {
        return JSON.parse(datos);
    } catch {
        return null;
    }
}

function leerUsuariosAuth() {
    try {
        const guardados = localStorage.getItem("requestyUsuarios");
        if (guardados) {
            const usuarios = JSON.parse(guardados);
            if (Array.isArray(usuarios) && usuarios.length > 0) return usuarios;
        }

        // Cuentas por defecto si localStorage está vacío
        const cuentasPorDefecto = [
            {
                nombre: "Juan Pérez",
                correo: "admin@requesty.com",
                contrasena: "admin123",
                rol: "Administrador",
                activo: true,
                permisos: ["menu", "mesas", "pedidos", "cocina", "caja", "pqrs", "reportes"]
            },
            {
                nombre: "Carlos Cajero",
                correo: "cajero@requesty.com",
                contrasena: "caja123",
                rol: "Cajero",
                activo: true,
                permisos: ["menu", "mesas", "pedidos", "caja"]
            },
            {
                nombre: "Mateo Mesero",
                correo: "mesero@requesty.com",
                contrasena: "mesero123",
                rol: "Mesero",
                activo: true,
                permisos: ["menu", "mesas", "pedidos"]
            },
            {
                nombre: "Claudia Cocina",
                correo: "cocina@requesty.com",
                contrasena: "cocina123",
                rol: "Cocina",
                activo: true,
                permisos: ["menu", "pedidos", "cocina"]
            },
            {
                nombre: "Cliente Demo",
                correo: "cliente@gmail.com",
                contrasena: "cliente123",
                rol: "Cliente",
                activo: true,
                permisos: ["menu", "pqrs"]
            }
        ];
        localStorage.setItem("requestyUsuarios", JSON.stringify(cuentasPorDefecto));
        return cuentasPorDefecto;
    } catch {
        return [];
    }
}

function paginaInicialUsuario(usuario) {
    const rol = claveRol(usuario.rol);
    const permisos = Array.isArray(usuario.permisos) ? usuario.permisos : permisosPorRol[rol] || [];
    const predeterminada = paginaInicialPorRol[rol];
    if (predeterminada && permisos.includes(predeterminada.replace(/\.(html|php)/, ""))) return predeterminada;
    return archivoPorPermiso[permisos[0]] || ((rol === "admin" || rol === "administrador") ? "index.php?page=reportes#cuentas" : loginUrl);
}

function iniciarSesion() {
    const correoInput = document.getElementById("usuario");
    const contrasenaInput = document.getElementById("contraseña");
    const mensaje = document.getElementById("loginMensaje");

    if (!correoInput || !contrasenaInput) return;

    const correo = correoInput.value.trim().toLowerCase();
    const contrasena = contrasenaInput.value;

    const usuario = leerUsuariosAuth().find((cuenta) =>
        cuenta.correo.toLowerCase() === correo && cuenta.contrasena === contrasena
    );

    if (!usuario || usuario.activo === false) {
        if (mensaje) mensaje.textContent = usuario ? "La cuenta está inactiva. Contacta al administrador." : "Correo o contraseña incorrectos.";
        return;
    }

    const sesion = JSON.stringify({ nombre: usuario.nombre, correo: usuario.correo, rol: usuario.rol });
    sessionStorage.removeItem("requestySesion");
    localStorage.removeItem("requestySesion");
    const recuerdameCheckbox = document.getElementById("checkbox_recuerdame");
    (recuerdameCheckbox && recuerdameCheckbox.checked ? localStorage : sessionStorage)
        .setItem("requestySesion", sesion);

    window.location.href = paginaInicialUsuario(usuario);
}

function protegerPagina(pagina) {
    const sesion = leerSesion();
    if (!sesion?.correo) {
        window.location.replace(loginUrl);
        return;
    }

    const cuenta = leerUsuariosAuth().find((usuario) => usuario.correo.toLowerCase() === sesion.correo.toLowerCase());
    if (!cuenta || cuenta.activo === false) {
        sessionStorage.removeItem("requestySesion");
        localStorage.removeItem("requestySesion");
        window.location.replace(loginUrl);
        return;
    }

    const rol = claveRol(cuenta.rol);
    const permisosBase = permisosPorRol[rol] || [];
    const esAdministrador = rol === "admin" || rol === "administrador";
    const paginasPermitidas = esAdministrador
        ? permisosPorRol.administrador
        : (Array.isArray(cuenta.permisos) ? cuenta.permisos : permisosBase);
    const puedeVerMenu = ["mesero", "cliente", "admin", "administrador"].includes(rol);
    const accesoCajaCliente = pagina === "caja" && rol === "cliente";
    const puedeAbrirPagina = (pagina === "reportes" && esAdministrador || accesoCajaCliente || paginasPermitidas.includes(pagina))
        && (pagina !== "menu" || puedeVerMenu);
    if (!puedeAbrirPagina) {
        window.location.replace(paginaInicialUsuario(cuenta));
        return;
    }

    sesion.nombre = cuenta.nombre;
    sesion.rol = cuenta.rol;
    sessionStorage.setItem("requestySesion", JSON.stringify(sesion));
    if (localStorage.getItem("requestySesion")) localStorage.setItem("requestySesion", JSON.stringify(sesion));

    const perfil = document.querySelector(".profile-info");
    if (perfil) {
        const nombreH4 = perfil.querySelector("h4");
        const rolP = perfil.querySelector("p");
        if (nombreH4) nombreH4.textContent = sesion.nombre;
        if (rolP) rolP.textContent = sesion.rol;
    }

    const avatar = document.querySelector(".profile-avatar");
    if (avatar) {
        avatar.textContent = sesion.nombre.split(/\s+/).slice(0, 2).map((parte) => parte[0]).join("").toUpperCase();
    }

    document.querySelectorAll(".sidebar-menu .sidebar-item").forEach((enlace) => {
        const href = enlace.getAttribute("href") || "";
        const destino = paginaPorEnlace[href] || (href.includes("page=") ? href.split("page=")[1] : null);
        if (href.includes("login") || href.includes("logout") || href.includes("index") || href === "#") return;
        if (!destino || !paginasPermitidas.includes(destino) || (destino === "menu" && !puedeVerMenu)) enlace.remove();
    });

    document.querySelectorAll("[data-admin-only]").forEach((elemento) => {
        if (esAdministrador) elemento.hidden = false;
        else elemento.remove();
    });

    const sidebarMenu = document.querySelector(".sidebar-menu");
    if (sidebarMenu && !sidebarMenu.querySelector(".btn-cerrar-sesion-js")) {
        const cerrarSesion = document.createElement("a");
        cerrarSesion.className = "sidebar-item btn-cerrar-sesion-js";
        cerrarSesion.href = "#";
        cerrarSesion.textContent = "Cerrar sesión";
        cerrarSesion.addEventListener("click", (evento) => {
            evento.preventDefault();
            sessionStorage.removeItem("requestySesion");
            localStorage.removeItem("requestySesion");
            window.location.href = loginUrl;
        });
        sidebarMenu.append(cerrarSesion);
    }
}

const botonInicio = document.getElementById("boton_inicio_de_sesion");
if (botonInicio) botonInicio.addEventListener("click", (e) => {
    // Si no está dentro de un submit normal o si es button normal
    if (botonInicio.type !== "submit") {
        e.preventDefault();
        iniciarSesion();
    }
});
