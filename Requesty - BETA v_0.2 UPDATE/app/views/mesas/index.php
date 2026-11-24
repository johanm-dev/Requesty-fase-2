<?php
$pageTitle = $pageTitle ?? 'Gestión de Mesas - Requesty';
$currentPage = 'mesas';
$extraCss = ['css/mesas.css'];
$mesasJson = json_encode(
    array_values($mesas ?? []),
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
);
require_once APPROOT . '/views/layouts/header.php';
?>
<div class="container">
    <?php require_once APPROOT . '/views/layouts/sidebar.php'; ?>

    <div class="main-content">
        <?php require_once APPROOT . '/views/layouts/topbar.php'; ?>

        <main class="content mesas-page">
            <header class="page-header">
                <h1 class="page-title">Mesas</h1>
            </header>

            <section class="mesas-controls" aria-label="Resumen de ocupación">
                <div class="mesas-stats">
                    <article class="stat-card stat-card-disponibles">
                        <span class="stat-label">Mesas Disponibles</span>
                        <strong class="stat-value" id="mesasDisponibles">0</strong>
                    </article>
                    <article class="stat-card stat-card-ocupadas">
                        <span class="stat-label">Mesas Ocupadas</span>
                        <strong class="stat-value" id="mesasOcupadas">0</strong>
                    </article>
                </div>

                <div class="mesas-filters" role="group" aria-label="Filtrar mesas">
                    <button class="filter-btn active" type="button" data-filter="all" aria-pressed="true">Todas</button>
                    <button class="filter-btn" type="button" data-filter="libres" aria-pressed="false">Libres</button>
                    <button class="filter-btn" type="button" data-filter="ocupadas" aria-pressed="false">Ocupadas</button>
                </div>
            </section>

            <p class="mesas-ocupacion-nota">
                <span>Ocupación de restaurante:</span>
                <strong id="tasaOcupacion">0%</strong>
            </p>

            <section class="mesas-plan" aria-labelledby="mesasPlanTitulo">
                <div class="mesas-plan-toolbar">
                    <div>
                        <h2 id="mesasPlanTitulo">Plano del restaurante</h2>
                        <p>La forma y los puestos de cada mesa se ajustan a su capacidad.</p>
                    </div>
                    <div class="mesas-plan-actions">
                        <?php if (!empty($puedeCrearMesas)): ?>
                            <button class="mesas-action-btn" type="button" id="btnAgregarMesa">Agregar</button>
                        <?php endif; ?>
                        <button class="mesas-action-btn" type="button" id="btnModoCombinar" aria-pressed="false">Combinar</button>
                        <button class="mesas-action-btn mesas-action-primary" type="button" id="btnConfirmarCombinacion" hidden disabled>Combinar</button>
                        <button class="mesas-action-btn mesas-action-secondary" type="button" id="btnCancelarCombinacion" hidden>Cancelar</button>
                    </div>
                </div>
                <p class="mesas-selection-hint" id="mesasSelectionHint" aria-live="polite" hidden>
                    Selecciona dos o más mesas disponibles.
                </p>
                <div class="mesas-grid" id="mesasGrid" aria-label="Plano de mesas"></div>
            </section>
        </main>
    </div>
</div>

