<?php
$pageTitle = $pageTitle ?? 'Requesty - Caja y facturación';
$currentPage = 'caja';
$extraCss = ['css/caja.css'];
require_once APPROOT . '/views/layouts/header.php';
?>
<div class="container">
    <?php require_once APPROOT . '/views/layouts/sidebar.php'; ?>

    <div class="main-content">
        <?php require_once APPROOT . '/views/layouts/topbar.php'; ?>

        <main>
            <header>
                <h2>Caja y Facturación</h2>
                <nav>
                    <button class="active" onclick="switchTab('ventas')">🛒 Ventas y Cobro</button>
                    <button onclick="switchTab('facturas')">📄 Historial y facturas</button>
                    <button onclick="switchTab('cierre')">💰 Cierre de Caja</button>
                </nav>
            </header>

            <section id="vista-caja-cliente" class="vista-caja-cliente" hidden aria-labelledby="titulo-caja-cliente">
                <div class="card">
                    <h3 id="titulo-caja-cliente">Pedido entregado</h3>
                    <p>Este es un resumen de tus pedidos entregados que aún están pendientes de pago. Acércate a caja y presenta el número del pedido para pagarlo.</p>
                    <div id="pedidos-cliente-caja" aria-live="polite"></div>
                    <a class="btn-action cliente-volver-menu" href="<?= url('menu') ?>">Volver al menú y al historial</a>
                </div>
            </section>

            <!-- SECCIÓN 1: VENTAS Y COBRO -->
            <div id="ventas">
                <div class="grid">
                    <div class="card">
                        <h3>1. Cliente, mesa y pedido</h3>
                        <div class="form-group">
                            <label for="client-email">Correo del cliente:</label>
                            <input type="email" id="client-email" autocomplete="email" required placeholder="cliente@correo.com">
                        </div>

                        <div class="form-group">
                            <label for="client-name">Nombre del cliente:</label>
                            <input type="text" id="client-name" autocomplete="name" required placeholder="Nombre asignado a la mesa">
                        </div>

                        <div class="form-group">
                            <label for="mesa-venta">Mesa asignada:</label>
                            <input type="text" id="mesa-venta" readonly placeholder="Se selecciona al identificar al cliente">
                        </div>

                        <div class="form-group">
                            <label for="order-select">Pedido entregado pendiente de pago:</label>
                            <select id="order-select" disabled>
                                <option value="">Busca al cliente por nombre y correo</option>
                            </select>
                        </div>

                        <div id="mensaje-venta" class="mensaje-venta" aria-live="polite"></div>

                        <h4>Resumen del pedido</h4>
                        <div id="pedido-resumen" class="pedido-resumen"><p>Ingresa el nombre y el correo asociados a una mesa ocupada.</p></div>
                    </div>

                    <div class="card card-totales">
                        <div>
                            <h3>2. Totales y Pago</h3>
                            <p>Subtotal: <span id="txt-subtotal">$0</span></p>
                            <p>Impuestos registrados: <span id="txt-iva">$0</span></p>
                            <p>Propina: <span id="txt-propina">$0</span></p>
                            <h2 class="total-destacado">Total a Pagar: <span id="txt-total">$0</span></h2>

                            <div class="form-group dropdown-pago">
                                <label>Propina</label>
                                <input type="number" id="propina" value="0" min="0" step="1000" placeholder="0">
                            </div>

                            <div class="form-group dropdown-pago">
                                <label>Método de Cobro:</label>
                                <div class="select-wrapper">
                                    <select id="payment-method">
                                        <option value="Efectivo">Efectivo</option>
                                        <option value="Tarjeta">Tarjeta (Datáfono)</option>
                                        <option value="Transferencia">PSE / Nequi / Daviplata</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button class="btn-action btn-cobrar" onclick="procesarCobro()">GENERAR FACTURA Y REGISTRAR PAGO</button>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN 2: HISTORIAL Y DIAN -->
            <div id="facturas" style="display: none;">
                <div class="card">
                    <h3>Facturas electrónicas registradas y estado de validación</h3>
                    <table>
                        <thead>
                            <tr><th>No. factura</th><th>Pedido</th><th>Cliente</th><th>Mesa</th><th>Total</th><th>Método</th><th>Estado de factura</th><th>Detalle</th></tr>
                        </thead>
                        <tbody id="facturas-historial">
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- SECCIÓN 3: CIERRE DE CAJA -->
            <div id="cierre" style="display: none;">
                <div class="card cierre-card">
                    <h3>Cierre de Caja</h3>

                    <div class="resumen-cierre">
                        <div class="resumen-item">
                            <span>Efectivo del sistema</span>
                            <strong id="cierre-efectivo-sistema">$0</strong>
                        </div>
                        <div class="resumen-item">
                            <span>Pagos electrónicos</span>
                            <strong id="cierre-electronico-sistema">$0</strong>
                        </div>
                    </div>

                    <div class="form-group cierre-entrada">
                        <label>Efectivo real en caja</label>
                        <input type="text" id="efectivo-manual" placeholder="0" inputmode="numeric" pattern="[0-9]*">
                        <button class="btn-action" onclick="ejecutarCierreCaja()">Confirmar cierre</button>

                        <div class="resultado-cierre" id="resultado-cierre-box">
                            Debe ejecutar el cierre para ver el resultado.
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="<?= asset('js/auth.js') ?>"></script>
<script>protegerPagina("caja");</script>
<script src="<?= asset('js/caja.js') ?>"></script>
</body>
</html>
