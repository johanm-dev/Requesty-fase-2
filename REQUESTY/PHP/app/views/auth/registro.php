<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Registro - Requesty') ?></title>
    <link rel="stylesheet" href="<?= asset('css/registro.css') ?>">
</head>
<body>
  <header>
     <h1 class="logo">Requesty</h1>
  </header>

  <main id="contenido">

    <section id="registro" class="seccion-formulario">
      <h2>Registrar Cuenta Nueva</h2>
      <p class="seccion-intro">Completa los datos requeridos. Los campos marcados con * son obligatorios.</p>

      <form id="formAprendiz" method="POST" action="<?= url('registro') ?>" novalidate>
        <div class="campo">
          <label for="nombre">Nombre completo *</label>
          <input type="text" id="nombre" name="nombre" placeholder="Ej: Juan Perez Gonzales" required>
        </div>

        <div class="campo">
          <label for="correo">Correo electrónico *</label>
          <input type="email" id="correo" name="correo" placeholder="Ej: nombre@gmail.com" required>
        </div>

        <div class="campo">
          <label for="telefono">Teléfono *</label>
          <input type="tel" id="telefono" name="telefono" placeholder="Ej: 3XXXXXXXXX">
        </div>

        <div class="campo">
          <label for="contrasena">Contraseña *</label>
          <input type="password" id="contrasena" name="contrasena" placeholder="Mínimo 6 caracteres" required>
        </div>

        <div class="campo">
          <label for="confirmar_contrasena">Confirmar contraseña *</label>
          <input type="password" id="confirmar_contrasena" name="confirmar_contrasena" placeholder="Repite tu contraseña" required>
        </div>

        <div class="campo">
          <label for="rol">Rol *</label>
          <select id="rol" name="rol" required>
            <option value="" disabled selected>Selecciona un rol</option>
            <option value="Cliente">Cliente</option>
            <option value="Administrador">Administrador</option>
          </select>
        </div>

        <div class="fila-botones-grandes">
          <button type="button" id="btnBorrarTodo" class="btn-secundario">Borrar</button>
          <button type="submit" class="btn-primario">Guardar y continuar</button>
        </div>

        <p id="formularioMensaje" class="formulario-mensaje" aria-live="polite">
            <?= !empty($error) ? htmlspecialchars($error) : (!empty($success) ? htmlspecialchars($success) : '') ?>
        </p>
      </form>
      <p class="enlace-login">¿Ya tienes cuenta? <a href="<?= url('login') ?>">Inicia sesión</a></p>
    </section>
  </main>

  <footer>
  </footer>

  <script src="<?= asset('js/auth.js') ?>"></script>
  <script src="<?= asset('js/registro.js') ?>"></script>
</body>
</html>
