(function () {
    "use strict";

    var COOKIE_KEY = "rq_theme";
    var root = document.documentElement;

    function getCookie(name) {
        var match = document.cookie.match(new RegExp("(?:^|; )" + name + "=([^;]*)"));
        return match ? decodeURIComponent(match[1]) : null;
    }

    function setCookie(name, value) {
        document.cookie = name + "=" + encodeURIComponent(value) + "; path=/; max-age=31536000; SameSite=Strict";
    }

    function getSavedTheme() {
        var saved = getCookie(COOKIE_KEY);
        if (saved === "dark" || saved === "light") return saved;
        return "light";
    }

    function applyTheme(theme, persist) {
        var next = theme === "dark" ? "dark" : "light";
        root.setAttribute("data-theme", next);

        if (persist) {
            setCookie(COOKIE_KEY, next);
        }

        updateToggleButtons(next);
    }

    function updateToggleButtons(theme) {
        var isDark = theme === "dark";
        var buttons = document.querySelectorAll("[data-theme-toggle]");
        buttons.forEach(function (btn) {
            var icon = btn.querySelector(".theme-toggle-icon");
            var label = btn.querySelector(".theme-toggle-text");
            var actionText = isDark ? "Cambiar a modo claro" : "Cambiar a modo oscuro";

            btn.setAttribute("aria-pressed", isDark ? "true" : "false");
            btn.setAttribute("aria-label", actionText);
            btn.setAttribute("title", actionText);

            if (icon) {
                icon.className = "fa-solid " + (isDark ? "fa-sun" : "fa-moon") + " theme-toggle-icon";
            }
            if (label) {
                label.textContent = isDark ? "Modo claro" : "Modo oscuro";
            }
        });
    }

    function toggleTheme() {
        var current = root.getAttribute("data-theme") === "dark" ? "dark" : "light";
        applyTheme(current === "dark" ? "light" : "dark", true);
    }

    applyTheme(getSavedTheme(), false);

    function initThemeToggles() {
        updateToggleButtons(root.getAttribute("data-theme") || "light");
        document.addEventListener("click", function (event) {
            var btn = event.target.closest("[data-theme-toggle]");
            if (!btn) return;
            event.preventDefault();
            toggleTheme();
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", initThemeToggles);
    } else {
        initThemeToggles();
    }

    window.RequestyTheme = {
        apply: applyTheme,
        toggle: toggleTheme,
        current: function () {
            return root.getAttribute("data-theme") || "light";
        }
    };
})();
