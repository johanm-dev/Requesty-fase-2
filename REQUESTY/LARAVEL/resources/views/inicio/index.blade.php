{{-- Landing de Requesty (modo claro) --}}
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $pageTitle ?? 'Requesty - Gestión inteligente para restaurantes' }}</title>
    <link rel="stylesheet" href="{{ asset('assets/css/styles.css') }}">
</head>
<body>

<header>
  <div class="contenedor">
    <div class="logo logo-marca" aria-label="Logo Requesty"><img class="logo-img" src="{{ asset('assets/img/imagen-35dc742b14.png') }}" alt="Logo de Requesty"></div>
    
    <a href="{{ url('/login') }}" class="btn btn-blanco boton-sesion">Iniciar sesión</a>
  </div>
</header>

<!-- Hero -->
<section class="hero" id="inicio">
  <div class="contenedor">
    <div>
      <p class="etiqueta">Sistema web para restaurantes</p>
      <h1 class="hero-entrada retardo-a" style="margin-top:14px">Tu operación,<br><span class="resalte">en tiempo real</span></h1>
      <p class="sub hero-entrada retardo-b" style="margin-top:18px">Requesty centraliza menú, pedidos, caja y reportes en una sola plataforma para atender más rápido y decidir mejor.</p>
      <a href="{{ url('/login') }}" class="btn btn-verde boton-demo">Demo gratuita</a>
    </div>
    <div class="hero-panel" style="background-image: linear-gradient(rgba(43,26,32,.55), rgba(43,26,32,.8)), url('https://images.pexels.com/photos/2814829/pexels-photo-2814829.jpeg?auto=compress&w=1260&h=750&dpr=1'); background-size: cover; background-position: center;">
      <img class="pos-imagen logo-panel" src="{{ asset('assets/img/imagen-35dc742b14.png') }}" alt="Logo de Requesty">
      <span class="vertical" aria-hidden="true">REQUESTY</span>
    </div>
  </div>
</section>

<!-- Tarjetas de módulos -->
<section id="modulos-pasarela" aria-label="Módulos del sistema">
  <header class="pasarela-head">
    <span class="pasarela-etiqueta">Plataforma integral</span>
    <h2>Módulos del sistema</h2>
  </header>

  <div class="pasarela-wrap">
    <button id="prevBtn" type="button" class="nav-arrow nav-izq" aria-label="Tarjeta anterior">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"></polyline></svg>
    </button>

    <div id="carouselStage" class="carousel-stage"></div>

    <button id="nextBtn" type="button" class="nav-arrow nav-der" aria-label="Siguiente tarjeta">
      <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
    </button>
  </div>

  <div id="paginationDots" class="pasarela-dots" role="tablist" aria-label="Seleccionar módulo"></div>

  <p class="pasarela-ayuda">Desliza, usa las flechas o haz clic en las tarjetas laterales para navegar</p>

  <div id="feedbackToast" class="toast" role="status" aria-live="polite">
    <span class="toast-punto"></span>
    <span id="toastText"></span>
  </div>
</section>

<!-- Cita de calidad -->
<section class="calidad" id="beneficios">
  <div class="cinta mover-derecha" style="margin: 0 0 90px; transform: rotate(-2deg);"><div class="pista"><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span></div></div>
  <div class="contenedor">
    <p class="frase">Menos esperas,<br><em>menos errores</em></p>
    <p class="texto-lateral">Cada pedido queda registrado con su detalle, evitando confusiones entre mesas, meseros y cocina.</p>
  </div>
  <div class="cinta mover-izquierda" style="margin: 90px 0 0; transform: rotate(-2deg);"><div class="pista"><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span><span>Requesty</span></div></div>
</section>

