/**
 * Requesty - Modal de Confirmación de Cerrar Sesión & Ola Fluida del Nav (Estilo Pinterest/Dock)
 */
(function () {
    function initNavWave() {
        const navMenu = document.getElementById("topNavMenu");
        const wavePill = document.getElementById("navWavePill");
        if (!navMenu || !wavePill) return;

        const navBtns = navMenu.querySelectorAll(".nav-btn");
        let pointerButton = null;
        let pendingPointerButton = null;
        let pointerChangeTimer = 0;
        let currentTop = null;
        let currentHeight = null;
        let lastFrameTime = 0;
        let waveFrame = 0;
        const transitioningLabels = new Set();
        const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        const pointerSettleDelay = 60;

        function getActiveButton() {
            return navMenu.querySelector(".nav-btn.active") || navBtns[0];
        }

        function getWaveTarget() {
            const target = pointerButton
                || navMenu.querySelector(".nav-btn:focus-visible")
                || getActiveButton();
            return target && navMenu.contains(target) ? target : getActiveButton();
        }

        function getWavePosition(targetBtn) {
            if (!targetBtn) {
                return null;
            }
            const menuRect = navMenu.getBoundingClientRect();
            const btnRect = targetBtn.getBoundingClientRect();
            return {
                top: btnRect.top - menuRect.top,
                height: btnRect.height
            };
        }

        function renderWave(top, height) {
            currentTop = top;
            currentHeight = height;
            wavePill.style.height = height + "px";
            wavePill.style.transform = "translate3d(0, " + top + "px, 0)";
            wavePill.style.opacity = "1";
        }

        function syncWaveCurrent(target, position) {
            const aligned = currentTop !== null
                && currentHeight !== null
                && Math.abs(position.top - currentTop) < 2
                && Math.abs(position.height - currentHeight) < 2;
            navBtns.forEach(function (btn) {
                btn.classList.toggle("is-wave-current", aligned && btn === target);
            });
        }

        function animateWave(timestamp) {
            waveFrame = 0;
            const target = getWaveTarget();
            const position = getWavePosition(target);
            if (!position) {
                wavePill.style.opacity = "0";
                return;
            }

            if (currentTop === null || currentHeight === null || reduceMotion) {
                renderWave(position.top, position.height);
            } else {
                const elapsed = lastFrameTime ? Math.min(timestamp - lastFrameTime, 32) : 16;
                const progress = 1 - Math.exp(-elapsed / 20);
                const top = currentTop + (position.top - currentTop) * progress;
                const height = currentHeight + (position.height - currentHeight) * progress;
                renderWave(top, height);
            }
            syncWaveCurrent(target, position);
            lastFrameTime = timestamp;

            const settled = Math.abs(position.top - currentTop) < 0.25
                && Math.abs(position.height - currentHeight) < 0.25;
            if (!settled || transitioningLabels.size > 0) {
                waveFrame = window.requestAnimationFrame(animateWave);
            } else {
                renderWave(position.top, position.height);
                syncWaveCurrent(target, position);
                lastFrameTime = 0;
            }
        }

        function startWaveAnimation() {
            if (!waveFrame) waveFrame = window.requestAnimationFrame(animateWave);
        }

        window.requestAnimationFrame(function () {
            const initialPosition = getWavePosition(getWaveTarget());
            if (initialPosition) renderWave(initialPosition.top, initialPosition.height);
            window.requestAnimationFrame(function () {
                startWaveAnimation();
            });
        });

        navBtns.forEach(function (btn) {
            btn.addEventListener("focus", startWaveAnimation);
            btn.addEventListener("blur", startWaveAnimation);
        });

        document.addEventListener("pointermove", function (event) {
            if (event.pointerType === "touch") return;

            const targetBtn = navMenu.contains(event.target)
                ? event.target.closest(".nav-btn")
                : null;
            if (targetBtn === pointerButton) {
                if (pointerChangeTimer) {
                    window.clearTimeout(pointerChangeTimer);
                    pointerChangeTimer = 0;
                    pendingPointerButton = null;
                }
                return;
            }
            if (targetBtn === pendingPointerButton && pointerChangeTimer) return;

            pendingPointerButton = targetBtn;
            if (pointerChangeTimer) window.clearTimeout(pointerChangeTimer);
            pointerChangeTimer = window.setTimeout(function () {
                pointerChangeTimer = 0;
                pointerButton = pendingPointerButton;
                pendingPointerButton = null;
                navBtns.forEach(function (btn) {
                    btn.classList.toggle("is-expanded", btn === pointerButton);
                });
                startWaveAnimation();
            }, pointerSettleDelay);
        }, { passive: true });

        navMenu.addEventListener("transitionrun", function (event) {
            if (event.target.matches(".nav-text") && event.propertyName === "max-width") {
                transitioningLabels.add(event.target);
                startWaveAnimation();
            }
        });

        function finishLabelTransition(event) {
            if (event.target.matches(".nav-text") && event.propertyName === "max-width") {
                transitioningLabels.delete(event.target);
                startWaveAnimation();
            }
        }
        navMenu.addEventListener("transitionend", finishLabelTransition);
        navMenu.addEventListener("transitioncancel", finishLabelTransition);

        window.addEventListener("blur", function () {
            if (pointerChangeTimer) window.clearTimeout(pointerChangeTimer);
            pointerChangeTimer = 0;
            pendingPointerButton = null;
            pointerButton = null;
            navBtns.forEach(function (btn) {
                btn.classList.remove("is-expanded");
            });
            startWaveAnimation();
        });

        window.addEventListener("resize", function () {
            startWaveAnimation();
        });
    }

    function initLogoutModal() {
        const logoutBtn = document.getElementById("btnTopbarLogout") || document.getElementById("btnSidebarLogout");
        const logoutModal = document.getElementById("modalLogoutConfirm");
        const cancelLogoutBtn = document.getElementById("btnCancelLogout");
        const confirmLogoutBtn = document.getElementById("btnConfirmLogout");

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

        document.querySelectorAll('[data-action="logout"], .btn-cerrar-sesion-js, #btnTopbarLogout, #btnSidebarLogout').forEach(function (btn) {
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

    function initNavToggle() {
        const toggle = document.getElementById("btnNavToggle");
        if (!toggle) return;

        const root = document.documentElement;

        function setExpanded(expanded, persist) {
            root.classList.toggle("nav-expanded", expanded);
            root.classList.toggle("sidebar-collapsed", !expanded);
            toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
            toggle.setAttribute("aria-label", expanded ? "Ocultar menú" : "Expandir menú");
            toggle.title = expanded ? "Ocultar menú" : "Expandir menú";
            toggle.querySelector(".nav-toggle-text").textContent = expanded ? "Ocultar menú" : "Expandir menú";

            if (persist) {
                try {
                    localStorage.setItem("rq_sidebar_collapsed", expanded ? "0" : "1");
                } catch (error) {
                    console.warn("No se pudo guardar la preferencia del menú lateral.", error);
                }
            }
        }

        setExpanded(root.classList.contains("nav-expanded"), false);
        toggle.addEventListener("click", function () {
            setExpanded(!root.classList.contains("nav-expanded"), true);
        });
    }

    function initAll() {
        initNavWave();
        initNavToggle();
        initLogoutModal();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initAll);
    } else {
        initAll();
    }
})();
