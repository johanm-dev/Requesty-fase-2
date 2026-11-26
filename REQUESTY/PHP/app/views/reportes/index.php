<?php
$pageTitle = $pageTitle ?? 'Requesty - Reportes';
$currentPage = 'reportes';
$extraCss = ['css/reportes.css', 'css/cuentas.css'];
$extraHead = '<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>';
require_once APPROOT . '/views/layouts/header.php';
?>
<div class="container">
    <?php require_once APPROOT . '/views/layouts/sidebar.php'; ?>

    <div class="main-content">
        <?php require_once APPROOT . '/views/layouts/topbar.php'; ?>

        <div class="content">

            <!-- ========== VISTA 1: REPORTES ========== -->
            <div id="vista-reportes">
                <div class="page-header">
                    <h1 class="page-title">Reportes</h1>
                </div>

                <div class="filters-section">
                    <label class="filter-label">Rango de fechas</label>
                    <input type="date" id="startDate" class="date-input">
                    <input type="date" id="endDate" class="date-input">
                    <div class="filters-actions">
                        <button class="generate-btn" onclick="generateReport()">Generar reporte</button>
                        <button class="pqrs-btn" id="btn-ver-pqrs" type="button">Ver PQRS</button>
                        <button class="menu-btn" id="btn-administrar-menu" type="button" data-admin-only hidden>Administrar menú</button>
                        <button class="menu-btn" id="btn-administrar-inventario" type="button" data-admin-only hidden>Gestionar inventario</button>
                        <button class="menu-btn" id="btn-ver-cuentas" type="button" data-admin-only hidden>Cuentas del personal</button>
                        <button class="menu-btn btn-reiniciar-procesos" id="btn-reiniciar-procesos" type="button" data-admin-only hidden>Reiniciar procesos</button>
                    </div>
                </div>

                <div class="kpi-container">
                    <div class="kpi-card">
                        <div class="kpi-label">Ventas</div>
                        <div class="kpi-value" id="kpiVentas">$0</div>
                        <div class="kpi-change">Ventas facturadas en el periodo</div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-label">Pedidos</div>
                        <div class="kpi-value" id="kpiPedidos">0</div>
                        <div class="kpi-change">Pedidos cobrados en el periodo</div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-label">Productos más vendidos</div>
                        <div id="productos-mas-vendidos" class="kpi-list"><div class="kpi-list-item">Sin ventas registradas</div></div>
                    </div>

                    <div class="kpi-card">
                        <div class="kpi-label">Horas pico</div>
                        <div id="horas-pico-lista" class="kpi-list"><div class="kpi-list-item">Sin ventas registradas</div></div>
                    </div>
                </div>

                <div class="chart-section">
                    <div class="chart-header">
                        <h2 class="chart-title">Gráfica de ventas</h2>
                        <select class="chart-range-select" id="chartRange" onchange="updateChart()">
                            <option value="7">Últimos 7 días</option>
                            <option value="14">Últimos 14 días</option>
                            <option value="30">Últimos 30 días</option>
                        </select>
                    </div>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
                <section class="chart-section ventas-pedidos" aria-labelledby="titulo-ventas-pedidos">
                    <h2 id="titulo-ventas-pedidos">Ventas por pedido y facturas</h2>
                    <div class="tabla-inventario-scroll">
                        <table class="tabla-inventario">
                            <thead><tr><th>Factura</th><th>ID pedido</th><th>Mesero</th><th>Método de pago</th><th>Total</th><th>Detalle</th></tr></thead>
                            <tbody id="reporte-facturas"></tbody>
                        </table>
                    </div>
                </section>
            </div>

            <!-- ========== VISTA 2: ADMINISTRAR MENÚ ========== -->
            <div id="vista-menu" class="catalogo oculto">
                <div class="page-header encabezado-menu">
                    <h1 class="page-title">Administrar menú</h1>
                    <div class="acciones-encabezado-menu">
                        <button type="button" id="guardar-imagenes-carpeta" class="boton-secundario">Guardar imágenes en img/tarjeta</button>
                        <button type="button" id="btn-volver-reportes" class="boton-secundario">Volver a reportes</button>
                    </div>
                </div>

                <section>
                    <h2>Administrar catálogo</h2>
                    <form id="formulario-producto" class="panel">
                        <div class="formulario">
                            <div class="campo"><label for="nombre">Nombre del plato</label><input id="nombre" required placeholder="Ej. Pasta carbonara"></div>
                            <div class="campo"><label for="precio">Precio</label><input id="precio" type="number" min="0" step="1" placeholder="15000"></div>
                            <div class="campo"><label for="categoria">Categoría</label><select id="categoria" required></select></div>
                            <div class="campo campo-imagen"><label for="imagen-producto">Enlace directo de imagen</label><input id="imagen-producto" type="url" placeholder="https://sitio.com/imagen.jpg" autocomplete="url"><small>Usa el enlace al archivo JPG, PNG o WebP, no el enlace a una publicación o página.</small></div>
                            <div id="previsualizacion-imagen" class="previsualizacion-imagen" hidden>
                                <img id="vista-previa-imagen" alt="Vista previa de la imagen del plato" hidden>
                                <p id="estado-imagen" aria-live="polite"></p>
                            </div>
                            <label class="campo-opcion-imagen"><input id="imagen-activa" type="checkbox" checked> Mostrar imagen en el menú</label>
                            <div class="campo campo-ingredientes"><label for="ingredientes">Ingredientes (separados por coma)</label><textarea id="ingredientes" required placeholder="Pasta, salsa, queso parmesano"></textarea></div>
                            <div class="campo campo-ingredientes"><label for="receta">Cantidad por plato de cada ingrediente</label><textarea id="receta" required placeholder="Pasta: 150, salsa: 50"></textarea><small>Registra cada cantidad por porción en gramos, mililitros o unidades. El stock del inventario debe usar la misma unidad.</small></div>
                            <div class="acciones-formulario"><button type="submit" id="guardar-producto">Agregar producto</button><button type="button" id="cancelar-edicion" class="boton-secundario oculto">Cancelar edición</button></div>
                        </div>
                        <p id="mensaje-menu" class="mensaje-menu" aria-live="polite"></p>
                        <p id="mensaje-menu-stock" class="mensaje-menu" aria-live="polite"></p>
                    </form>
                </section>

                <section>
                    <h2>Menú</h2>
                    <div class="herramientas-menu">
                        <label class="busqueda" for="buscar-producto">
                            <span>Buscar producto</span>
                            <input id="buscar-producto" type="search" placeholder="Busca por nombre o ingrediente...">
                        </label>
                        <p id="contador-productos" class="contador-productos"></p>
                    </div>
                    <div id="filtros" class="filtros" aria-label="Filtrar por categoría"></div>
                    <div id="catalogo"></div>
                </section>

            </div>

            <!-- ========== VISTA 3: INVENTARIO ========== -->
            <section id="vista-inventario" class="oculto">
                <div class="page-header encabezado-menu">
                    <h1 class="page-title">Control de existencias e insumos</h1>
                    <button type="button" id="btn-volver-inventario" class="boton-secundario">Volver a reportes</button>
                </div>
                <p class="descripcion-inventario">El inventario predeterminado cubre 45 porciones de cada plato y fija mínimos para 15 porciones, sumando los insumos compartidos. Los sólidos se registran en gramos (g), los líquidos en litros (L) y las piezas en unidades. Para el jugo, el pedido permite elegir agua o leche.</p>
                <form id="formulario-inventario" class="panel formulario-inventario">
                    <div class="campo"><label for="nombre-insumo">Nombre del insumo</label><input id="nombre-insumo" required maxlength="100" placeholder="Ej. Arroz"></div>
                    <div class="campo"><label for="unidad-insumo">Unidad de medida</label><select id="unidad-insumo" required><option value="g">Gramos (g)</option><option value="L">Litros (L)</option><option value="unidad">Unidad</option></select></div>
                    <div class="campo"><label for="stock-insumo">Cantidad disponible</label><input id="stock-insumo" type="number" min="0" step="0.001" required value="45"></div>
                    <div class="campo"><label for="minimo-insumo">Nivel mínimo</label><input id="minimo-insumo" type="number" min="0" step="0.001" required value="15"></div>
                    <div class="acciones-formulario">
                        <button type="submit" id="guardar-insumo">Registrar insumo</button>
                        <button type="button" id="cancelar-edicion-insumo" class="boton-secundario" hidden>Cancelar</button>
                    </div>
                </form>
                <p id="mensaje-inventario" class="mensaje-menu" aria-live="polite"></p>
                <section class="tabla-inventario-seccion" aria-labelledby="titulo-tabla-inventario">
                    <h2 id="titulo-tabla-inventario">Inventario actual</h2>
                    <div class="tabla-inventario-scroll">
                        <table class="tabla-inventario">
                            <thead><tr><th>Insumo</th><th>Stock</th><th>Nivel mínimo</th><th>Estado</th><th>Consumo por porción</th><th>Platos relacionados</th><th>Acciones</th></tr></thead>
                            <tbody id="tabla-inventario"></tbody>
                        </table>
                    </div>
                </section>
            </section>

            <!-- ========== VISTA 4: REGISTRO DE PQRS ========== -->
            <div id="vista-pqrs" class="pqrs-section oculto">
                <div class="page-header list-header">
                    <h1 class="page-title">PQRS registrados</h1>
                    <button type="button" id="btn-volver-reportes-pqrs" class="boton-secundario">Volver a reportes</button>
                </div>

                <section class="card list-card">
                    <div class="list-header">
                        <div class="filters">
                            <input type="text" id="buscar" placeholder="Buscar por cliente o descripción...">

                            <select id="filtro-tipo">
                                <option value="">Todos los tipos</option>
                                <option value="Petición">Petición</option>
                                <option value="Queja">Queja</option>
                                <option value="Reclamo">Reclamo</option>
                                <option value="Sugerencia">Sugerencia</option>
                                <option value="Felicitación">Felicitación</option>
                            </select>

                            <select id="filtro-estado">
                                <option value="">Todos los estados</option>
                                <option value="Pendiente">Pendiente</option>
                                <option value="En revisión">En revisión</option>
                                <option value="Resuelta">Resuelta</option>
                                <option value="Cerrada">Cerrada</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-wrapper">
                        <table class="pqrs-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Tipo</th>
                                    <th>Cliente</th>
                                    <th>Pedido</th>
                                    <th>Descripción</th>
                                    <th>Fecha</th>
                                    <th>Estado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="tabla-pqrs">
                                <!-- Filas dinámicas -->
                            </tbody>
                        </table>

                        <p id="mensaje-vacio" class="mensaje-vacio" style="display:none;">
                            No hay PQRS que coincidan con los filtros.
                        </p>
                    </div>
                </section>
            </div>

            <!-- ========== VISTA 5: CUENTAS DEL PERSONAL ========== -->
            <section id="vista-cuentas" class="oculto cuentas-content">
                <header class="page-header cuentas-header">
                    <div>
                        <p class="eyebrow">ADMINISTRACIÓN</p>
                        <h1 class="page-title">Cuentas del personal</h1>
                    </div>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button class="boton-secundario" id="btn-volver-cuentas-top" type="button">Volver a reportes</button>
                        <button class="btn-primary" id="nuevaCuenta" type="button">＋ Nueva cuenta</button>
                    </div>
                </header>

                <section class="resumen-cuentas" aria-label="Resumen de cuentas">
                    <div><span>Total de cuentas</span><strong id="totalCuentas">0</strong></div>
                    <div><span>Activas</span><strong id="cuentasActivas">0</strong></div>
                    <div><span>Inactivas</span><strong id="cuentasInactivas">0</strong></div>
                </section>

                <section class="tabla-seccion" aria-labelledby="tituloTabla">
                    <div class="tabla-header">
                        <h2 id="tituloTabla">Personal</h2>
                        <label class="busqueda-cuentas"><span>Buscar</span><input id="buscarCuenta" type="search" placeholder="Nombre o correo"></label>
                    </div>
                    <div class="tabla-scroll">
                        <table>
                            <thead><tr><th>Nombre</th><th>Correo</th><th>Rol</th><th>Estado</th><th>Permisos</th><th>Acciones</th></tr></thead>
                            <tbody id="listaCuentas"></tbody>
                        </table>
                        <p id="sinCuentas" class="sin-cuentas" hidden>No hay cuentas que coincidan con la búsqueda.</p>
                    </div>
                </section>
                <button type="button" id="volver-reportes-cuentas" class="btn-secundario">Volver a reportes</button>
            </section>

        </div>
    </div>
