<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-base" content="{{ rtrim(url('/'), '/') }}">
    <title>{{ $pageTitle ?? 'Requesty - Inicio de Sesión' }}</title>
    <script src="{{ asset('js/theme.js') }}"></script>
    <link rel="icon" type="image/png" href="{{ asset('img/logo.png') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/login.css') }}">
    <link rel="stylesheet" href="{{ asset('css/theme.css') }}">
</head>
<body>
    <header class="auth-theme-header">
        <button type="button" class="btn-theme-toggle auth-theme-toggle" data-theme-toggle
                title="Cambiar a modo oscuro" aria-label="Cambiar a modo oscuro" aria-pressed="false">
            <i class="fa-solid fa-moon theme-toggle-icon" aria-hidden="true"></i>
        </button>
    </header>
    <div class="luciernagas-fondo" aria-hidden="true">
        <canvas id="luciernagasCanvas"></canvas>
        <span class="luciernaga"></span>
        <span class="luciernaga"></span>
        <span class="luciernaga"></span>
        <span class="luciernaga"></span>
        <span class="luciernaga"></span>
        <span class="luciernaga"></span>
        <span class="luciernaga"></span>
        <span class="luciernaga"></span>
        <span class="luciernaga"></span>
        <span class="luciernaga"></span>
        <span class="luciernaga"></span>
        <span class="luciernaga"></span>
        <span class="luciernaga"></span>
        <span class="luciernaga"></span>
        <span class="luciernaga"></span>
        <span class="luciernaga"></span>
        <span class="luciernaga"></span>
        <span class="luciernaga"></span>
    </div>
    <main>
        <div id="general">
            <section id="titulo">
                <img id="mascota-login" src="{{ asset('img/mascota.gif') }}" alt="Mascota de Requesty">
                <h1>Requesty</h1>
                <h2 id="eslogan">Tu restaurante, más eficiencia</h2>
                <h3>Gestiona tu caja, controla tus pedidos <br>
                    y toma mejores decisiones.</h3>
            </section>
            <section id="registro">
                <h2 id="titulo2">INICIO DE SESIÓN</h2>
                <p id="subtitulo">Accede a tu cuenta para continuar</p>

                <form id="formLogin" method="POST" action="{{ url('/login') }}">
                    @csrf
                    <input type="text" name="usuario" placeholder="Correo electrónico" id="usuario" required>
                    <input type="password" name="contraseña" placeholder="Contraseña" id="contraseña" required>

                    <div class="campo-recuerdame">
                        <input type="checkbox" id="checkbox_recuerdame" name="recuerdame">
                        <label for="checkbox_recuerdame">Recuérdame</label>
                    </div>

                    <a id="a1" href="#">¿Olvidaste tu contraseña?</a>

                    <button id="boton_inicio_de_sesion" class="boton" type="submit">Iniciar sesión</button>

                    <p id="loginMensaje" aria-live="polite">{{ $error ?? '' }}</p>
                </form>

                <p id="o"> o </p>

                <a id="a2" href="{{ url('/registro') }}">
                    <button id="boton_crear_cuenta" class="boton" type="button">Crear cuenta</button>
                </a>
            </section>
        </div>
    </main>
    <script src="{{ asset('js/auth.js') }}"></script>
</body>
</html>
