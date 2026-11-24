@include('layouts.header', ['extraCss' => ['css/cocina.css'], 'extraHeadScripts' => []])
<div class="container">
    @include('layouts.sidebar')

    <div class="main-content">
        @include('layouts.topbar')

        <div class="content">
            <div class="page-header">
                <h1 class="page-title">Cocina</h1>
            </div>

            <p id="mensaje-cocina" class="mensaje-cocina" role="alert" aria-live="assertive"></p>
            <p class="leyenda-alertas" aria-label="Alertas de tiempo">
                <span class="leyenda-normal">Menos de 10 min</span>
                <span class="leyenda-advertencia">10 min o más</span>
                <span class="leyenda-critica">20 min o más</span>
            </p>
            <section class="resumen">
                <div class="tarjeta-resumen pendiente">
                    <span class="numero-resumen" id="contador-pendientes">0</span>
                    <span class="texto-resumen">Pendientes</span>
                </div>
                <div class="tarjeta-resumen preparacion">
                    <span class="numero-resumen" id="contador-preparacion">0</span>
                    <span class="texto-resumen">En preparación</span>
                </div>
                <div class="tarjeta-resumen listo">
                    <span class="numero-resumen" id="contador-listos">0</span>
                    <span class="texto-resumen">Listos</span>
                </div>
            </section>

            <div id="contenedor-pedidos" class="contenedor-pedidos" aria-live="polite"></div>
        </div>
    </div>
</div>

<script src="{{ asset('js/auth.js') }}"></script>
<script src="{{ asset('js/inventario.js') }}"></script>
<script src="{{ asset('js/cocina.js') }}"></script>
</body>
</html>
