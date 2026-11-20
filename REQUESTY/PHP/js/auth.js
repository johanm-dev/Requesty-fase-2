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
        // Cuentas por defecto (incluye usuarios semilla PostgreSQL 17 y cuentas demo)
        const cuentasPorDefecto = [
            {
                nombre: "Carlos Gomez",
                correo: "admin@restaurante.local",
                contrasena: "AdminRest2026*",
                rol: "Administrador",
                activo: true,
                permisos: ["menu", "mesas", "pedidos", "cocina", "caja", "pqrs", "reportes"]
            },
            {
                nombre: "Laura Martinez",
                correo: "cajero@restaurante.local",
                contrasena: "CajeroRest2026*",
                rol: "Cajero",
                activo: true,
                permisos: ["menu", "mesas", "pedidos", "caja"]
            },
            {
                nombre: "Mateo Ruiz",
                correo: "mesero@restaurante.local",
                contrasena: "MeseroRest2026*",
                rol: "Mesero",
                activo: true,
                permisos: ["menu", "mesas", "pedidos"]
            },
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

        const guardados = localStorage.getItem("requestyUsuarios");
        if (guardados) {
            const usuarios = JSON.parse(guardados);
            if (Array.isArray(usuarios) && usuarios.length > 0) {
                const correosExistentes = new Set(usuarios.map(u => String(u.correo || "").toLowerCase()));
                for (const base of cuentasPorDefecto) {
                    if (!correosExistentes.has(base.correo.toLowerCase())) {
                        usuarios.push(base);
                    }
                }
                return usuarios;
            }
        }
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

async function protegerPagina(pagina) {
    let sesion = leerSesion();
    let cuentaServidor = null;
    try {
        const respuesta = await fetch("index.php?page=api/sesion", {
            headers: { "Accept": "application/json" },
            cache: "no-store"
        });
        if (!respuesta.ok) throw new Error(`Validación de sesión respondió ${respuesta.status}`);
        const datos = await respuesta.json();
        if (datos.autenticado && datos.usuario?.correo) {
            sesion = {
                nombre: datos.usuario.nombre,
                correo: datos.usuario.correo,
                rol: datos.usuario.rol
            };
            const rolServidor = claveRol(sesion.rol);
            cuentaServidor = {
                ...sesion,
                activo: true,
                permisos: permisosPorRol[rolServidor] || []
            };
            sessionStorage.setItem("requestySesion", JSON.stringify(sesion));
            if (localStorage.getItem("requestySesion")) {
                localStorage.setItem("requestySesion", JSON.stringify(sesion));
            }
        }
    } catch (error) {
        console.error("No se pudo validar la sesión con el servidor.", error);
    }

    if (!sesion?.correo) {
        window.location.replace(loginUrl);
        return;
    }

    let cuenta = cuentaServidor || leerUsuariosAuth().find((usuario) => usuario.correo.toLowerCase() === sesion.correo.toLowerCase());
    if (!cuenta) {
        const rolSesion = claveRol(sesion.rol);
        cuenta = {
            nombre: sesion.nombre,
            correo: sesion.correo,
            rol: sesion.rol,
            activo: true,
            permisos: permisosPorRol[rolSesion] || []
        };
    }
    if (cuenta.activo === false) {
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

    document.querySelectorAll(".sidebar-menu .sidebar-item, .top-nav-menu .nav-btn").forEach((enlace) => {
        const href = enlace.getAttribute("href") || "";
        const destino = paginaPorEnlace[href] || (href.includes("page=") ? href.split("page=")[1] : null);
        if (href.includes("login") || href.includes("logout") || href.includes("index") || href === "#") return;
        if (!destino || !paginasPermitidas.includes(destino) || (destino === "menu" && !puedeVerMenu)) enlace.remove();
    });

    document.querySelectorAll("[data-admin-only]").forEach((elemento) => {
        if (esAdministrador) elemento.hidden = false;
        else elemento.remove();
    });

    // Si no existe el botón de logout estilizado en el sidebar, proveer fallback
    const sidebarMenu = document.querySelector(".sidebar-menu");
    const logoutBtnExistente = document.getElementById("btnSidebarLogout");
    if (sidebarMenu && !logoutBtnExistente && !sidebarMenu.querySelector(".btn-cerrar-sesion-js")) {
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

// Efecto de presión ("hundirse") y onda al hacer clic en los botones del login y registro
document.querySelectorAll("#registro .boton, .seccion-formulario .boton, .btn-primario, .btn-secundario").forEach((boton) => {
    boton.addEventListener("pointerdown", (e) => {
        boton.classList.add("btn-hundido");

        const rect = boton.getBoundingClientRect();
        const tamano = Math.max(rect.width, rect.height);
        const onda = document.createElement("span");
        onda.className = "onda-click";
        onda.style.width = `${tamano}px`;
        onda.style.height = `${tamano}px`;
        onda.style.left = `${e.clientX - rect.left - tamano / 2}px`;
        onda.style.top = `${e.clientY - rect.top - tamano / 2}px`;
        boton.appendChild(onda);
        onda.addEventListener("animationend", () => onda.remove());
    });

    const soltarBoton = () => {
        setTimeout(() => boton.classList.remove("btn-hundido"), 110);
    };

    boton.addEventListener("pointerup", soltarBoton);
    boton.addEventListener("pointerleave", soltarBoton);
    boton.addEventListener("pointercancel", soltarBoton);
});

// Animación de luciérnagas en el fondo de inicio de sesión
(function iniciarLuciernagasLogin() {
    const canvas = document.getElementById("luciernagasCanvas");
    if (!canvas) return;

    const ctx = canvas.getContext("2d");
    if (!ctx) return;

    let ancho = (canvas.width = window.innerWidth);
    let alto = (canvas.height = window.innerHeight);

    window.addEventListener("resize", () => {
        ancho = canvas.width = window.innerWidth;
        alto = canvas.height = window.innerHeight;
    });

    const cantidad = 34;
    const luciernagas = Array.from({ length: cantidad }, () => ({
        x: Math.random() * ancho,
        y: Math.random() * alto,
        radio: 2.2 + Math.random() * 3.4,
        angulo: Math.random() * Math.PI * 2,
        velocidad: 0.35 + Math.random() * 0.65,
        giro: (Math.random() - 0.5) * 0.025,
        faseBrillo: Math.random() * Math.PI * 2,
        velBrillo: 0.02 + Math.random() * 0.035
    }));

    function animar() {
        ctx.clearRect(0, 0, ancho, alto);

        for (const l of luciernagas) {
            l.angulo += l.giro + (Math.random() - 0.5) * 0.015;
            l.x += Math.cos(l.angulo) * l.velocidad;
            l.y += Math.sin(l.angulo) * l.velocidad - 0.15;
            l.faseBrillo += l.velBrillo;

            if (l.x < -30) l.x = ancho + 30;
            if (l.x > ancho + 30) l.x = -30;
            if (l.y < -30) l.y = alto + 30;
            if (l.y > alto + 30) l.y = -30;

            const pulso = 0.25 + ((Math.sin(l.faseBrillo) + 1) / 2) * 0.75;
            const haloRadio = l.radio * 5.2;

            const gradiente = ctx.createRadialGradient(l.x, l.y, 0, l.x, l.y, haloRadio);
            gradiente.addColorStop(0, `rgba(255, 250, 215, ${0.95 * pulso})`);
            gradiente.addColorStop(0.3, `rgba(245, 185, 66, ${0.75 * pulso})`);
            gradiente.addColorStop(0.65, `rgba(201, 155, 69, ${0.32 * pulso})`);
            gradiente.addColorStop(1, "rgba(201, 155, 69, 0)");

            ctx.beginPath();
            ctx.fillStyle = gradiente;
            ctx.arc(l.x, l.y, haloRadio, 0, Math.PI * 2);
            ctx.fill();

            ctx.beginPath();
            ctx.fillStyle = `rgba(255, 252, 230, ${Math.min(1, pulso + 0.15)})`;
            ctx.arc(l.x, l.y, l.radio * 0.65, 0, Math.PI * 2);
            ctx.fill();
        }

        requestAnimationFrame(animar);
    }

    requestAnimationFrame(animar);
})();
