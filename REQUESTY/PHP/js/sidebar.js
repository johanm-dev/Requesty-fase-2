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

    function initModuleTransition() {
        const loader = document.getElementById("rqModuleLoader");
        if (!loader) return;

        const canvas = document.getElementById("rqLoaderLuciernagasCanvas");
        const ctx = canvas ? canvas.getContext("2d") : null;
        let ancho = window.innerWidth;
        let alto = window.innerHeight;
        let animFrameId = 0;

        const luciernagas = Array.from({ length: 34 }, function () {
            return {
                x: Math.random() * ancho,
                y: Math.random() * alto,
                radio: 2.2 + Math.random() * 3.4,
                angulo: Math.random() * Math.PI * 2,
                velocidad: 0.35 + Math.random() * 0.65,
                giro: (Math.random() - 0.5) * 0.025,
                faseBrillo: Math.random() * Math.PI * 2,
                velBrillo: 0.02 + Math.random() * 0.035
            };
        });

        function redimensionarCanvas() {
            if (!canvas) return;
            ancho = canvas.width = window.innerWidth;
            alto = canvas.height = window.innerHeight;
        }

        function animarLuciernagas() {
            if (!ctx || !loader.classList.contains("active")) {
                animFrameId = 0;
                return;
            }
            ctx.clearRect(0, 0, ancho, alto);

            for (let i = 0; i < luciernagas.length; i++) {
                const l = luciernagas[i];
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
                gradiente.addColorStop(0, "rgba(255, 250, 215, " + (0.95 * pulso) + ")");
                gradiente.addColorStop(0.3, "rgba(245, 185, 66, " + (0.75 * pulso) + ")");
                gradiente.addColorStop(0.65, "rgba(201, 155, 69, " + (0.32 * pulso) + ")");
                gradiente.addColorStop(1, "rgba(201, 155, 69, 0)");

                ctx.beginPath();
                ctx.fillStyle = gradiente;
                ctx.arc(l.x, l.y, haloRadio, 0, Math.PI * 2);
                ctx.fill();

                ctx.beginPath();
                ctx.fillStyle = "rgba(255, 252, 230, " + Math.min(1, pulso + 0.15) + ")";
                ctx.arc(l.x, l.y, l.radio * 0.65, 0, Math.PI * 2);
                ctx.fill();
            }

            animFrameId = window.requestAnimationFrame(animarLuciernagas);
        }

        function iniciarCanvasLuciernagas() {
            if (!ctx) return;
            redimensionarCanvas();
            if (!animFrameId) {
                animFrameId = window.requestAnimationFrame(animarLuciernagas);
            }
        }

        window.addEventListener("resize", redimensionarCanvas);

        let hideTimer = 0;

        function ocultarLoader() {
            loader.classList.remove("instant");
            void loader.offsetWidth;
            loader.classList.remove("active");
            loader.setAttribute("aria-hidden", "true");
        }

        function mostrarBrevemente(duracionMs) {
            if (hideTimer) window.clearTimeout(hideTimer);
            loader.classList.remove("instant");
            loader.classList.add("active");
            loader.setAttribute("aria-hidden", "false");
            iniciarCanvasLuciernagas();
            hideTimer = window.setTimeout(ocultarLoader, duracionMs || 520);
        }

        window.mostrarTransicionModulo = mostrarBrevemente;

        // Si venimos de hacer clic en otro módulo, mostrar brevemente al terminar de cargar y ocultar
        try {
            if (sessionStorage.getItem("rq_module_transition") === "1") {
                sessionStorage.removeItem("rq_module_transition");
                loader.classList.add("active");
                loader.setAttribute("aria-hidden", "false");
                iniciarCanvasLuciernagas();
                hideTimer = window.setTimeout(ocultarLoader, 420);
            }
        } catch (e) {
            ocultarLoader();
        }

        window.addEventListener("pageshow", function (event) {
            if (event.persisted) {
                try {
                    sessionStorage.removeItem("rq_module_transition");
                } catch (e) {}
                ocultarLoader();
            }
        });

        // Interceptar clics en enlaces de cambio de módulo (barra lateral y cabecera)
        const enlacesModulo = document.querySelectorAll(
            ".top-nav-menu .nav-btn, .sidebar-menu .sidebar-item, .top-nav-brand, .app-page-header-brand"
        );

        enlacesModulo.forEach(function (enlace) {
            enlace.addEventListener("click", function (e) {
                if (e.defaultPrevented) return;
                if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
                if (enlace.target && enlace.target !== "_self") return;
                if (enlace.classList.contains("btn-cerrar-sesion-js") || enlace.id === "btnSidebarLogout") return;

                const href = enlace.getAttribute("href");
                if (!href || href === "#" || href.startsWith("javascript:")) return;

                // Si ya estamos exactamente en la misma URL sin cambio, solo mostrar brevemente
                const destinoUrl = new URL(href, window.location.href);
                if (destinoUrl.href === window.location.href) {
                    e.preventDefault();
                    mostrarBrevemente(520);
                    return;
                }

                e.preventDefault();
                try {
                    sessionStorage.setItem("rq_module_transition", "1");
                } catch (err) {}

                loader.classList.remove("instant");
                loader.classList.add("active");
                loader.setAttribute("aria-hidden", "false");
                iniciarCanvasLuciernagas();

                window.setTimeout(function () {
                    window.location.href = destinoUrl.href;
                }, 380);
            });
        });
    }

    function initAll() {
        initNavWave();
        initNavToggle();
        initLogoutModal();
        initModuleTransition();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initAll);
    } else {
        initAll();
    }
})();
