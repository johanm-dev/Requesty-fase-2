// Gestión de Cuentas del Personal conectada 100% a PostgreSQL (api/usuarios)
(function () {
    const form = document.getElementById("formCuenta");
    const modal = document.getElementById("modalCuenta");
    const tbody = document.getElementById("listaCuentas");
    const busquedaCuenta = document.getElementById("buscarCuenta");
    const campoRol = document.getElementById("rolCuenta");
    const campoId = document.getElementById("idCuenta");
    const campoDoc = document.getElementById("documentoCuenta");
    const docWrap = document.getElementById("campoDocumentoWrap");
    const campoContrasena = document.getElementById("contrasenaCuenta");
    const mensaje = document.getElementById("mensajeCuenta");
    if (!form || !tbody) return;

    const permisosPorRol = {
        ADMIN: ["menu", "mesas", "pedidos", "cocina", "caja", "pqrs", "reportes"],
        CAJERO: ["menu", "mesas", "pedidos", "caja"],
        MESERO: ["menu", "mesas", "pedidos"],
        COCINA: ["menu", "pedidos", "cocina"],
        CLIENTE: ["menu", "pqrs", "caja"]
    };
    const interfaces = [
        ["menu", "Catálogo"], ["mesas", "Mesas"], ["pedidos", "Pedidos"],
        ["cocina", "Cocina"], ["caja", "Pagos/Caja"], ["pqrs", "PQRS"], ["reportes", "Reportes"]
    ];
    let usuariosCache = [];

    function esc(v) {
        return window.RequestyAPI.escaparHTML(v);
    }

    function dibujarPermisos(rol) {
        const contenedor = document.getElementById("listaPermisos");
        if (!contenedor) return;
        const activos = permisosPorRol[String(rol || "").toUpperCase()] || [];
        contenedor.replaceChildren();
        interfaces.forEach(([clave, etiqueta]) => {
            const label = document.createElement("label");
            label.className = "permiso-opcion";
            const checkbox = document.createElement("input");
            checkbox.type = "checkbox";
            checkbox.disabled = true;
            checkbox.checked = activos.includes(clave);
            const span = document.createElement("span");
            span.textContent = etiqueta;
            label.append(checkbox, span);
            contenedor.append(label);
        });
    }

    async function cargarCuentas() {
        try {
            const res = await window.RequestyAPI.get("api/usuarios");
            usuariosCache = Array.isArray(res?.usuarios) ? res.usuarios : [];
            renderizarCuentas();
        } catch (err) {
            if (mensaje) mensaje.textContent = err.message || "Error al cargar usuarios.";
        }
    }

    function renderizarCuentas() {
        const termino = (busquedaCuenta?.value || "").trim().toLowerCase();
        const visibles = usuariosCache.filter((u) =>
            `${u.nombre} ${u.apellido || ""} ${u.correo}`.toLowerCase().includes(termino)
        );
        document.getElementById("totalCuentas").textContent = usuariosCache.length;
        document.getElementById("cuentasActivas").textContent = usuariosCache.filter((u) => u.estado === "ACTIVO").length;
        document.getElementById("cuentasInactivas").textContent = usuariosCache.filter((u) => u.estado !== "ACTIVO").length;
        document.getElementById("sinCuentas").hidden = visibles.length > 0;

        tbody.innerHTML = visibles.map((u) => {
            const activo = u.estado === "ACTIVO";
            const rol = String(u.rol || "").toUpperCase();
            const perms = permisosPorRol[rol] || [];
            return `<tr>
                <td><strong>${esc(`${u.nombre} ${u.apellido || ""}`.trim())}</strong></td>
                <td>${esc(u.correo)}</td>
                <td>${esc(u.rol)}</td>
                <td><span class="estado-cuenta ${activo ? "" : "inactivo"}">${esc(u.estado)}</span></td>
                <td>${perms.length} de ${interfaces.length}</td>
                <td><div class="acciones-cuenta">
                    <button class="btn-accion" type="button" data-accion="editar" data-id="${Number(u.id_usuario)}">Editar</button>
                    <button class="btn-accion ${activo ? "peligro" : ""}" type="button" data-accion="estado" data-id="${Number(u.id_usuario)}">${activo ? "Inactivar" : "Activar"}</button>
                </div></td>
            </tr>`;
        }).join("");
    }

    function abrirNuevaCuenta() {
        form.reset();
        campoId.value = "";
        if (docWrap) docWrap.hidden = false;
        if (campoDoc) campoDoc.required = true;
        campoContrasena.required = true;
        document.getElementById("tituloModal").textContent = "Nueva cuenta";
        document.getElementById("etiquetaContrasena").firstChild.textContent = "Contraseña";
        mensaje.textContent = "";
        dibujarPermisos(campoRol.value);
        modal.showModal();
    }

    function abrirEdicion(idUsuario) {
        const u = usuariosCache.find((x) => Number(x.id_usuario) === Number(idUsuario));
        if (!u) return;
        form.reset();
        campoId.value = String(u.id_usuario);
        document.getElementById("nombreCuenta").value = u.nombre || "";
        document.getElementById("apellidoCuenta").value = u.apellido || "";
        if (docWrap) docWrap.hidden = true;
        if (campoDoc) campoDoc.required = false;
        document.getElementById("correoCuenta").value = u.correo || "";
        document.getElementById("telefonoCuenta").value = u.telefono || "";
        campoRol.value = String(u.rol || "MESERO").toUpperCase();
        campoContrasena.required = false;
        document.getElementById("tituloModal").textContent = "Editar cuenta";
        document.getElementById("etiquetaContrasena").firstChild.textContent = "Nueva contraseña (opcional)";
        mensaje.textContent = "";
        dibujarPermisos(campoRol.value);
        modal.showModal();
    }

    async function cambiarEstado(idUsuario) {
        const u = usuariosCache.find((x) => Number(x.id_usuario) === Number(idUsuario));
        if (!u) return;
        const nuevoEstado = u.estado === "ACTIVO" ? "INACTIVO" : "ACTIVO";
        try {
            await window.RequestyAPI.put("api/usuarios", { id_usuario: Number(idUsuario), estado: nuevoEstado });
            await cargarCuentas();
        } catch (err) {
            window.alert(err.message || "No se pudo cambiar el estado.");
        }
    }

    async function guardarCuenta(evento) {
        evento.preventDefault();
        mensaje.textContent = "";
        const idUsuario = campoId.value ? Number(campoId.value) : null;
        const payload = {
            nombre: document.getElementById("nombreCuenta").value.trim(),
            apellido: document.getElementById("apellidoCuenta").value.trim(),
            correo: document.getElementById("correoCuenta").value.trim().toLowerCase(),
            telefono: document.getElementById("telefonoCuenta").value.trim() || null,
            rol: campoRol.value
        };
        const pass = campoContrasena.value;
        if (pass && (pass.length < 8 || !/[A-Z]/.test(pass) || !/[a-z]/.test(pass) || !/[0-9]/.test(pass))) {
            mensaje.textContent = "La contraseña debe tener mínimo 8 caracteres, mayúscula, minúscula y número.";
            return;
        }
        if (!idUsuario && !pass) {
            mensaje.textContent = "La contraseña es obligatoria para cuentas nuevas.";
            return;
        }

        try {
            if (idUsuario) {
                if (pass) payload.contrasena = pass;
                payload.id_usuario = idUsuario;
                await window.RequestyAPI.put("api/usuarios", payload);
            } else {
                payload.documento = campoDoc.value.trim();
                payload.contrasena = pass;
                await window.RequestyAPI.post("api/usuarios", payload);
            }
            modal.close();
            await cargarCuentas();
        } catch (err) {
            mensaje.textContent = err.message || "No se pudo guardar la cuenta.";
        }
    }

    document.getElementById("nuevaCuenta")?.addEventListener("click", abrirNuevaCuenta);
    document.getElementById("cerrarModal")?.addEventListener("click", () => modal.close());
    document.getElementById("cancelarCuenta")?.addEventListener("click", () => modal.close());
    campoRol?.addEventListener("change", () => dibujarPermisos(campoRol.value));
    form.addEventListener("submit", guardarCuenta);
    busquedaCuenta?.addEventListener("input", renderizarCuentas);

    tbody.addEventListener("click", (e) => {
        const btn = e.target.closest("button[data-accion]");
        if (!btn) return;
        if (btn.dataset.accion === "editar") abrirEdicion(btn.dataset.id);
        if (btn.dataset.accion === "estado") cambiarEstado(btn.dataset.id);
    });

    if (window.RequestyAPI.leerSesion()) {
        cargarCuentas();
    } else {
        window.addEventListener("requesty-sesion-lista", cargarCuentas, { once: true });
    }
})();
