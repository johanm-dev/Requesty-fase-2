(function () {
    "use strict";

    var COOKIE_KEY = "rq_sidebar_collapsed";

    function getCookie(name) {
        var match = document.cookie.match(new RegExp("(?:^|; )" + name + "=([^;]*)"));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function setCookie(name, value) {
        document.cookie = name + "=" + encodeURIComponent(value) + "; path=/; max-age=31536000; SameSite=Strict";
    }

    (function applySavedSidebarState() {
        if (getCookie(COOKIE_KEY) === "0") {
            document.documentElement.classList.add("nav-expanded");
            document.documentElement.classList.remove("sidebar-collapsed");
        } else {
            document.documentElement.classList.add("sidebar-collapsed");
        }
    })();

    function initTopbarWave() {
        const navMenu = document.getElementById("topNavMenu");
        const wavePill = document.getElementById("navWavePill");
        if (!navMenu || !wavePill) return;

        const navBtns = Array.from(navMenu.querySelectorAll(".nav-btn"));
        let activeBtn = navMenu.querySelector(".nav-btn.active") || navBtns[0] || null;
        let pointerButton = null;
        let pendingPointerButton = null;
        let pointerChangeTimer = 0;
        let rafId = 0;
        const transitioningLabels = new Set();
        const pointerSettleDelay = 45;

        function getWaveTarget() {
            if (pointerButton) return pointerButton;
            const focusedBtn = navMenu.contains(document.activeElement)
                ? document.activeElement.closest(".nav-btn")
                : null;
            return focusedBtn || activeBtn || navBtns[0] || null;
        }

        function getWavePosition(targetBtn) {
            if (!targetBtn) return null;
            const menuRect = navMenu.getBoundingClientRect();
            const btnRect = targetBtn.getBoundingClientRect();
            if (!btnRect.height || !menuRect.height) return null;

            return {
                top: btnRect.top - menuRect.top,
                height: btnRect.height
            };
        }

        function renderWave(top, height) {
            wavePill.style.opacity = "1";
            wavePill.style.top = top.toFixed(2) + "px";
            wavePill.style.height = height.toFixed(2) + "px";
        }

        function tickWave() {
            rafId = 0;
            const target = getWaveTarget();
            if (!target) {
                wavePill.style.opacity = "0";
                return;
            }
            const next = getWavePosition(target);
            if (!next) return;

            renderWave(next.top, next.height);

            if (transitioningLabels.size > 0) {
                rafId = window.requestAnimationFrame(tickWave);
            }
        }

        function startWaveAnimation() {
            if (!rafId) {
                rafId = window.requestAnimationFrame(tickWave);
            }
        }

        const initialPosition = getWavePosition(getWaveTarget());
        if (initialPosition) {
            renderWave(initialPosition.top, initialPosition.height);
        } else {
            startWaveAnimation();
        }

        navBtns.forEach(function (btn) {
            btn.addEventListener("focus", startWaveAnimation);
            btn.addEventListener("blur", startWaveAnimation);
        });

        window.addEventListener("resize", startWaveAnimation);
        window.addEventListener("load", startWaveAnimation);
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
            const txt = toggle.querySelector(".nav-toggle-text");
            if (txt) txt.textContent = expanded ? "Ocultar menú" : "Expandir menú";

            if (persist) {
                setCookie(COOKIE_KEY, expanded ? "0" : "1");
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

        window.mostrarTransicionModulo = function (duracionMs) {
            const ms = typeof duracionMs === "number" ? duracionMs : 480;
            loader.classList.add("active");
            loader.setAttribute("aria-hidden", "false");
            window.setTimeout(function () {
                loader.classList.remove("active");
                loader.setAttribute("aria-hidden", "true");
            }, ms);
        };
    }

    function initLogoutConfirm() {
        const btnLogout = document.getElementById("btnTopbarLogout") || document.getElementById("btnSidebarLogout");
        const modal = document.getElementById("modalLogoutConfirm");
        const btnCancel = document.getElementById("btnCancelLogout");
        const btnConfirm = document.getElementById("btnConfirmLogout");

        if (!btnLogout || !modal) return;

        function openModal(e) {
            if (e) e.preventDefault();
            modal.style.display = "flex";
            modal.setAttribute("aria-hidden", "false");
            requestAnimationFrame(function () {
                modal.classList.add("active");
            });
            if (btnCancel) btnCancel.focus();
        }

        function closeModal() {
            modal.classList.remove("active");
            modal.setAttribute("aria-hidden", "true");
            setTimeout(function () {
                modal.style.display = "none";
            }, 220);
        }

        btnLogout.addEventListener("click", openModal);
        if (btnCancel) btnCancel.addEventListener("click", closeModal);
        modal.addEventListener("click", function (e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape" && modal.classList.contains("active")) {
                closeModal();
            }
        });
        if (btnConfirm) {
            btnConfirm.addEventListener("click", function (e) {
                e.preventDefault();
                window.location.href = btnConfirm.getAttribute("href") || "/logout";
            });
        }
    }

    document.addEventListener("DOMContentLoaded", function () {
        initNavToggle();
        initTopbarWave();
        initModuleTransition();
        initLogoutConfirm();
    });
})();
