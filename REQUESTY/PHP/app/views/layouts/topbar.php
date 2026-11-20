<?php
$currentPage = $currentPage ?? '';
$user = $_SESSION['usuario'] ?? [
    'nombre' => 'Juan Pérez',
    'rol'    => 'Administrador'
];

// Generar iniciales para avatar
$partesNombre = explode(' ', trim($user['nombre']));
$iniciales = '';
foreach (array_slice($partesNombre, 0, 2) as $p) {
    if (!empty($p)) $iniciales .= mb_strtoupper(mb_substr($p, 0, 1));
}
if (empty($iniciales)) $iniciales = 'RQ';
?>
<aside class="top-bar-nav" id="appTopNavbar" aria-label="Navegación principal">
    <a href="<?= url('pedidos') ?>" class="top-nav-brand" title="Requesty" aria-label="Requesty">
        <span class="brand-badge"><i class="fa-solid fa-utensils brand-icon" aria-hidden="true"></i></span>
        <span class="brand-text">Requesty</span>
    </a>

    <button type="button" class="nav-toggle" id="btnNavToggle"
            aria-label="Expandir menú" aria-expanded="false" aria-controls="topNavMenu"
            title="Expandir menú">
        <i class="fa-solid fa-angles-right" aria-hidden="true"></i>
        <span class="nav-toggle-text">Expandir menú</span>
    </button>

    <nav class="top-nav-menu" id="topNavMenu" aria-label="Secciones">
        <!-- Indicador de navegación animado -->
        <div class="nav-wave-pill" id="navWavePill"></div>

        <a class="nav-btn <?= ($currentPage === 'inicio') ? 'active' : '' ?>" href="<?= url('pedidos') ?>" title="Inicio" data-title="Inicio">
            <i class="fa-solid fa-house nav-icon"></i>
            <span class="nav-text">Inicio</span>
        </a>
        <a class="nav-btn <?= ($currentPage === 'pedidos') ? 'active' : '' ?>" href="<?= url('pedidos') ?>" title="Pedidos" data-title="Pedidos">
            <i class="fa-solid fa-clipboard-list nav-icon"></i>
            <span class="nav-text">Pedidos</span>
        </a>
        <a class="nav-btn <?= ($currentPage === 'cocina') ? 'active' : '' ?>" href="<?= url('cocina') ?>" title="Cocina" data-title="Cocina">
            <i class="fa-solid fa-fire-burner nav-icon"></i>
            <span class="nav-text">Cocina</span>
        </a>
        <a class="nav-btn <?= ($currentPage === 'menu') ? 'active' : '' ?>" href="<?= url('menu') ?>" title="Menú" data-title="Menú">
            <i class="fa-solid fa-book-open nav-icon"></i>
            <span class="nav-text">Menú</span>
        </a>
        <a class="nav-btn <?= ($currentPage === 'caja') ? 'active' : '' ?>" href="<?= url('caja') ?>" title="Caja y facturación" data-title="Caja y facturación">
            <i class="fa-solid fa-cash-register nav-icon"></i>
            <span class="nav-text">Caja y facturación</span>
        </a>
        <a class="nav-btn <?= ($currentPage === 'reportes') ? 'active' : '' ?>" href="<?= url('reportes') ?>" title="Reportes" data-title="Reportes">
            <i class="fa-solid fa-chart-line nav-icon"></i>
            <span class="nav-text">Reportes</span>
        </a>
        <a class="nav-btn <?= ($currentPage === 'mesas') ? 'active' : '' ?>" href="<?= url('mesas') ?>" title="Mesas" data-title="Mesas">
            <i class="fa-solid fa-chair nav-icon"></i>
            <span class="nav-text">Mesas</span>
        </a>
        <a class="nav-btn <?= ($currentPage === 'pqrs') ? 'active' : '' ?>" href="<?= url('pqrs') ?>" title="PQRS" data-title="PQRS">
            <i class="fa-solid fa-comments nav-icon"></i>
            <span class="nav-text">PQRS</span>
        </a>
    </nav>

    <div class="top-nav-footer">
        <div class="user-profile-badge top-nav-profile" tabindex="0" role="img"
             aria-label="<?= htmlspecialchars($user['nombre'] . ', ' . $user['rol'], ENT_QUOTES, 'UTF-8') ?>"
             data-tooltip="<?= htmlspecialchars($user['nombre'] . ' · ' . $user['rol'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="profile-avatar" aria-hidden="true"><?= htmlspecialchars($iniciales) ?></div>
            <span class="top-nav-profile-name" aria-hidden="true"><?= htmlspecialchars($user['nombre'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="top-nav-footer-divider" aria-hidden="true"></div>
        <button type="button" class="btn-topbar-logout" id="btnTopbarLogout" title="Cerrar sesión" aria-label="Cerrar sesión">
            <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
            <span class="top-nav-logout-text" aria-hidden="true">Cerrar sesión</span>
        </button>
    </div>
</aside>

<header class="app-page-header">
    <a href="<?= url('pedidos') ?>" class="app-page-header-brand">Requesty</a>
</header>

<!-- Modal de Confirmación de Cierre de Sesión -->
<div id="modalLogoutConfirm" class="rq-modal-overlay" style="display: none;" aria-hidden="true" role="dialog" aria-labelledby="modalLogoutTitle">
    <div class="rq-modal-card">
        <div class="rq-modal-icon-danger">
            <i class="fa-solid fa-right-from-bracket"></i>
        </div>
        <h3 class="rq-modal-title" id="modalLogoutTitle">¿Cerrar sesión?</h3>
        <p class="rq-modal-message">¿Estás seguro de que deseas salir del sistema <strong>Requesty</strong>?</p>
        <div class="rq-modal-actions">
            <button type="button" class="rq-btn-secondary" id="btnCancelLogout">Cancelar</button>
            <a href="<?= url('logout') ?>" class="rq-btn-danger" id="btnConfirmLogout">
                <i class="fa-solid fa-right-from-bracket"></i> Sí, cerrar sesión
            </a>
        </div>
    </div>
</div>

<!-- Pantalla breve de transición al cambiar de módulo -->
<div id="rqModuleLoader" class="rq-module-loader" aria-hidden="true">
    <div class="rq-loader-luciernagas" aria-hidden="true">
        <canvas id="rqLoaderLuciernagasCanvas"></canvas>
        <span class="rq-loader-luciernaga"></span>
        <span class="rq-loader-luciernaga"></span>
        <span class="rq-loader-luciernaga"></span>
        <span class="rq-loader-luciernaga"></span>
        <span class="rq-loader-luciernaga"></span>
        <span class="rq-loader-luciernaga"></span>
        <span class="rq-loader-luciernaga"></span>
        <span class="rq-loader-luciernaga"></span>
        <span class="rq-loader-luciernaga"></span>
        <span class="rq-loader-luciernaga"></span>
        <span class="rq-loader-luciernaga"></span>
        <span class="rq-loader-luciernaga"></span>
        <span class="rq-loader-luciernaga"></span>
        <span class="rq-loader-luciernaga"></span>
        <span class="rq-loader-luciernaga"></span>
        <span class="rq-loader-luciernaga"></span>
        <span class="rq-loader-luciernaga"></span>
        <span class="rq-loader-luciernaga"></span>
    </div>
    <div class="rq-module-loader-card">
        <img src="<?= asset('img/mascota2.gif') ?>" alt="Cambiando de módulo..." class="rq-module-loader-gif">
    </div>
</div>
<script>
    (function () {
        try {
            if (sessionStorage.getItem('rq_module_transition') === '1') {
                var loader = document.getElementById('rqModuleLoader');
                if (loader) {
                    loader.classList.add('active', 'instant');
                }
            }
        } catch (e) {}
    })();
</script>

<script src="<?= asset('js/sidebar.js') ?>"></script>
