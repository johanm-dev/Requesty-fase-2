@include('layouts.header', ['extraCss' => ['css/menu.css'], 'extraHeadScripts' => []])
<div class="container">
    @include('layouts.sidebar')

    <div class="main-content">
        @include('layouts.topbar')

        <div class="content menu-kiosk-page">
            <div class="page-header">
                <div>
                    <p class="catalogo-eyebrow">AQUÍ ES · COCINA DE CASA</p>
                    <h1 class="page-title">Nuestro menú</h1>
                </div>
            </div>
            <section class="catalogo-controles" aria-label="Buscar y filtrar productos">
                <label class="catalogo-busqueda" for="buscar-menu">
                    <span>Buscar en el menú</span>
                    <input id="buscar-menu" type="search" placeholder="Plato o ingrediente...">
                </label>
                <div class="catalogo-categorias-wrapper">
                    <span class="catalogo-seccion-etiqueta">Categorías</span>
                    <div id="filtros-menu" class="catalogo-filtros" aria-label="Categorías del menú"></div>
                </div>
            </section>
            <p id="mensaje-menu-inventario" class="mensaje-menu-inventario" role="status" aria-live="polite"></p>
            <p id="mensaje-pedido" class="mensaje-menu-inventario" role="alert" aria-live="assertive"></p>
            <p id="bloqueo-cliente-mesa" class="bloqueo-cliente-mesa" role="status" hidden>Un mesero debe asignarte una mesa para consultar el menú.</p>

            <div class="seccion-platos-header">
                <div>
                    <h2 class="seccion-platos-titulo">Menú especial para ti</h2>
                    <p class="catalogo-conteo" id="conteo-menu" aria-live="polite"></p>
                </div>
            </div>

            <section id="catalogo-menu" class="catalogo-productos" aria-live="polite"></section>
            <section id="seguimiento-pedidos" class="seguimiento-pedidos" aria-labelledby="titulo-seguimiento" hidden>
                <h2 id="titulo-seguimiento">Historial de mis pedidos</h2>
                <p id="mensaje-seguimiento-pedidos" role="status" aria-live="polite"></p>
                <div id="lista-seguimiento-pedidos"></div>
            </section>
        </div>
    </div>
</div>

<!-- Panel Lateral Desplegable Derecho: Ver Detalle / Personalización y Pedido -->
<dialog id="detalle-producto" class="detalle-producto drawer-derecho" aria-labelledby="nombre-detalle-producto">
    <div class="detalle-producto-contenido drawer-contenido">
        <header class="drawer-header">
            <div class="drawer-header-info">
                <span class="drawer-mesa-badge" id="drawer-mesa-badge">Mesa 1</span>
                <div class="drawer-cliente-meta">
                    <strong class="drawer-cliente-nombre" id="drawer-cliente-nombre">Aquí Es · Cocina de Casa</strong>
                    <span class="drawer-fecha" id="drawer-fecha">Detalle de selección</span>
                </div>
            </div>
            <button id="cerrar-detalle-producto" class="detalle-producto-cerrar" type="button" aria-label="Cerrar detalle">
                <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            </button>
        </header>

        <div class="drawer-body">
            <div class="drawer-imagen-wrap" id="drawer-imagen-wrap"></div>

            <div class="drawer-meta-badges">
                <span id="categoria-detalle-producto" class="plato-categoria"></span>
                <span id="estado-detalle-producto" class="estado-disponibilidad" aria-live="polite"></span>
            </div>

            <h2 id="nombre-detalle-producto" class="drawer-titulo-plato"></h2>

            <div class="drawer-precio-fila">
                <span id="precio-detalle-producto" class="plato-precio drawer-precio-destacado"></span>
                <div class="drawer-cantidad-control">
                    <button type="button" id="btn-qty-minus" class="btn-qty" aria-label="Disminuir cantidad">-</button>
                    <span id="drawer-qty-val" class="drawer-qty-val">1</span>
                    <button type="button" id="btn-qty-plus" class="btn-qty" aria-label="Aumentar cantidad">+</button>
                </div>
            </div>

            <div class="drawer-seccion-ingredientes">
                <h3 class="drawer-seccion-titulo"><i class="fa-solid fa-leaf"></i> Ingredientes y especificaciones</h3>
                <div id="drawer-tags-ingredientes" class="drawer-tags-lista"></div>
            </div>

            <div class="drawer-ticket-resumen">
                <div class="ticket-fila">
                    <span class="ticket-concepto">Precio base:</span>
                    <span class="ticket-monto" id="ticket-precio-base">$0</span>
                </div>
                <div class="ticket-fila">
                    <span class="ticket-concepto">Cantidad:</span>
                    <span class="ticket-monto" id="ticket-unidades">1x</span>
                </div>
                <div class="ticket-fila ticket-fila-total">
                    <strong class="ticket-total-label">Total estimado:</strong>
                    <strong class="ticket-total-valor" id="ticket-total-monto">$0</strong>
                </div>
            </div>
        </div>

        <footer class="drawer-footer">
            <button type="button" id="drawer-btn-accion" class="btn-drawer-confirmar">
                <span id="drawer-btn-texto"><i class="fa-solid fa-plus"></i> Agregar al pedido</span>
                <span id="drawer-btn-precio-total" class="drawer-btn-precio-pill">$0</span>
            </button>
        </footer>
    </div>
</dialog>

<script src="{{ asset('js/auth.js') }}"></script>
<script src="{{ asset('js/inventario.js') }}"></script>
<script src="{{ asset('js/catalogo-cliente.js') }}"></script>
</body>
</html>