<div class="modal-overlay" id="modalOverlay" hidden></div>
<section class="modal" id="modalMesa" role="dialog" aria-modal="true" aria-labelledby="modalTitle" hidden>
    <div class="mesa-modal-content">
        <header class="modal-header">
            <div class="mesa-modal-heading">
                <span>Gestión de mesa</span>
                <h2 id="modalTitle">Mesa</h2>
            </div>
            <button class="modal-close" type="button" id="btnCerrarModalMesa" aria-label="Cerrar">&times;</button>
        </header>
        <div class="modal-body">
            <div class="info-item">
                <label for="selectEstadoMesa" class="info-label">Estado de la mesa</label>
                <select id="selectEstadoMesa">
                    <option value="" disabled>Selecciona un estado</option>
                    <option value="libre">Disponible</option>
                    <option value="ocupada">Ocupada</option>
                    <option value="reservada">Reservada</option>
                    <option value="inactiva">Inactiva</option>
                </select>
            </div>
            <div class="info-item">
                <span class="info-label">Capacidad</span>
                <strong class="info-value" id="modalCapacidad">0 puestos</strong>
            </div>
            <div class="info-item">
                <span class="info-label">Clientes</span>
                <strong class="info-value" id="modalClientes">-</strong>
            </div>
            <div class="info-item">
                <span class="info-label">Tiempo ocupada</span>
                <strong class="info-value" id="modalTiempo">-</strong>
            </div>
            <div class="info-item">
                <span class="info-label">Pedidos activos</span>
                <strong class="info-value" id="modalPedidos">0</strong>
            </div>
            <div class="info-item" id="clienteAsignadoGroup" hidden>
                <span class="info-label">Cliente asignado</span>
                <strong class="info-value" id="modalClienteAsignado">-</strong>
            </div>
            <div class="info-item mesa-info-wide" id="fechaReservaActualGroup" hidden>
                <span class="info-label">Fecha de reserva</span>
                <strong class="info-value" id="fechaReservaActual">-</strong>
            </div>
            <div class="info-item mesa-asignacion" id="asignarInputGroup" hidden>
                <label for="inputClientes" class="info-label">Número de clientes</label>
                <input type="number" id="inputClientes" min="1" value="1" required>
                <label for="inputNombreCliente" class="info-label">Nombre del cliente (opcional)</label>
                <input type="text" id="inputNombreCliente" maxlength="100" autocomplete="name">
                <label for="inputCorreoCliente" class="info-label">Correo del cliente (opcional)</label>
                <input type="email" id="inputCorreoCliente" maxlength="254" autocomplete="email">
                <span class="form-error" id="errorClientes" role="alert" hidden></span>
            </div>
            <div class="info-item mesa-info-wide mesa-asignacion" id="reservaInputGroup" hidden>
                <label for="inputFechaReserva" class="info-label">Fecha y hora (HH:MM AM/PM DD/MM)</label>
                <input type="datetime-local" id="inputFechaReserva" step="60">
                <span class="form-error" id="errorReserva" role="alert" hidden></span>
            </div>
            <span class="form-error mesa-info-wide" id="errorEstadoMesa" role="alert" hidden></span>
        </div>
        <footer class="modal-actions">
            <button class="btn-primary" type="button" id="btnAccion">Asignar mesa</button>
            <button class="btn-secondary" type="button" id="btnCancelarModalMesa">Cerrar</button>
        </footer>
    </div>
</section>

<?php if (!empty($puedeCrearMesas)): ?>
    <div class="modal-overlay" id="modalNuevaMesaOverlay" hidden></div>
    <section class="modal modal-nueva-mesa" id="modalNuevaMesa" role="dialog" aria-modal="true" aria-labelledby="modalNuevaMesaTitulo" hidden>
        <form id="formNuevaMesa" novalidate>
            <header class="modal-header">
                <div class="mesa-modal-heading">
                    <span>Solo administrador</span>
                    <h2 id="modalNuevaMesaTitulo">Agregar mesa</h2>
                </div>
                <button class="modal-close" type="button" id="btnCerrarNuevaMesa" aria-label="Cerrar">&times;</button>
            </header>
            <div class="modal-body">
                <label for="capacidadNuevaMesa" class="info-label">Cantidad de puestos (1–12)</label>
                <input type="number" id="capacidadNuevaMesa" min="1" max="12" value="4" required>
                <span class="form-error" id="errorNuevaMesa" role="alert" hidden></span>
            </div>
            <footer class="modal-actions">
                <button class="btn-primary" type="submit" id="btnGuardarNuevaMesa">Guardar mesa</button>
                <button class="btn-secondary" type="button" id="btnCancelarNuevaMesa">Cancelar</button>
            </footer>
        </form>
    </section>
<?php endif; ?>

<ul class="toast-list" id="toastList" aria-label="Notificaciones" aria-live="polite"></ul>

<script>
    window.requestyMesasIniciales = <?= $mesasJson !== false ? $mesasJson : '[]' ?>;
    window.requestyPuedeCrearMesa = <?= !empty($puedeCrearMesas) ? 'true' : 'false' ?>;
    window.requestyMesaApiUrl = <?= json_encode(url('api/mesas'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    window.requestyCrearMesaApiUrl = <?= json_encode(url('api/mesas/crear'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
</script>
<script src="<?= asset('js/auth.js') ?>"></script>
<script>protegerPagina("mesas");</script>
<script src="<?= asset('js/mesas.js') ?>"></script>
</body>
</html>
