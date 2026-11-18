<?php
$pageTitle = $pageTitle ?? 'Requesty - Mesas';
$currentPage = 'mesas';
$extraCss = ['css/mesas.css'];
require_once APPROOT . '/views/layouts/header.php';
?>
<div class="container">
    <?php require_once APPROOT . '/views/layouts/sidebar.php'; ?>

    <div class="main-content">
        <?php require_once APPROOT . '/views/layouts/topbar.php'; ?>

        <div class="content">
            <div class="page-header">
                <h1 class="page-title">Mesas</h1>
            </div>

            <div class="mesas-controls">
                <div class="mesas-stats">
                    <div class="stat-card">
                        <span class="stat-label">Mesas Disponibles</span>
                        <span class="stat-value" id="mesasDisponibles">12</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Mesas Ocupadas</span>
                        <span class="stat-value ocupadas" id="mesasOcupadas">3</span>
                    </div>
                    <div class="stat-card">
                        <span class="stat-label">Ocupación</span>
                        <span class="stat-value" id="tasaOcupacion">20%</span>
                    </div>
                </div>

                <div class="mesas-filters">
                    <button class="filter-btn active" data-filter="all">Todas</button>
                    <button class="filter-btn" data-filter="libres">Libres</button>
                    <button class="filter-btn" data-filter="ocupadas">Ocupadas</button>
                </div>
            </div>

            <div class="mesas-grid" id="mesasGrid">
            </div>
        </div>
    </div>
</div>

<div class="modal" id="modalMesa">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Mesa 1</h2>
            <button class="modal-close" onclick="cerrarModal()">&times;</button>
        </div>

        <div id="tabInfo">
            <div class="modal-body">
                <div class="info-item">
                    <span class="info-label">Estado:</span>
                    <span class="info-value" id="modalEstado">Libre</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Capacidad:</span>
                    <span class="info-value" id="modalCapacidad">4 personas</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Clientes:</span>
                    <span class="info-value" id="modalClientes">0</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tiempo ocupada:</span>
                    <span class="info-value" id="modalTiempo">-</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Pedidos activos:</span>
                    <span class="info-value" id="modalPedidos">0</span>
                </div>
                <div class="info-item" id="asignarInputGroup" style="display:none; flex-direction:column; align-items:flex-start; gap:6px;">
                    <label for="inputClientes" class="info-label">Número de clientes:</label>
                    <input type="number" id="inputClientes" min="1" value="1" style="width:100%; padding:8px 10px; border:1.5px solid var(--color-accent); border-radius:6px; font-size:14px;">
                    <label for="inputNombreCliente" class="info-label">Nombre del cliente (opcional):</label>
                    <input type="text" id="inputNombreCliente" maxlength="100" autocomplete="name">
                    <label for="inputCorreoCliente" class="info-label">Correo del cliente (opcional):</label>
                    <input type="email" id="inputCorreoCliente" maxlength="254" autocomplete="email" required>
                    <span id="errorClientes" style="color:var(--color-danger); font-size:12px; display:none;"></span>
                </div>
                <div class="info-item" id="clienteAsignadoGroup" hidden>
                    <span class="info-label">Cliente asignado:</span>
                    <span class="info-value" id="modalClienteAsignado">-</span>
                </div>
            </div>
            <div class="modal-actions">
                <button class="btn-primary" id="btnAccion" onclick="accionMesa()">Asignar Mesa</button>
                <button class="btn-secondary" onclick="cerrarModal()">Cerrar</button>
            </div>
        </div>

    </div>
</div>

<div class="modal-overlay" id="modalOverlay" onclick="cerrarModal()"></div>

<script src="<?= asset('js/auth.js') ?>"></script>
<script>protegerPagina("mesas");</script>
<script src="<?= asset('js/mesas.js') ?>"></script>
</body>
</html>
