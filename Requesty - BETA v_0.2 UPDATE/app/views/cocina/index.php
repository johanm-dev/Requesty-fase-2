<?php
$pageTitle = $pageTitle ?? 'Cocina - Requesty';
$currentPage = 'cocina';
$extraCss = ['css/cocina.css'];
require_once APPROOT . '/views/layouts/header.php';
?>
<div class="container">
    <?php require_once APPROOT . '/views/layouts/sidebar.php'; ?>

    <div class="main-content">
        <?php require_once APPROOT . '/views/layouts/topbar.php'; ?>

        <div class="content cocina-content">
            <div class="page-header cocina-page-header">
                <div>
                    <p class="catalogo-eyebrow">SISTEMA KDS · PANTALLA DE COCINA</p>
                    <h1 class="page-title">Comandas en Cocina</h1>
                </div>
                <div class="cocina-header-actions">
                    <button type="button" class="btn-cocina-secundario" id="btnRestaurarDemo" title="Cargar comandas de ejemplo">
                        <i class="fa-solid fa-receipt"></i> Cargar ejemplos
                    </button>
                </div>
            </div>

            <p id="mensaje-cocina" class="mensaje-cocina" role="alert" aria-live="assertive"></p>

            <section class="resumen cocina-resumen">
                <div class="tarjeta-resumen pendiente">
                    <span class="numero-resumen" id="contador-pendientes">0</span>
                    <span class="texto-resumen">En cola</span>
                </div>
                <div class="tarjeta-resumen preparacion">
                    <span class="numero-resumen" id="contador-preparacion">0</span>
                    <span class="texto-resumen">En preparación</span>
                </div>
                <div class="tarjeta-resumen listo">
                    <span class="numero-resumen" id="contador-listos">0</span>
                    <span class="texto-resumen">Listos hoy</span>
                </div>
            </section>

            <!-- Cuerda / Riel de comandas colgantes tipo libretas -->
            <div class="kds-riel-wrapper">
                <div class="cuerda-cocina">
                    <div class="cuerda-soporte cuerda-soporte-izq"></div>
                    <div class="cuerda-cable"></div>
                    <div class="cuerda-soporte cuerda-soporte-der"></div>
                </div>

                <div id="contenedor-pedidos" class="contenedor-libretas" aria-live="polite"></div>
            </div>

            <!-- Barra inferior con botones rápidos de comandas -->
            <div class="barra-inferior-comandas" id="barraInferiorComandas">
                <button type="button" class="btn-nav-riel" id="btnRielPrev" aria-label="Desplazar a la izquierda">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <div class="pildoras-comandas" id="pildorasComandas"></div>
                <button type="button" class="btn-nav-riel" id="btnRielNext" aria-label="Desplazar a la derecha">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset('js/auth.js') ?>"></script>
<script>protegerPagina("cocina");</script>
<script src="<?= asset('js/inventario.js') ?>"></script>
<script src="<?= asset('js/cocina.js') ?>"></script>
</body>
</html>
