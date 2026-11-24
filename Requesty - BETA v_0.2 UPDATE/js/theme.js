(function () {
    const storageKey = 'requestyTheme';
    const root = document.documentElement;

    function readTheme() {
        try {
            return localStorage.getItem(storageKey) === 'dark' ? 'dark' : 'light';
        } catch (error) {
            console.warn('No se pudo leer la preferencia del tema.', error);
            return 'light';
        }
    }

    function updateButtons(theme) {
        const oscuro = theme === 'dark';
        const accion = oscuro ? 'Cambiar a modo claro' : 'Cambiar a modo oscuro';
        const texto = oscuro ? 'Modo claro' : 'Modo oscuro';

        document.querySelectorAll('[data-theme-toggle]').forEach(button => {
            button.setAttribute('aria-label', accion);
            button.setAttribute('aria-pressed', String(oscuro));
            button.title = accion;

            const icon = button.querySelector('.theme-toggle-icon') || button.querySelector('i');
            if (icon) {
                if (oscuro) {
                    icon.classList.remove('fa-moon');
                    icon.classList.add('fa-sun');
                } else {
                    icon.classList.remove('fa-sun');
                    icon.classList.add('fa-moon');
                }
            }

            const textSpan = button.querySelector('.theme-toggle-text');
            if (textSpan) {
                textSpan.textContent = texto;
            }
        });
    }

    function aplicarTema(theme, guardar) {
        const temaValido = theme === 'dark' ? 'dark' : 'light';
        root.dataset.theme = temaValido;
        updateButtons(temaValido);

        if (guardar) {
            try {
                localStorage.setItem(storageKey, temaValido);
            } catch (error) {
                console.warn('No se pudo guardar la preferencia del tema.', error);
            }
        }

        document.dispatchEvent(new CustomEvent('requesty-theme-changed', {
            detail: { theme: temaValido }
        }));
    }

    function enlazarControles() {
        document.querySelectorAll('[data-theme-toggle]').forEach(button => {
            if (!button.dataset.themeBound) {
                button.dataset.themeBound = 'true';
                button.addEventListener('click', function (e) {
                    e.preventDefault();
                    aplicarTema(root.dataset.theme === 'dark' ? 'light' : 'dark', true);
                });
            }
        });

        window.addEventListener('storage', evento => {
            if (evento.key === storageKey) aplicarTema(evento.newValue, false);
        });
        updateButtons(root.dataset.theme || 'light');
    }

    aplicarTema(readTheme(), false);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', enlazarControles, { once: true });
    } else {
        enlazarControles();
    }
})();
