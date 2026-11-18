<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Requesty - Inicio de Sesión') ?></title>
    <link rel="stylesheet" href="<?= asset('css/login.css') ?>">
</head>
<body>
    <main>
        <div id="general">
            <section id="titulo">
                <h1>Requesty</h1>
                <h2 id="eslogan">Tu restaurante, más eficiencia</h2>
                <h3>Gestiona tu caja, controla tus pedidos <br>
                    y toma mejores decisiones.</h3>
            </section>
            <section id="registro">
                <h2 id="titulo2">INICIO DE SESIÓN</h2>
                <p id="subtitulo">Accede a tu cuenta para continuar</p>

                <form id="formLogin" method="POST" action="<?= htmlspecialchars(url('login'), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(CsrfService::token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="text" name="usuario" placeholder="Correo electrónico" id="usuario" required>
                    <input type="password" name="contraseña" placeholder="Contraseña" id="contraseña" required>

                    <div class="campo-recuerdame">
                        <input type="checkbox" id="checkbox_recuerdame" name="recuerdame">
                        <label for="checkbox_recuerdame">Recuérdame</label>
                    </div>

                    <a id="a1" href="#">¿Olvidaste tu contraseña?</a>

                    <button id="boton_inicio_de_sesion" class="boton" type="submit">Iniciar sesión</button>
                    
                    <p id="loginMensaje" aria-live="polite">
                        <?= !empty($error) ? htmlspecialchars($error, ENT_QUOTES, 'UTF-8') : '' ?>
                    </p>
                </form>

                <p id="o"> o </p>

                <a id="a2" href="<?= url('registro') ?>">
                    <button id="boton_crear_cuenta" class="boton" type="button">Crear cuenta</button>
                </a>
            </section>
        </div>
    </main>
    <script src="<?= asset('js/auth.js') ?>"></script>
</body>
</html>