</div>

<!-- MODAL: ver / actualizar estado PQRS -->
<div id="modal-detalle" class="modal-overlay">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Detalle del PQRS <span id="modal-id"></span></h3>
            <button type="button" id="modal-cerrar" class="modal-close">&times;</button>
        </div>

        <div class="modal-body">
            <p><strong>Tipo:</strong> <span id="modal-tipo"></span></p>
            <p><strong>Cliente:</strong> <span id="modal-cliente"></span></p>
            <p><strong>Pedido:</strong> <span id="modal-pedido"></span></p>
            <p><strong>Fecha:</strong> <span id="modal-fecha"></span></p>
            <p><strong>Descripción:</strong></p>
            <p id="modal-descripcion" class="modal-descripcion"></p>

            <div class="form-group">
                <label for="modal-estado">Actualizar estado</label>
                <select id="modal-estado">
                    <option value="Pendiente">Pendiente</option>
                    <option value="En revisión">En revisión</option>
                    <option value="Resuelta">Resuelta</option>
                    <option value="Cerrada">Cerrada</option>
                </select>
            </div>
        </div>

        <div class="modal-footer">
            <button type="button" id="modal-guardar" class="btn btn-primary">Guardar cambios</button>
        </div>
    </div>
</div>

<!-- MODAL CUENTA PERSONAL -->
<dialog id="modalCuenta" class="modal-cuenta">
    <form id="formCuenta" method="dialog">
        <header class="modal-header">
            <div><p class="eyebrow">PERSONAL</p><h2 id="tituloModal">Nueva cuenta</h2></div>
            <button class="btn-cerrar" id="cerrarModal" type="button" aria-label="Cerrar">×</button>
        </header>
        <input id="idCuenta" type="hidden">
        <div class="campos-cuenta">
            <label>Nombre completo<input id="nombreCuenta" name="nombre" required maxlength="100" autocomplete="name"></label>
            <label>Correo electrónico<input id="correoCuenta" name="correo" type="email" required maxlength="150" autocomplete="email"></label>
            <label>Teléfono<input id="telefonoCuenta" name="telefono" type="tel" maxlength="20" autocomplete="tel"></label>
            <label id="etiquetaContrasena">Contraseña<input id="contrasenaCuenta" name="contrasena" type="password" minlength="6" autocomplete="new-password"></label>
            <label>Rol<select id="rolCuenta" name="rol" required>
                <option value="Administrador">Administrador</option>
                <option value="Cajero">Cajero</option>
                <option value="Mesero">Mesero</option>
                <option value="Cocina">Cocina</option>
                <option value="Cliente">Cliente</option>
            </select></label>
        </div>
        <fieldset class="permisos-cuenta">
            <legend>Permisos de interfaz</legend>
            <p>Los permisos iniciales dependen del rol y se pueden ajustar para esta cuenta.</p>
            <div id="listaPermisos" class="lista-permisos"></div>
        </fieldset>
        <p id="mensajeCuenta" class="mensaje-cuenta" aria-live="polite"></p>
        <footer class="modal-acciones">
            <button class="btn-secundario" id="cancelarCuenta" type="button">Cancelar</button>
            <button class="btn-primary" type="submit">Guardar cuenta</button>
        </footer>
    </form>
</dialog>

<script src="<?= asset('js/auth.js') ?>"></script>
<script>protegerPagina("reportes");</script>
<script src="<?= asset('js/inventario.js') ?>"></script>
<script src="<?= asset('js/reportes.js') ?>"></script>
<script src="<?= asset('js/cuentas.js') ?>"></script>
</body>
</html>
