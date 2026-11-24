// Utilitario de catálogo ligero (sin almacenamiento local)
(function () {
    window.RequestyMenuHelper = Object.freeze({
        async listar() {
            const res = await window.RequestyAPI.get("api/productos");
            return res?.productos || [];
        }
    });
})();
