<?php
$pageTitle = $pageTitle ?? 'Cocina - Requesty';
$currentPage = 'cocina';
$extraCss = ['css/cocina.css'];

// Comprobación de rol de usuario para permisos de admin en cocina
$rolUsuario = strtolower(trim($_SESSION['usuario']['rol'] ?? $_SESSION['user']['rol'] ?? $user['rol'] ?? ''));
$esAdmin = in_array($rolUsuario, ['admin', 'administrador']);

require_once APPROOT . '/views/layouts/header.php';
?>
<div class="container">
    <?php require_once APPROOT . '/views/layouts/sidebar.php'; ?>

    <div class="main-content">
        <?php require_once APPROOT . '/views/layouts/topbar.php'; ?>

        <div class="content cocina-content">
            <div class="page-header cocina-page-header">
                <div>
                    <h1 class="page-title">Comandas en Cocina</h1>
                </div>
                <?php if ($esAdmin): ?>
                <div class="cocina-header-actions">
                    <button type="button" class="btn-cocina-secundario" id="btnRestaurarDemo" title="Cargar comandas de ejemplo">
                        <i class="fa-solid fa-receipt"></i> Cargar ejemplos
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <p id="mensaje-cocina" class="mensaje-cocina" role="alert" aria-live="assertive"></p>

            <!-- 3 Cajas de Resumen KDS con diseño icónico -->
            <section class="resumen cocina-resumen" aria-label="Resumen de comandas">
                <div class="tarjeta-resumen kds-caja-iconica pendiente">
                    <div class="caja-icono-wrap icono-pendiente">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                    </div>
                    <div class="caja-info">
                        <span class="numero-resumen" id="contador-pendientes">0</span>
                        <span class="texto-resumen">En cola</span>
                    </div>
                    <div class="caja-badge-iconico">Pendientes</div>
                </div>
                <div class="tarjeta-resumen kds-caja-iconica preparacion">
                    <div class="caja-icono-wrap icono-preparacion">
                        <i class="fa-solid fa-fire-burner"></i>
                    </div>
                    <div class="caja-info">
                        <span class="numero-resumen" id="contador-preparacion">0</span>
                        <span class="texto-resumen">En preparación</span>
                    </div>
                    <div class="caja-badge-iconico">Al Fuego</div>
                </div>
                <div class="tarjeta-resumen kds-caja-iconica listo">
                    <div class="caja-icono-wrap icono-listo">
                        <i class="fa-solid fa-bell-concierge"></i>
                    </div>
                    <div class="caja-info">
                        <span class="numero-resumen" id="contador-listos">0</span>
                        <span class="texto-resumen">Listos hoy</span>
                    </div>
                    <div class="caja-badge-iconico">A Servir</div>
                </div>
            </section>

            <!-- Filtro de Mesas ubicado justo debajo de las 3 cajas -->
            <div class="cocina-filtro-mesas-wrapper" id="barraFiltroMesas">
                <div class="filtro-mesas-label">
                    <i class="fa-solid fa-filter"></i>
                    <span>Filtrar por mesa:</span>
                </div>
                <div class="filtro-mesas-lista" id="filtroMesasCocina">
                    <button type="button" class="btn-filtro-mesa active" data-mesa="todas">
                        <i class="fa-solid fa-layer-group"></i> Todas las mesas
                    </button>
                </div>
            </div>

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
