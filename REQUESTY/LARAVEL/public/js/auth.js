(function () {
    const permisosPorRol = {
        administrador: ["inicio", "menu", "mesas", "pedidos", "cocina", "caja", "pqrs", "reportes"],
        admin: ["inicio", "menu", "mesas", "pedidos", "cocina", "caja", "pqrs", "reportes"],
        cajero: ["inicio", "menu", "mesas", "pedidos", "caja"],
        mesero: ["inicio", "menu", "mesas", "pedidos"],
        cocina: ["inicio", "menu", "pedidos", "cocina"],
        cliente: ["inicio", "menu", "pqrs", "caja"]
    };

    const paginaPorEnlace = {
        "/inicio": "inicio",
        "/menu": "menu",
        "/mesas": "mesas",
        "/pedidos": "pedidos",
        "/cocina": "cocina",
        "/caja": "caja",
        "/pqrs": "pqrs",
        "/reportes": "reportes"
    };

    const paginaInicialPorRol = {
        administrador: "/mesas",
        admin: "/mesas",
        cajero: "/caja",
        mesero: "/mesas",
        cocina: "/cocina",
        cliente: "/menu"
    };

    const loginUrl = "/login";
    let sesionMemoria = null;
    let csrfTokenActual = "";

    function metaContent(name) {
        return document.querySelector(`meta[name="${name}"]`)?.getAttribute("content") || "";
    }

    function obtenerCsrfToken() {
        return csrfTokenActual || metaContent("csrf-token") || document.querySelector('input[name="_token"]')?.value || document.querySelector('input[name="_csrf"]')?.value || "";
    }

    function buildApiUrl(route, params) {
        const cleanRoute = String(route || "").replace(/^\/+/, "");
        const base = metaContent("app-base").replace(/\/$/, "");
        const prefix = base ? `${base}/${cleanRoute}` : `/${cleanRoute}`;
        if (!params || typeof params !== "object") return prefix;
        const qs = new URLSearchParams();
        Object.entries(params).forEach(([k, v]) => {
            if (v !== undefined && v !== null && v !== "") qs.set(k, String(v));
        });
        const tail = qs.toString();
        return tail ? `${prefix}?${tail}` : prefix;
    }

    async function apiRequest(route, options = {}) {
        const method = String(options.method || "GET").toUpperCase();
        const url = buildApiUrl(route, options.params);
        const headers = {
            "Accept": "application/json",
            "X-Requested-With": "XMLHttpRequest",
            ...(options.headers || {})
        };
        if (method !== "GET" && method !== "HEAD") {
            const csrf = obtenerCsrfToken();
            if (csrf) headers["X-CSRF-Token"] = csrf;
        }
        let body = options.body;
        if (body !== undefined && body !== null && !(body instanceof FormData) && typeof body !== "string") {
            headers["Content-Type"] = "application/json";
            body = JSON.stringify(body);
        }

        const resp = await fetch(url, {
            method,
            headers,
            body,
            credentials: "same-origin"
        });
        let data = null;
        try {
            data = await resp.json();
        } catch {
            data = null;
        }
        if (resp.status === 401 && route !== "api/login" && route !== "api/sesion") {
            window.location.replace(loginUrl);
        }
        if (!resp.ok || (data && data.success === false)) {
            const err = new Error(data?.message || `Error HTTP ${resp.status}`);
            err.status = resp.status;
            err.payload = data;
            throw err;
        }
        if (data?.csrf_token) csrfTokenActual = data.csrf_token;
        return data;
    }

    function claveRol(rol) {
        return String(rol || "").trim().normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase();
    }

    function leerSesion() {
        return sesionMemoria;
    }

    async function cargarSesionServidor() {
        try {
            const data = await apiRequest("api/sesion");
            if (data?.autenticado && data.usuario) {
                sesionMemoria = {
                    id: Number(data.usuario.id || data.usuario.id_usuario || 0),
                    nombre: String(data.usuario.nombre || ""),
                    correo: String(data.usuario.correo || ""),
                    rol: String(data.usuario.rol || ""),
                    permisos: Array.isArray(data.permisos) ? data.permisos : []
                };
                if (data.csrf_token) csrfTokenActual = data.csrf_token;
                window.dispatchEvent(new CustomEvent("requesty-sesion-lista", { detail: sesionMemoria }));
                return sesionMemoria;
            }
        } catch (err) {
            if (err.status === 401) {
                sesionMemoria = null;
            }
        }
        return sesionMemoria;
    }

    function paginaInicialUsuario(usuario) {
        const rol = claveRol(usuario?.rol);
        return paginaInicialPorRol[rol] || "/mesas";
    }

    function aplicarPermisosEnInterfaz(usuario, pagina) {
        if (!usuario) return;
        const rol = claveRol(usuario.rol);
        const esAdministrador = rol === "admin" || rol === "administrador";
        const paginasPermitidas = esAdministrador
            ? permisosPorRol.administrador
            : (Array.isArray(usuario.permisos) && usuario.permisos.length ? usuario.permisos : permisosPorRol[rol] || []);

        const puedeAbrirPagina = esAdministrador
            || (pagina === "caja" && rol === "cliente")
            || paginasPermitidas.includes(pagina);

        if (pagina && !puedeAbrirPagina) {
            window.location.replace(paginaInicialUsuario(usuario));
            return;
        }

        const perfil = document.querySelector(".profile-info");
        if (perfil) {
            const nombreH4 = perfil.querySelector("h4");
            const rolP = perfil.querySelector("p");
            if (nombreH4 && usuario.nombre) nombreH4.textContent = usuario.nombre;
            if (rolP && usuario.rol) rolP.textContent = usuario.rol;
        }

        const avatar = document.querySelector(".profile-avatar");
        if (avatar && usuario.nombre) {
            avatar.textContent = usuario.nombre.split(/\s+/).slice(0, 2).map((p) => p[0]).join("").toUpperCase();
        }

        document.querySelectorAll(".sidebar-menu .sidebar-item, .top-nav-menu .nav-btn").forEach((enlace) => {
            const href = enlace.getAttribute("href") || "";
            if (href.includes("login") || href.includes("logout") || href.includes("inicio") || href === "#") return;
            let destino = paginaPorEnlace[href] || null;
            if (!destino) {
                try {
                    const seg = new URL(href, window.location.origin).pathname.replace(/^\/+/, "").split("/")[0];
                    destino = seg || null;
                } catch {
                    destino = null;
                }
            }
            if (!destino) return;
            if (!paginasPermitidas.includes(destino)) enlace.remove();
        });

        document.querySelectorAll("[data-admin-only]").forEach((el) => {
            if (esAdministrador) el.hidden = false;
            else el.remove();
        });
    }

    async function protegerPagina(pagina) {
        const sesion = await cargarSesionServidor();
        if (!sesion) {
            window.location.replace(loginUrl);
            return null;
        }
        aplicarPermisosEnInterfaz(sesion, pagina);
        return sesion;
    }

    function escaparHTML(valor) {
        return String(valor ?? "").replace(/[&<>"']/g, (c) => ({
            "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;"
        })[c]);
    }

    window.RequestyAPI = Object.freeze({
        get: (route, params) => apiRequest(route, { method: "GET", params }),
        post: (route, body, params) => apiRequest(route, { method: "POST", body, params }),
        put: (route, body, params) => apiRequest(route, { method: "PUT", body, params }),
        del: (route, body, params) => apiRequest(route, { method: "DELETE", body, params }),
        cargarSesion: cargarSesionServidor,
        leerSesion,
        escaparHTML,
        obtenerCsrfToken,
        buildApiUrl
    });

    window.claveRol = claveRol;
    window.leerSesion = leerSesion;
    window.protegerPagina = protegerPagina;

    const formLogin = document.getElementById("formLogin");
    if (formLogin) {
        formLogin.addEventListener("submit", async (evento) => {
            evento.preventDefault();
            const correoInput = document.getElementById("usuario");
            const passInput = document.getElementById("contraseña");
            const mensaje = document.getElementById("loginMensaje");
            const correo = correoInput?.value.trim().toLowerCase() || "";
            const contrasena = passInput?.value || "";
            if (!correo || !contrasena) {
                if (mensaje) mensaje.textContent = "Por favor ingresa tu correo y contraseña.";
                return;
            }
            try {
                const res = await apiRequest("api/login", {
                    method: "POST",
                    body: { correo, contrasena }
                });
                window.location.href = res.redirectUrl || paginaInicialUsuario(res.usuario);
            } catch (err) {
                if (mensaje) mensaje.textContent = err.message || "Correo o contraseña incorrectos.";
            }
        });
    }

    const paginaActual = document.body?.dataset?.page;
    if (paginaActual) {
        protegerPagina(paginaActual);
    }

    // Efecto táctil ("hundirse") y onda al hacer clic en los botones del login y registro
    document.querySelectorAll("#registro .boton, .seccion-formulario .boton, .boton, .btn-primario, .btn-secundario").forEach((boton) => {
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

    // Animación de 34 luciérnagas en el fondo de inicio de sesión y registro
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
})();
