@include('layouts.header', ['extraCss' => ['css/menu.css'], 'extraHeadScripts' => []])
<div class="container">
    @include('layouts.sidebar')

    <div class="main-content">
        @include('layouts.topbar')

        <div class="content">
            <div class="page-header">
                <div>
                    <p class="catalogo-eyebrow">AQUÍ ES · COCINA DE CASA</p>
                    <h1 class="page-title">Nuestro menú</h1>
                </div>
            </div>
            <section class="catalogo-controles" aria-label="Buscar y filtrar productos">
                <label class="catalogo-busqueda" for="buscar-menu">
                    <span>Buscar en el menú</span>
                    <input id="buscar-menu" type="search" placeholder="Plato o ingrediente">
                </label>
                <div id="filtros-menu" class="catalogo-filtros" aria-label="Categorías del menú"></div>
            </section>
            <p id="mensaje-menu-inventario" class="mensaje-menu-inventario" role="status" aria-live="polite"></p>
            <p id="mensaje-pedido" class="mensaje-menu-inventario" role="alert" aria-live="assertive"></p>
            <p id="bloqueo-cliente-mesa" class="bloqueo-cliente-mesa" role="status" hidden>Un mesero debe asignarte una mesa para consultar el menú.</p>
            <p class="catalogo-conteo" id="conteo-menu" aria-live="polite"></p>
            <section id="catalogo-menu" class="catalogo-productos" aria-live="polite"></section>
            <section id="seguimiento-pedidos" class="seguimiento-pedidos" aria-labelledby="titulo-seguimiento" hidden>
                <h2 id="titulo-seguimiento">Historial de mis pedidos</h2>
                <p id="mensaje-seguimiento-pedidos" role="status" aria-live="polite"></p>
                <div id="lista-seguimiento-pedidos"></div>
            </section>
        </div>
    </div>
</div>

<dialog id="detalle-producto" class="detalle-producto" aria-labelledby="nombre-detalle-producto">
    <div class="detalle-producto-contenido">
        <button id="cerrar-detalle-producto" class="detalle-producto-cerrar" type="button" aria-label="Cerrar detalle">×</button>
        <p id="categoria-detalle-producto" class="plato-categoria"></p>
        <h2 id="nombre-detalle-producto"></h2>
        <p id="precio-detalle-producto" class="plato-precio"></p>
        <p id="estado-detalle-producto" class="estado-disponibilidad" aria-live="polite"></p>
        <p><strong>Ingredientes</strong></p>
        <p id="ingredientes-detalle-producto"></p>
    </div>
</dialog>

<script src="{{ asset('js/auth.js') }}"></script>
<script src="{{ asset('js/inventario.js') }}"></script>
<script src="{{ asset('js/catalogo-cliente.js') }}"></script>
</body>
</html>
