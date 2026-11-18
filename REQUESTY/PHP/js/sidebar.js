/**
 * Requesty - Navegación Lateral (Sidebar) & Modal de Confirmación
 */
(function () {
    const STORAGE_KEY = "rq_sidebar_collapsed";

    function initSidebar() {
        const sidebar = document.getElementById("appSidebar");
        const collapseBtn = document.getElementById("sidebarCollapseBtn");
        const topbarToggle = document.getElementById("topbarSidebarToggle");
        const sidebarLogo = document.getElementById("sidebarLogo");
        const logoutBtn = document.getElementById("btnSidebarLogout");
        const logoutModal = document.getElementById("modalLogoutConfirm");
        const cancelLogoutBtn = document.getElementById("btnCancelLogout");
        const confirmLogoutBtn = document.getElementById("btnConfirmLogout");

        if (!sidebar) return;

        // ==========================================
        // 1. Estado de Colapso / Despliegue
        // ==========================================
        function setCollapsed(collapsed, saveStorage) {
            if (collapsed) {
                sidebar.classList.add("collapsed");
                document.body.classList.add("sidebar-collapsed");
                document.documentElement.classList.add("sidebar-collapsed");
                if (collapseBtn) {
                    collapseBtn.setAttribute("title", "Desplegar menú lateral");
                    collapseBtn.setAttribute("aria-expanded", "false");
                }
            } else {
                sidebar.classList.remove("collapsed");
                document.body.classList.remove("sidebar-collapsed");
                document.documentElement.classList.remove("sidebar-collapsed");
                if (collapseBtn) {
                    collapseBtn.setAttribute("title", "Plegar menú lateral");
                    collapseBtn.setAttribute("aria-expanded", "true");
                }
            }
            if (saveStorage) {
                try {
                    localStorage.setItem(STORAGE_KEY, collapsed ? "1" : "0");
                } catch (e) {}
            }
        }

        function toggleSidebar() {
            const isCurrentlyCollapsed = sidebar.classList.contains("collapsed") || document.body.classList.contains("sidebar-collapsed");
            setCollapsed(!isCurrentlyCollapsed, true);
        }

        // Leer preferencia guardada
        try {
            const savedState = localStorage.getItem(STORAGE_KEY);
            if (savedState === "1") {
                setCollapsed(true, false);
            } else {
                setCollapsed(false, false);
            }
        } catch (e) {
            setCollapsed(false, false);
        }

        // Botón en la cabecera del sidebar
        if (collapseBtn) {
            collapseBtn.addEventListener("click", function (e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        }

        // Botón en el topbar (hamburguesa)
        if (topbarToggle) {
            topbarToggle.addEventListener("click", function (e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        }

        // Al hacer click en el logo cuando está colapsado, desplegar
        if (sidebarLogo) {
            sidebarLogo.addEventListener("click", function () {
                if (sidebar.classList.contains("collapsed")) {
                    setCollapsed(false, true);
                }
            });
        }

        // ==========================================
        // 2. Modal de Confirmación de Cerrar Sesión
        // ==========================================
        function abrirModalLogout() {
            if (!logoutModal) return;
            logoutModal.style.display = "flex";
            logoutModal.setAttribute("aria-hidden", "false");
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

        if (confirmLogoutBtn) {
            confirmLogoutBtn.addEventListener("click", function () {
                try {
                    sessionStorage.removeItem("requestySesion");
                    localStorage.removeItem("requestySesion");
                } catch (e) {}
            });
        }

        if (logoutModal) {
            logoutModal.addEventListener("click", function (e) {
                if (e.target === logoutModal) {
                    cerrarModalLogout();
                }
            });
        }

        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape" && logoutModal && logoutModal.classList.contains("show")) {
                cerrarModalLogout();
            }
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initSidebar);
    } else {
        initSidebar();
    }
})();
