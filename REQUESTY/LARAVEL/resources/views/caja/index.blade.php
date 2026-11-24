@include('layouts.header', ['extraCss' => ['css/caja.css'], 'extraHeadScripts' => []])
<div class="container">
    @include('layouts.sidebar')

    <div class="main-content">
        @include('layouts.topbar')

        <main>
            <header>
                <h2>Caja y Facturación</h2>
                <nav id="caja-tabs-nav">
                    <button type="button" data-tab="ventas">🛒 Ventas y Cobro</button>
                    <button type="button" data-tab="facturas">📄 Historial y facturas</button>
                    <button type="button" data-tab="cierre">💰 Turno y Cierre de Caja</button>
                </nav>
            </header>

            <section id="vista-caja-cliente" class="vista-caja-cliente" hidden aria-labelledby="titulo-caja-cliente">
                <div class="card">
                    <h3 id="titulo-caja-cliente">Pedido entregado</h3>
                    <p>Este es un resumen de tus pedidos entregados que aún están pendientes de pago. Acércate a caja y presenta el número del pedido para pagarlo.</p>
                    <div id="pedidos-cliente-caja" aria-live="polite"></div>
                    <a class="btn-action cliente-volver-menu" href="{{ url('/menu') }}">Volver al menú y al historial</a>
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
                            <p>Descuento aplicado: <span id="txt-descuento">$0</span></p>
                            <p>Impuestos registrados: <span id="txt-iva">$0</span></p>
                            <p>Propina: <span id="txt-propina">$0</span></p>
                            <h2 class="total-destacado">Total a Pagar: <span id="txt-total">$0</span></h2>

                            <div class="form-group dropdown-pago">
                                <label for="discount-select">Descuento autorizado (opcional)</label>
                                <div class="select-wrapper" style="display:flex; gap:8px;">
                                    <select id="discount-select">
                                        <option value="">Sin descuento</option>
                                    </select>
                                    <button type="button" id="btn-aplicar-descuento" class="btn-secondary">Aplicar</button>
                                </div>
                            </div>

                            <div class="form-group dropdown-pago">
                                <label for="propina">Propina</label>
                                <input type="number" id="propina" value="0" min="0" step="1000" placeholder="0">
                            </div>

                            <div class="form-group dropdown-pago">
                                <label for="payment-method">Método de Cobro:</label>
                                <div class="select-wrapper">
                                    <select id="payment-method">
                                        <option value="EFECTIVO">Efectivo</option>
                                        <option value="TARJETA_CREDITO">Tarjeta Crédito</option>
                                        <option value="TARJETA_DEBITO">Tarjeta Débito</option>
                                        <option value="TRANSFERENCIA">Transferencia PSE</option>
                                        <option value="NEQUI">Nequi</option>
                                        <option value="DAVIPLATA">Daviplata</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <button type="button" id="btn-procesar-cobro" class="btn-action btn-cobrar">GENERAR FACTURA Y REGISTRAR PAGO</button>
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

            <!-- SECCIÓN 3: TURNO Y CIERRE DE CAJA -->
            <div id="cierre" style="display: none;">
                <div class="card cierre-card">
                    <h3>Turno y Cierre de Caja</h3>
                    <p id="estado-turno-caja" aria-live="polite"></p>

                    <div id="panel-abrir-caja" class="form-group cierre-entrada" hidden>
                        <label for="monto-apertura">Monto base de apertura ($)</label>
                        <input type="number" id="monto-apertura" value="100000" min="0" step="1000">
                        <button type="button" id="btn-abrir-caja" class="btn-action">Abrir Turno de Caja</button>
                    </div>

                    <div id="panel-cerrar-caja">
                        <div class="resumen-cierre">
                            <div class="resumen-item">
                                <span>Monto apertura</span>
                                <strong id="cierre-apertura-sistema">$0</strong>
                            </div>
                            <div class="resumen-item">
                                <span>Efectivo esperado (apertura + ventas + propinas)</span>
                                <strong id="cierre-efectivo-sistema">$0</strong>
                            </div>
                            <div class="resumen-item">
                                <span>Pagos electrónicos</span>
                                <strong id="cierre-electronico-sistema">$0</strong>
                            </div>
                        </div>

                        <div class="form-group cierre-entrada">
                            <label for="efectivo-manual">Efectivo real contado en caja</label>
                            <input type="number" id="efectivo-manual" placeholder="0" min="0" step="100">
                            <button type="button" id="btn-confirmar-cierre" class="btn-action">Confirmar cierre</button>

                            <div class="resultado-cierre" id="resultado-cierre-box">
                                Debe ejecutar el cierre para ver el resultado.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script src="{{ asset('js/auth.js') }}"></script>
<script src="{{ asset('js/caja.js') }}"></script>
</body>
</html>
