/**
 * Requesty - Navegación Lateral (Sidebar) & Modal de Confirmación de Cierre de Sesión
 */
(function () {
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

        function setCollapsed(collapsed) {
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
        }

        function toggleSidebar() {
            const isCollapsed = sidebar.classList.contains("collapsed") || document.body.classList.contains("sidebar-collapsed");
            setCollapsed(!isCollapsed);
        }

        if (collapseBtn) {
            collapseBtn.addEventListener("click", (e) => {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        }

        if (topbarToggle) {
            topbarToggle.addEventListener("click", (e) => {
                e.preventDefault();
                e.stopPropagation();
                toggleSidebar();
            });
        }

        if (sidebarLogo) {
            sidebarLogo.addEventListener("click", () => {
                if (sidebar.classList.contains("collapsed")) setCollapsed(false);
            });
        }

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
            setTimeout(() => {
                logoutModal.style.display = "none";
                logoutModal.setAttribute("aria-hidden", "true");
                document.body.style.overflow = "";
            }, 200);
        }

        if (logoutBtn) {
            logoutBtn.addEventListener("click", (e) => {
                e.preventDefault();
                abrirModalLogout();
            });
        }

        document.querySelectorAll('[data-action="logout"], .btn-cerrar-sesion-js').forEach((btn) => {
            btn.addEventListener("click", (e) => {
                e.preventDefault();
                abrirModalLogout();
            });
        });

        if (cancelLogoutBtn) {
            cancelLogoutBtn.addEventListener("click", (e) => {
                e.preventDefault();
                cerrarModalLogout();
            });
        }

        if (confirmLogoutBtn) {
            confirmLogoutBtn.addEventListener("click", async (e) => {
                if (window.RequestyAPI) {
                    e.preventDefault();
                    try {
                        await window.RequestyAPI.post("api/logout", {});
                    } catch {
                        // Fallback to standard server logout route
                    }
                    window.location.href = "/logout";
                }
            });
        }

        if (logoutModal) {
            logoutModal.addEventListener("click", (e) => {
                if (e.target === logoutModal) cerrarModalLogout();
            });
        }

        document.addEventListener("keydown", (e) => {
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
