@include('layouts.header', ['extraCss' => ['css/pedidos.css'], 'extraHeadScripts' => []])
<div class="container">
  @include('layouts.sidebar')

  <div class="main-content">
    @include('layouts.topbar')

    <div class="content-wrapper">
      <!-- Encabezado Principal -->
      <header class="main-header">
        <div class="header-title">
          <h1>Gestión de Pedidos</h1>
          <p>Listado detallado, búsqueda y filtros operativos</p>
        </div>
        <div class="header-actions">
          <p id="pedido-notificacion" class="pedido-notificacion" role="status" aria-live="polite"></p>
          <button class="icon-btn" id="btnNotifications" title="Notificaciones">
            <i class="fa-regular fa-bell"></i>
          </button>
          <div class="server-status" id="serverStatus" style="cursor: pointer;" title="Cambiar estado de servidor">
            <span class="status-dot"></span>
            <span id="serverStatusText">Servidor Activo</span>
          </div>
        </div>
      </header>

      <div class="toolbar">
        <div class="search-box">
          <i class="fa-solid fa-magnifying-glass search-icon"></i>
          <input type="text" id="searchInput" placeholder="Buscar por pedido, mesa...">
        </div>

        <div class="filter-tabs">
          <button class="tab-btn active" data-filter="all">Todos</button>
          <button class="tab-btn" data-filter="Mesa">Mesa</button>
          <button class="tab-btn" data-filter="Domicilio">Domicilio</button>
          <button class="tab-btn" data-filter="Para Llevar">Para Llevar</button>
        </div>

        <button class="btn-primary" id="btnNewOrder">
          <i class="fa-solid fa-plus"></i> + Nuevo Pedido
        </button>
      </div>

      <div class="table-card">
        <table class="orders-table">
          <thead>
            <tr>
              <th>ID Pedido</th>
              <th>Destino / Mesa</th>
              <th>Canal</th>
              <th>Hora</th>
              <th>Productos</th>
              <th>Total</th>
              <th>Estado</th>
              <th class="text-right">Acción</th>
            </tr>
          </thead>
          <tbody id="ordersTbody">
            <!-- Filas dinámicas cargadas por pedidos.js -->
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Modal Nuevo / Editar Pedido -->
<div class="modal-overlay" id="orderModal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 id="modalTitle">Nuevo Pedido</h2>
      <button class="close-btn" id="btnCloseModal">&times;</button>
    </div>
    <form id="orderForm">
      <input type="hidden" id="editOrderId">
      <div class="form-row">
        <div class="form-group">
          <label for="orderIdInput">ID Pedido</label>
          <input type="text" id="orderIdInput" readonly required>
        </div>
        <div class="form-group">
          <label for="statusSelect">Estado</label>
          <select id="statusSelect" required>
            <option value="PENDIENTE">En cola</option>
            <option value="EN_PREPARACION">En preparación</option>
            <option value="LISTO">Listo para servir</option>
            <option value="ENTREGADO">Entregado</option>
            <option value="CANCELADO">Cancelado</option>
          </select>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="channelSelect">Tipo de pedido</label>
          <select id="channelSelect" required>
            <option value="Mesa">Restaurante (mesa)</option>
            <option value="Para Llevar">Para llevar</option>
            <option value="Domicilio">Domicilio</option>
          </select>
        </div>
        <div class="form-group" id="tableGroup">
          <label for="tableInput">Mesa</label>
          <select id="tableInput" required></select>
        </div>
        <div class="form-group" id="addressGroup" hidden>
          <label for="destinationInput">Dirección de entrega</label>
          <input type="text" id="destinationInput" maxlength="180" placeholder="Calle 10 #2-3">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="customerNameInput">Nombre del cliente</label>
          <input type="text" id="customerNameInput" maxlength="100" placeholder="Nombre del cliente">
        </div>
        <div class="form-group">
          <label for="customerEmailInput">Correo del cliente (para seguimiento)</label>
          <input type="email" id="customerEmailInput" maxlength="254" placeholder="cliente@correo.com">
        </div>
      </div>

      <section class="order-builder" aria-labelledby="tituloProductosPedido">
        <h3 id="tituloProductosPedido">Productos del pedido</h3>
        <div class="add-order-product">
          <label class="form-group" for="productSelect">
            <span>Producto</span>
            <select id="productSelect"></select>
          </label>
          <button type="button" class="btn-secondary" id="btnAddProduct">Agregar producto</button>
        </div>
        <div id="orderCartItems" class="order-cart-items"></div>
        <p id="emptyOrderCart" class="empty-order-cart">Agrega productos para crear el pedido.</p>
        <p id="orderCartMessage" class="order-cart-message" aria-live="polite"></p>
        <div class="order-total"><span>Total</span><strong id="orderTotal">$0</strong></div>
      </section>

      <div class="modal-actions">
        <button type="button" class="btn-secondary" id="btnCancelModal">Cancelar</button>
        <button type="submit" class="btn-primary">Guardar Pedido</button>
      </div>
    </form>
  </div>
</div>

<script src="{{ asset('js/auth.js') }}"></script>
<script src="{{ asset('js/inventario.js') }}"></script>
<script src="{{ asset('js/pedidos.js') }}"></script>
</body>
</html>
