<?php
$pageTitle = $pageTitle ?? 'Requesty - PQRS';
$currentPage = 'pqrs';
$extraCss = ['css/Pqrs.css'];
require_once APPROOT . '/views/layouts/header.php';
?>
<div class="container">
  <?php require_once APPROOT . '/views/layouts/sidebar.php'; ?>

  <div class="main-content">
    <?php require_once APPROOT . '/views/layouts/topbar.php'; ?>

    <main class="content">
      <div class="page-header">
        <h1 class="page-title">PQRS</h1>
        <p class="page-subtitle">Peticiones, quejas, reclamos, sugerencias y felicitaciones registradas por los clientes.</p>
      </div>

      <div class="pqrs-layout">
        <section class="card form-card">
          <h2 class="card-title">Registrar PQRS</h2>

          <form id="form-pqrs" class="form-grid">
            <div class="form-group grid-full">
              <label for="tipo">Tipo de Solicitud</label>
              <select id="tipo" name="tipo" required>
                <option value="" disabled selected>Selecciona un tipo</option>
                <option value="Petición">Petición</option>
                <option value="Queja">Queja</option>
                <option value="Reclamo">Reclamo</option>
                <option value="Sugerencia">Sugerencia</option>
                <option value="Felicitación">Felicitación</option>
              </select>
            </div>

            <div class="form-group">
              <label for="id_cliente">Cliente</label>
              <select id="id_cliente" name="id_cliente" required>
                <option value="" disabled selected>Selecciona un cliente</option>
              </select>
            </div>

            <div class="form-group">
              <label for="id_pedido">Pedido relacionado <span class="opcional">(opcional)</span></label>
              <select id="id_pedido" name="id_pedido">
                <option value="">Ninguno</option>
              </select>
            </div>

            <div class="form-group grid-full">
              <label for="descripcion">Descripción</label>
              <textarea id="descripcion" name="descripcion" rows="4" placeholder="Cuéntanos qué pasó..." required></textarea>
            </div>

            <div class="form-actions grid-full">
              <button type="reset" class="btn btn-secondary">Limpiar</button>
              <button type="submit" class="btn btn-primary">Registrar PQRS</button>
            </div>
          </form>
        </section>
      </div>
    </main>
  </div>
</div>

<script src="<?= asset('js/auth.js') ?>"></script>
<script>protegerPagina("pqrs");</script>
<script src="<?= asset('js/pqrs.js') ?>"></script>
</body>
</html>