<!-- Beneficios destacados -->
<section class="beneficios-hub" id="beneficios" aria-labelledby="titulo-beneficios">
  <h2 class="hub-titulo" id="titulo-beneficios">Beneficios</h2>
  <div class="hub">
    <svg class="hub-lineas" viewBox="0 0 960 620" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
      <path class="linea" d="M480 310 Q370 310 280 310"/>
      <path class="linea" d="M480 310 Q370 310 280 310"/>
      <path class="linea" d="M480 310 Q370 310 280 310"/>
      <path class="linea" d="M480 310 Q370 310 280 310"/>
    </svg>

    <div class="hub-centro">
      <img class="hub-logo" src="{{ asset('assets/img/imagen-35dc742b14.png') }}" alt="Logo de Requesty">
    </div>

    <article class="hub-card card-1">
      <span class="hub-icono" aria-hidden="true">⏱</span>
      <h3>Menos tiempos de espera</h3>
      <p>Los pedidos llegan a cocina en tiempo real, sin intermediarios ni papeles perdidos.</p>
    </article>

    <article class="hub-card card-2">
      <span class="hub-icono" aria-hidden="true">✔</span>
      <h3>Menos errores humanos</h3>
      <p>Cada pedido queda registrado con su detalle, evitando confusiones entre mesas y meseros.</p>
    </article>

    <article class="hub-card card-3">
      <span class="hub-icono" aria-hidden="true">▦</span>
      <h3>Control de inventario</h3>
      <p>Sabes qué está disponible antes de que el cliente lo pida.</p>
    </article>

    <article class="hub-card card-4">
      <span class="hub-icono" aria-hidden="true">📊</span>
      <h3>Decisiones con datos</h3>
      <p>Reportes administrativos y un dashboard con la información clave de tu negocio.</p>
    </article>
  </div>
</section>

<!-- Casos por tipo de negocio -->
<section>
  <div class="contenedor">
    <div class="foto-marco"><img class="foto-zoom" src="https://images.pexels.com/photos/4005229/pexels-photo-4005229.jpeg?cs=tinysrgb&dpr=1&w=1100" alt="Mesa de restaurante con alimentos servidos"></div>
    <h2>Pensado para tu restaurante</h2>
    <div class="sucursales">
      <div class="lateral-vertical">Casos de uso</div>
      <div class="caso">
        <h3>Restaurante con mesas</h3>
        <p>Toma el pedido en mesa y envíalo a cocina al instante.</p>
      </div>
      <div class="caso">
        <h3>Domicilios y para llevar</h3>
        <p>Sigue cada pedido hasta la entrega y cierra la caja al final del turno.</p>
      </div>
      <div class="caso">
        <h3>Administración</h3>
        <p>Revisa ventas y productos con más movimiento desde el dashboard.</p>
      </div>
      <div class="caso">
        <h3>Facturación</h3>
        <p>Genera facturas desde el módulo de caja sin pasos adicionales.</p>
      </div>
    </div>
  </div>
</section>

<!-- Pie -->
<footer class="pie">
  <div class="contenedor grid-pie">
    <div>
      <div class="logo logo-marca" style="margin-bottom:14px;"><img class="logo-img" src="{{ asset('assets/img/imagen-35dc742b14.png') }}" alt="Logo de Requesty"></div>
      <p class="sub">Sistema web de gestión para restaurantes: menú, pedidos, caja y reportes en un solo lugar.</p>
    </div>
    <div class="suscribir">
      <h3>Suscríbete</h3>
      <p class="sub">Recibe novedades y consejos para tu restaurante.</p>
      <input type="email" placeholder="Escribe tu correo">
      <button class="btn btn-verde" type="button">Suscribirme</button>
    </div>
    <div class="pie-enlaces">
      <a href="#inicio">Inicio</a>
      <a href="#modulos-pasarela">Módulos</a>
      <a href="#beneficios">Beneficios</a>
      <a href="#">Términos</a>
      <a href="#">Privacidad</a>
    </div>
  </div>
  <p class="copia">© {{ date('Y') }} Requesty. Ejemplo de diseño para el proyecto.</p>
</footer>

    <script src="{{ asset('assets/js/app.js') }}"></script>
</body>
</html>
