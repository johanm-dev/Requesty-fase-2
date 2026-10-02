const permisosPorRol = {
    administrador: ["menu", "mesas", "pedidos", "cocina", "caja", "pqrs", "reportes"],
    admin: ["menu", "mesas", "pedidos", "cocina", "caja", "pqrs", "reportes"],
    cajero: ["menu", "mesas", "pedidos", "caja"],
    mesero: ["menu", "mesas", "pedidos"],
    cocina: ["menu", "pedidos", "cocina"],
    cliente: ["menu", "pqrs"]
};

const paginaPorEnlace = {
    "menu.html": "menu",
    "mesas.html": "mesas",
    "pedidos.html": "pedidos",
    "cocina.html": "cocina",
    "caja.html": "caja",
    "Pqrs.html": "pqrs",
    "reportes.html": "reportes"
};

const paginaInicialPorRol = {
    administrador: "mesas.html",
    admin: "mesas.html",
    cajero: "caja.html",
    mesero: "mesas.html",
    cocina: "cocina.html",
    cliente: "menu.html"
};
const archivoPorPermiso = {
    menu: "menu.html",
    mesas: "mesas.html",
    pedidos: "pedidos.html",
    cocina: "cocina.html",
    caja: "caja.html",
    pqrs: "Pqrs.html",
    reportes: "reportes.html"
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
        const usuarios = JSON.parse(localStorage.getItem("requestyUsuarios") || "[]");
        return Array.isArray(usuarios) ? usuarios : [];
    } catch {
        return [];
    }
}

function paginaInicialUsuario(usuario) {
    const rol = claveRol(usuario.rol);
    const permisos = Array.isArray(usuario.permisos) ? usuario.permisos : permisosPorRol[rol] || [];
    const predeterminada = paginaInicialPorRol[rol];
    if (predeterminada && permisos.includes(predeterminada.replace(".html", ""))) return predeterminada;
    return archivoPorPermiso[permisos[0]] || ((rol === "admin" || rol === "administrador") ? "cuentas.html" : "index.html");
}

function iniciarSesion() {
    const correo = document.getElementById("usuario").value.trim().toLowerCase();
    const contrasena = document.getElementById("contraseña").value;
    const mensaje = document.getElementById("loginMensaje");
    const usuario = leerUsuariosAuth().find((cuenta) =>
        cuenta.correo.toLowerCase() === correo && cuenta.contrasena === contrasena
    );

    if (!usuario || usuario.activo === false) {
        mensaje.textContent = usuario ? "La cuenta está inactiva. Contacta al administrador." : "Correo o contraseña incorrectos.";
        return;
    }

    const sesion = JSON.stringify({ nombre: usuario.nombre, correo: usuario.correo, rol: usuario.rol });
    sessionStorage.removeItem("requestySesion");
    localStorage.removeItem("requestySesion");
    (document.getElementById("checkbox_recuerdame").checked ? localStorage : sessionStorage)
        .setItem("requestySesion", sesion);

    window.location.href = paginaInicialUsuario(usuario);
}

function protegerPagina(pagina) {
    const sesion = leerSesion();
    if (!sesion?.correo) {
        window.location.replace("index.html");
        return;
    }

    const cuenta = leerUsuariosAuth().find((usuario) => usuario.correo.toLowerCase() === sesion.correo.toLowerCase());
    if (!cuenta || cuenta.activo === false) {
        sessionStorage.removeItem("requestySesion");
        localStorage.removeItem("requestySesion");
        window.location.replace("index.html");
        return;
    }

    const rol = claveRol(cuenta.rol);
    const permisosBase = permisosPorRol[rol] || [];
    const esAdministrador = rol === "admin" || rol === "administrador";
    // El administrador siempre ve todas las interfaces (evita quedar bloqueado por permisos guardados)
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
        perfil.querySelector("h4").textContent = sesion.nombre;
        perfil.querySelector("p").textContent = sesion.rol;
    }

    const avatar = document.querySelector(".profile-avatar");
    if (avatar) {
        avatar.textContent = sesion.nombre.split(/\s+/).slice(0, 2).map((parte) => parte[0]).join("").toUpperCase();
    }

    document.querySelectorAll(".sidebar-menu .sidebar-item").forEach((enlace) => {
        const destino = paginaPorEnlace[enlace.getAttribute("href")];
        if (!destino || !paginasPermitidas.includes(destino) || destino === "menu" && !puedeVerMenu) enlace.remove();
    });

    document.querySelectorAll("[data-admin-only]").forEach((elemento) => {
        if (esAdministrador) elemento.hidden = false;
        else elemento.remove();
    });

    const cerrarSesion = document.createElement("a");
    cerrarSesion.className = "sidebar-item";
    cerrarSesion.href = "#";
    cerrarSesion.textContent = "Cerrar sesión";
    cerrarSesion.addEventListener("click", (evento) => {
        evento.preventDefault();
        sessionStorage.removeItem("requestySesion");
        localStorage.removeItem("requestySesion");
        window.location.href = "index.html";
    });
    document.querySelector(".sidebar-menu")?.append(cerrarSesion);
}

const botonInicio = document.getElementById("boton_inicio_de_sesion");
if (botonInicio) botonInicio.addEventListener("click", iniciarSesion);