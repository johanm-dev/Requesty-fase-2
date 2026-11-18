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
<aside class="sidebar">
    <div class="sidebar-logo">Requesty</div>
    <nav class="sidebar-menu">
        <a class="sidebar-item <?= ($currentPage === 'inicio') ? 'active' : '' ?>" href="<?= url('login') ?>">Inicio</a>
        <a class="sidebar-item <?= ($currentPage === 'pedidos') ? 'active' : '' ?>" href="<?= url('pedidos') ?>">Pedidos</a>
        <a class="sidebar-item <?= ($currentPage === 'cocina') ? 'active' : '' ?>" href="<?= url('cocina') ?>">Cocina</a>
        <a class="sidebar-item <?= ($currentPage === 'menu') ? 'active' : '' ?>" href="<?= url('menu') ?>">Menú</a>
        <a class="sidebar-item <?= ($currentPage === 'caja') ? 'active' : '' ?>" href="<?= url('caja') ?>">Caja y facturación</a>
        <a class="sidebar-item <?= ($currentPage === 'reportes') ? 'active' : '' ?>" href="<?= url('reportes') ?>">Reportes</a>
        <a class="sidebar-item <?= ($currentPage === 'mesas') ? 'active' : '' ?>" href="<?= url('mesas') ?>">Mesas</a>
        <a class="sidebar-item <?= ($currentPage === 'pqrs') ? 'active' : '' ?>" href="<?= url('pqrs') ?>">PQRS</a>
        <a class="sidebar-item" href="<?= url('logout') ?>">Cerrar sesión</a>
    </nav>
    <div class="sidebar-profile">
        <div class="profile-avatar"><?= htmlspecialchars($iniciales) ?></div>
        <div class="profile-info">
            <h4><?= htmlspecialchars($user['nombre']) ?></h4>
            <p><?= htmlspecialchars($user['rol']) ?></p>
        </div>
    </div>
</aside>
