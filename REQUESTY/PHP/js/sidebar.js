/**
 * Requesty - Navegación Lateral (Sidebar) & Modal de Confirmación
 */
document.addEventListener("DOMContentLoaded", function () {
    const sidebar = document.getElementById("appSidebar");
    const collapseBtn = document.getElementById("sidebarCollapseBtn");
    const topbarToggle = document.getElementById("topbarSidebarToggle");
    const logoutBtn = document.getElementById("btnSidebarLogout");
    const logoutModal = document.getElementById("modalLogoutConfirm");
    const cancelLogoutBtn = document.getElementById("btnCancelLogout");
    const confirmLogoutBtn = document.getElementById("btnConfirmLogout");

    // ==========================================
    // 1. Estado de Colapso / Despliegue del Menú
    // ==========================================
    const STORAGE_KEY = "rq_sidebar_collapsed";

    function aplicarEstadoSidebar(colapsado) {
        if (!sidebar) return;
        if (colapsado) {
            sidebar.classList.add("collapsed");
            document.documentElement.classList.add("sidebar-is-collapsed");
            document.body.classList.add("sidebar-is-collapsed");
            if (collapseBtn) {
                collapseBtn.setAttribute("title", "Desplegar menú lateral");
                collapseBtn.setAttribute("aria-expanded", "false");
            }
        } else {
            sidebar.classList.remove("collapsed");
            document.documentElement.classList.remove("sidebar-is-collapsed");
            document.body.classList.remove("sidebar-is-collapsed");
            if (collapseBtn) {
                collapseBtn.setAttribute("title", "Plegar menú lateral");
                collapseBtn.setAttribute("aria-expanded", "true");
            }
        }
    }

    // Inicializar desde localStorage (si no está ya seteado por el inline script)
    const guardado = localStorage.getItem(STORAGE_KEY);
    if (guardado === "1") {
        aplicarEstadoSidebar(true);
    } else if (guardado === "0") {
        aplicarEstadoSidebar(false);
    } else {
        // En pantallas grandes por defecto abierto, en pantallas pequeñas por defecto colapsado
        if (window.innerWidth <= 1024 && window.innerWidth > 768) {
            aplicarEstadoSidebar(true);
        } else {
            aplicarEstadoSidebar(false);
        }
    }

    function toggleSidebar() {
        if (!sidebar) return;
        const estaColapsado = sidebar.classList.contains("collapsed");
        const nuevoEstado = !estaColapsado;
        aplicarEstadoSidebar(nuevoEstado);
        try {
            localStorage.setItem(STORAGE_KEY, nuevoEstado ? "1" : "0");
        } catch (e) {}
    }

    if (collapseBtn) {
        collapseBtn.addEventListener("click", function (e) {
            e.preventDefault();
            toggleSidebar();
        });
    }

    if (topbarToggle) {
        topbarToggle.addEventListener("click", function (e) {
            e.preventDefault();
            // En móvil puede ser toggle de menú móvil
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle("mobile-open");
                document.body.classList.toggle("sidebar-mobile-open");
            } else {
                toggleSidebar();
            }
        });
    }

    // Cerrar menú móvil al hacer click fuera
    document.addEventListener("click", function (e) {
        if (window.innerWidth <= 768 && sidebar && sidebar.classList.contains("mobile-open")) {
            if (!sidebar.contains(e.target) && (!topbarToggle || !topbarToggle.contains(e.target))) {
                sidebar.classList.remove("mobile-open");
                document.body.classList.remove("sidebar-mobile-open");
            }
        }
    });

    // ==========================================
    // 2. Modal de Confirmación de Cerrar Sesión
    // ==========================================
    function abrirModalLogout() {
        if (!logoutModal) return;
        logoutModal.style.display = "flex";
        logoutModal.setAttribute("aria-hidden", "false");
        // Forzar reflow para animación CSS
        void logoutModal.offsetWidth;
        logoutModal.classList.add("show");
        document.body.style.overflow = "hidden";
        if (cancelLogoutBtn) cancelLogoutBtn.focus();
    }

    function cerrarModalLogout() {
        if (!logoutModal) return;
        logoutModal.classList.remove("show");
        setTimeout(function () {
            logoutModal.style.display = "none";
            logoutModal.setAttribute("aria-hidden", "true");
            document.body.style.overflow = "";
        }, 200);
    }

    if (logoutBtn) {
        logoutBtn.addEventListener("click", function (e) {
            e.preventDefault();
            abrirModalLogout();
        });
    }

    // Cualquier otro botón de logout con data-action="logout"
    document.querySelectorAll('[data-action="logout"], .btn-cerrar-sesion-js').forEach(function (btn) {
        btn.addEventListener("click", function (e) {
            e.preventDefault();
            abrirModalLogout();
        });
    });

    if (cancelLogoutBtn) {
        cancelLogoutBtn.addEventListener("click", function (e) {
            e.preventDefault();
            cerrarModalLogout();
        });
    }

    // Confirmar logout: limpiar almacenamiento y proceder
    if (confirmLogoutBtn) {
        confirmLogoutBtn.addEventListener("click", function () {
            try {
                sessionStorage.removeItem("requestySesion");
                localStorage.removeItem("requestySesion");
            } catch (e) {}
        });
    }

    // Cerrar al hacer click en el overlay oscuro exterior
    if (logoutModal) {
        logoutModal.addEventListener("click", function (e) {
            if (e.target === logoutModal) {
                cerrarModalLogout();
            }
        });
    }

    // Cerrar con tecla Escape
    document.addEventListener("keydown", function (e) {
        if (e.key === "Escape" && logoutModal && logoutModal.classList.contains("show")) {
            cerrarModalLogout();
        }
    });
});
