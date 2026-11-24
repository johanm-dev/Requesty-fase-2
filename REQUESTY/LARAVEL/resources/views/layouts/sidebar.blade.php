@php
    $currentPage = $currentPage ?? '';
    $user = $user ?? session('usuario', [
        'nombre' => 'Invitado',
        'rol' => '',
    ]);

    $partesNombre = explode(' ', trim((string) ($user['nombre'] ?? 'Invitado')));
    $iniciales = '';
    foreach (array_slice($partesNombre, 0, 2) as $p) {
        if (!empty($p)) {
            $iniciales .= mb_strtoupper(mb_substr($p, 0, 1));
        }
    }
    if ($iniciales === '') {
        $iniciales = 'RQ';
    }
@endphp
<aside class="sidebar" id="appSidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo" id="sidebarLogo" title="Requesty">
            <i class="fa-solid fa-utensils logo-icon"></i>
            <span class="logo-text">Requesty</span>
        </div>
        <button type="button" class="sidebar-toggle-btn" id="sidebarCollapseBtn" title="Plegar / Desplegar menú" aria-label="Alternar menú">
            <i class="fa-solid fa-chevron-left toggle-icon"></i>
        </button>
    </div>

    <nav class="sidebar-menu">
        <a class="sidebar-item {{ ($currentPage === 'inicio') ? 'active' : '' }}" href="{{ url('/pedidos') }}" title="Inicio" data-title="Inicio">
            <i class="fa-solid fa-house nav-icon"></i>
            <span class="nav-text">Inicio</span>
        </a>
        <a class="sidebar-item {{ ($currentPage === 'pedidos') ? 'active' : '' }}" href="{{ url('/pedidos') }}" title="Pedidos" data-title="Pedidos">
            <i class="fa-solid fa-clipboard-list nav-icon"></i>
            <span class="nav-text">Pedidos</span>
        </a>
        <a class="sidebar-item {{ ($currentPage === 'cocina') ? 'active' : '' }}" href="{{ url('/cocina') }}" title="Cocina" data-title="Cocina">
            <i class="fa-solid fa-fire-burner nav-icon"></i>
            <span class="nav-text">Cocina</span>
        </a>
        <a class="sidebar-item {{ ($currentPage === 'menu') ? 'active' : '' }}" href="{{ url('/menu') }}" title="Menú" data-title="Menú">
            <i class="fa-solid fa-book-open nav-icon"></i>
            <span class="nav-text">Menú</span>
        </a>
        <a class="sidebar-item {{ ($currentPage === 'caja') ? 'active' : '' }}" href="{{ url('/caja') }}" title="Caja y facturación" data-title="Caja y facturación">
            <i class="fa-solid fa-cash-register nav-icon"></i>
            <span class="nav-text">Caja y facturación</span>
        </a>
        <a class="sidebar-item {{ ($currentPage === 'reportes') ? 'active' : '' }}" href="{{ url('/reportes') }}" title="Reportes" data-title="Reportes">
            <i class="fa-solid fa-chart-line nav-icon"></i>
            <span class="nav-text">Reportes</span>
        </a>
        <a class="sidebar-item {{ ($currentPage === 'mesas') ? 'active' : '' }}" href="{{ url('/mesas') }}" title="Mesas" data-title="Mesas">
            <i class="fa-solid fa-chair nav-icon"></i>
            <span class="nav-text">Mesas</span>
        </a>
        <a class="sidebar-item {{ ($currentPage === 'pqrs') ? 'active' : '' }}" href="{{ url('/pqrs') }}" title="PQRS" data-title="PQRS">
            <i class="fa-solid fa-comments nav-icon"></i>
            <span class="nav-text">PQRS</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-profile" title="{{ (string) ($user['nombre'] ?? '') }} ({{ (string) ($user['rol'] ?? '') }})">
            <div class="profile-avatar">{{ $iniciales }}</div>
            <div class="profile-info">
                <h4>{{ (string) ($user['nombre'] ?? '') }}</h4>
                <p>{{ (string) ($user['rol'] ?? '') }}</p>
            </div>
        </div>
        <a class="sidebar-item sidebar-item-logout" id="btnSidebarLogout" href="javascript:void(0)" title="Cerrar sesión" data-title="Cerrar sesión">
            <i class="fa-solid fa-right-from-bracket nav-icon"></i>
            <span class="nav-text">Cerrar sesión</span>
        </a>
    </div>
</aside>

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
            <a href="{{ url('/logout') }}" class="rq-btn-danger" id="btnConfirmLogout">
                <i class="fa-solid fa-right-from-bracket"></i> Sí, cerrar sesión
            </a>
        </div>
    </div>
</div>

<script src="{{ asset('js/sidebar.js') }}"></script>
