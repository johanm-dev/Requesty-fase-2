@include('layouts.header', ['extraCss' => ['css/cocina.css'], 'extraHeadScripts' => []])
<div class="container">
    @include('layouts.sidebar')

    <div class="main-content">
        @include('layouts.topbar')

        <div class="content cocina-kds-page">
            <div class="page-header cocina-header-bar">
                <div class="cocina-title-block">
                    <h1 class="page-title">Comandas de Cocina</h1>
                </div>
                <div class="cocina-header-actions">
                    <p class="leyenda-alertas" aria-label="Alertas de tiempo">
                        <span class="leyenda-item leyenda-normal"><i class="fa-regular fa-clock"></i> &lt; 10 min</span>
                        <span class="leyenda-item leyenda-advertencia"><i class="fa-solid fa-fire-flame-Simple"></i> 10-19 min</span>
                        <span class="leyenda-item leyenda-critica"><i class="fa-solid fa-triangle-exclamation"></i> 20+ min (Prioridad)</span>
                    </p>
                </div>
            </div>

            <p id="mensaje-cocina" class="mensaje-cocina" role="alert" aria-live="assertive"></p>

            <!-- 3 Cajas icónicas simplificadas: En espera / En preparación / Listos -->
            <section class="resumen" aria-label="Resumen de comandas">
                <div class="tarjeta-resumen pendiente">
                    <div class="resumen-icono-box">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                    <div class="resumen-info-box">
                        <span class="texto-resumen">En espera</span>
                    </div>
                    <span class="numero-resumen" id="contador-pendientes">0</span>
                </div>
                <div class="tarjeta-resumen preparacion">
                    <div class="resumen-icono-box">
                        <i class="fa-solid fa-fire-burner"></i>
                    </div>
                    <div class="resumen-info-box">
                        <span class="texto-resumen">En preparación</span>
                    </div>
                    <span class="numero-resumen" id="contador-preparacion">0</span>
                </div>
                <div class="tarjeta-resumen listo">
                    <div class="resumen-icono-box">
                        <i class="fa-solid fa-bell-concierge"></i>
                    </div>
                    <div class="resumen-info-box">
                        <span class="texto-resumen">Listos</span>
                    </div>
                    <span class="numero-resumen" id="contador-listos">0</span>
                </div>
            </section>

            <!-- Filtro de mesas debajo de las 3 cajas -->
            <footer class="kds-dock-inferior" aria-label="Filtro rápido por mesas activas">
                <div class="kds-dock-label">
                    <i class="fa-solid fa-filter"></i>
                    <span>Filtrar por mesa:</span>
                </div>
                <div class="kds-dock-track" id="filtros-mesa-cocina"></div>
            </footer>

            <!-- Cuerda con libretas de comandas colgantes -->
            <div class="cuerda-escenario">
                <div class="cuerda-barra" aria-hidden="true">
                    <span class="cuerda-soporte izq"></span>
                    <div class="cuerda-cable"></div>
                    <span class="cuerda-soporte der"></span>
                </div>

                <div id="contenedor-pedidos" class="contenedor-pedidos cuerda-comandas" aria-live="polite"></div>
            </div>
        </div>
    </div>
</div>

<script src="{{ asset('js/auth.js') }}"></script>
<script src="{{ asset('js/inventario.js') }}"></script>
<script src="{{ asset('js/cocina.js') }}"></script>
</body>
</html>
