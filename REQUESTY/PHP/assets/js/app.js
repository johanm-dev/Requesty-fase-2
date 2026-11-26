(function () {
    var selector = 'section .contenedor > *, .carta, .caso, .popular .tarjeta, .cifra, .hero-panel, .tarjeta-hero, .form, .gracias, .pie .grid-pie > *';
    var elementos = document.querySelectorAll(selector);
    elementos.forEach(function (el, i) {
      el.classList.add('reveal');
      el.classList.add('retardo-' + ((i % 3) + 1));
    });
    if (!('IntersectionObserver' in window)) {
      elementos.forEach(function (el) { el.classList.add('visible'); });
      return;
    }
    var observador = new IntersectionObserver(function (entradas) {
      entradas.forEach(function (entrada) {
        if (entrada.isIntersecting) {
          entrada.target.classList.add('visible');
          observador.unobserve(entrada.target);
        }
      });
    }, { threshold: 0.15 });
    elementos.forEach(function (el) { observador.observe(el); });

    document.querySelectorAll('.hero-entrada').forEach(function (el) {
    el.addEventListener('animationend', function () { el.classList.add('listo'); });
  });

  var menu = document.querySelectorAll('.carrusel .carta, .popular .tarjeta');
    menu.forEach(function (el, i) {
      el.classList.add('reveal');
      el.style.transitionDelay = (i * 0.12) + 's';
    });
  })();

  (function () {
    var nav = document.querySelector('.menu-pill');
    if (!nav) return;
    var indicador = document.createElement('span');
    indicador.className = 'indicador';
    nav.insertBefore(indicador, nav.firstChild);
    var enlaces = nav.querySelectorAll('a');

    var svgNS = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(svgNS, 'svg');
    svg.setAttribute('class', 'linea-nav');
    var trazo = document.createElementNS(svgNS, 'path');
    svg.appendChild(trazo);
    nav.appendChild(svg);

    function dibujarLinea(a) {
      var W = nav.offsetWidth, H = 20, base = 17, pico = 3;
      var x0 = a.offsetLeft - 12, x1 = a.offsetLeft + a.offsetWidth + 12;
      svg.setAttribute('width', W);
      svg.setAttribute('height', H);
      svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
      var d = 'M0 ' + base + ' L' + x0 + ' ' + base +
              ' C' + (x0 + 14) + ' ' + base + ' ' + (x0 + 14) + ' ' + pico + ' ' + (x0 + 28) + ' ' + pico +
              ' L' + (x1 - 28) + ' ' + pico +
              ' C' + (x1 - 14) + ' ' + pico + ' ' + (x1 - 14) + ' ' + base + ' ' + x1 + ' ' + base +
              ' L' + W + ' ' + base;
      trazo.setAttribute('d', d);
    }

    function mover(a) {
      indicador.style.left = a.offsetLeft + 'px';
      indicador.style.width = a.offsetWidth + 'px';
      dibujarLinea(a);
    }
    function activar(a) {
      enlaces.forEach(function (l) { l.classList.remove('activo'); });
      a.classList.add('activo');
      mover(a);
      indicador.classList.remove('pulsa');
      void indicador.offsetWidth;
      indicador.classList.add('pulsa');
    }
    enlaces.forEach(function (a) {
      a.addEventListener('click', function () { activar(a); });
    });
    activar(nav.querySelector('.activo') || enlaces[0]);
    window.addEventListener('resize', function () {
      mover(nav.querySelector('.activo'));
    });
  })();

  (function () {
    var bloques = document.querySelectorAll('.pestanas, .carrusel');
    var secciones = [];
    bloques.forEach(function (b) {
      var s = b.closest('section');
      if (s && secciones.indexOf(s) === -1) { secciones.push(s); }
    });
    secciones.forEach(function (s) { s.classList.add('animar-modulos'); });
    if (!('IntersectionObserver' in window)) {
      secciones.forEach(function (s) { s.classList.add('activa-modulos'); });
      return;
    }
    var obs = new IntersectionObserver(function (entradas) {
      entradas.forEach(function (e) {
        if (e.isIntersecting) {
          e.target.classList.add('activa-modulos');
          obs.unobserve(e.target);
        }
      });
    }, { threshold: 0.2 });
    secciones.forEach(function (s) { obs.observe(s); });
  })();

  (function () {
    var modulesData = [
      { title: 'MENÚ DIGITAL', description: 'Carta actualizada y disponibilidad por producto.',
        icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2v0a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>' },
      { title: 'PEDIDOS', description: 'Seguimiento en tiempo real, desde la toma hasta la entrega.',
        icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1" ry="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M9 12h6"/><path d="M9 16h6"/><path d="M9 8h1"/></svg>' },
      { title: 'DASHBOARD', description: 'Ventas, pedidos activos y productos con más movimiento.',
        icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/><line x1="3" y1="20" x2="21" y2="20"/></svg>' },
      { title: 'CAJA Y FACTURACIÓN', description: 'Cobros, cierre de caja y generación de facturas.',
        icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2.5"/><line x1="2" y1="10" x2="22" y2="10"/><line x1="6" y1="15" x2="10" y2="15"/><circle cx="17" cy="15" r="1.2" fill="currentColor"/></svg>' },
      { title: 'REPORTES', description: 'Informes administrativos para revisar el desempeño.',
        icon: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><polyline points="7 15 11 11 14 14 20 7"/><polyline points="16 7 20 7 20 11"/></svg>' }
    ];

    var activeIndex = 2;
    var total = modulesData.length;
    var stage = document.getElementById('carouselStage');
    var dotsContainer = document.getElementById('paginationDots');
    var prevBtn = document.getElementById('prevBtn');
    var nextBtn = document.getElementById('nextBtn');
    var toast = document.getElementById('feedbackToast');
    var toastText = document.getElementById('toastText');
    var cards = [], dots = [], toastTimeout = null;

    function initCarousel() {
      modulesData.forEach(function (mod, index) {
        var card = document.createElement('article');
        card.className = 'carousel-card';
        card.innerHTML =
          '<div class="icon-badge">' + mod.icon + '</div>' +
          '<div class="txt"><h3>' + mod.title + '</h3><p>' + mod.description + '</p></div>' +
          '<button type="button" class="btn-ver">VER</button>';

        card.addEventListener('click', function () {
          var pos = card.getAttribute('data-pos');
          if (pos === 'left') goPrev();
          else if (pos === 'right') goNext();
        });
        card.querySelector('.btn-ver').addEventListener('click', function (e) {
          e.stopPropagation();
          if (card.getAttribute('data-pos') === 'center') showToast(mod.title);
        });
        stage.appendChild(card);
        cards.push(card);

        var dot = document.createElement('button');
        dot.type = 'button';
        dot.setAttribute('aria-label', 'Ir a ' + mod.title);
        dot.addEventListener('click', function () { activeIndex = index; update(); });
        dotsContainer.appendChild(dot);
        dots.push(dot);
      });
      update();
    }

    function relativeOffset(index, current) {
      var diff = (index - current) % total;
      if (diff > Math.floor(total / 2)) diff -= total;
      if (diff < -Math.floor(total / 2)) diff += total;
      return diff;
    }

    function update() {
      cards.forEach(function (card, index) {
        var o = relativeOffset(index, activeIndex);
        var pos = o === 0 ? 'center' : o === -1 ? 'left' : o === 1 ? 'right' : o <= -2 ? 'hidden-left' : 'hidden-right';
        card.setAttribute('data-pos', pos);
        card.setAttribute('aria-hidden', pos.indexOf('hidden') === 0 ? 'true' : 'false');
      });
      dots.forEach(function (dot, index) {
        var activo = index === activeIndex;
        dot.style.width = activo ? '28px' : '10px';
        dot.style.backgroundColor = activo ? '#B9585D' : 'rgba(185,88,93,.35)';
        dot.style.boxShadow = activo ? '0 0 12px rgba(185,88,93,.6)' : 'none';
      });
    }

    function goNext() { activeIndex = (activeIndex + 1) % total; update(); }
    function goPrev() { activeIndex = (activeIndex - 1 + total) % total; update(); }

    function showToast(title) {
      toastText.innerHTML = 'Accediendo a <strong>' + title + '</strong>';
      toast.classList.add('show');
      clearTimeout(toastTimeout);
      toastTimeout = setTimeout(function () { toast.classList.remove('show'); }, 2400);
    }

    prevBtn.addEventListener('click', goPrev);
    nextBtn.addEventListener('click', goNext);

    document.addEventListener('keydown', function (e) {
      var rect = stage.getBoundingClientRect();
      if (rect.bottom < 0 || rect.top > window.innerHeight) return;
      if (e.key === 'ArrowLeft') goPrev();
      else if (e.key === 'ArrowRight') goNext();
      else if (e.key === 'Enter') showToast(modulesData[activeIndex].title);
    });

    var startX = 0, dragging = false;
    stage.addEventListener('touchstart', function (e) { startX = e.touches[0].clientX; dragging = true; }, { passive: true });
    stage.addEventListener('touchend', function (e) {
      if (!dragging) return;
      var dx = e.changedTouches[0].clientX - startX;
      if (Math.abs(dx) > 42) { if (dx < 0) goNext(); else goPrev(); }
      dragging = false;
    });
    stage.addEventListener('mousedown', function (e) {
      if (e.target.closest('button')) return;
      startX = e.clientX; dragging = true;
    });
    window.addEventListener('mouseup', function (e) {
      if (!dragging) return;
      var dx = e.clientX - startX;
      if (Math.abs(dx) > 50) { if (dx < 0) goNext(); else goPrev(); }
      dragging = false;
    });

    // Desplazamiento automático cada 2.5 s
    var INTERVALO = 1500;
    var autoTimer = null;
    var pausadoPorUsuario = false;
    var reducirMovimiento = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function iniciarAuto() {
      if (reducirMovimiento || autoTimer) return;
      autoTimer = setInterval(function () {
        var rect = stage.getBoundingClientRect();
        var visible = rect.bottom > 0 && rect.top < window.innerHeight;
        if (visible && !pausadoPorUsuario) goNext();
      }, INTERVALO);
    }
    function detenerAuto() {
      clearInterval(autoTimer);
      autoTimer = null;
    }
    function reiniciarAuto() {
      detenerAuto();
      iniciarAuto();
    }

    // Pausa mientras el cursor, el dedo o el foco están sobre el carrusel
    stage.addEventListener('mouseenter', function () { pausadoPorUsuario = true; });
    stage.addEventListener('mouseleave', function () { pausadoPorUsuario = false; });
    stage.addEventListener('touchstart', function () { pausadoPorUsuario = true; }, { passive: true });
    stage.addEventListener('touchend', function () { setTimeout(function () { pausadoPorUsuario = false; }, INTERVALO); });

    // Al navegar manualmente se reinicia el contador para no saltar dos veces seguidas
    [prevBtn, nextBtn].forEach(function (b) {
      b.addEventListener('click', reiniciarAuto);
    });
    dotsContainer.addEventListener('click', reiniciarAuto);

    initCarousel();
    iniciarAuto();
  })();


/* ===== Tarjetas de beneficios orbitando el círculo ===== */
(function () {
  var hub = document.querySelector('.hub');
  if (!hub) return;
  var cards = [].slice.call(hub.querySelectorAll('.hub-card'));
  var paths = [].slice.call(hub.querySelectorAll('.hub-lineas .linea'));
  if (!cards.length || !paths.length) return;
  if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

  var base = [225, 315, 135, 45];   // posición inicial de cada tarjeta (grados)
  var RX = 340, RY = 235, CX = 480, CY = 310;           // radios de la órbita (px)
  var VEL = 14;                     // velocidad en grados por segundo
  var angulo = 0, ultimo = null;


  function esMovil() { return window.innerWidth <= 820; }


  /* Genera un trazo en zigzag (rayo) desde el centro hasta cada tarjeta */
  function rayo(i, ts) {
    var a = (base[i] + angulo) * Math.PI / 180;
    var x = RX * Math.cos(a), y = RY * Math.sin(a);
    var p0x = CX, p0y = CY, p2x = CX + x, p2y = CY + y;
    var kx = CX + x * 0.5, ky = CY + y * 0.5;
    var dx = p2x - p0x, dy = p2y - p0y;
    var len = Math.sqrt(dx * dx + dy * dy) || 1;
    var nx = -dy / len, ny = dx / len;          /* normal al trazo */
    var N = 12, d = 'M' + p0x.toFixed(1) + ' ' + p0y.toFixed(1);
    for (var k = 1; k < N; k++) {
      var t = k / N;
      var bx = (1 - t) * (1 - t) * p0x + 2 * (1 - t) * t * kx + t * t * p2x;
      var by = (1 - t) * (1 - t) * p0y + 2 * (1 - t) * t * ky + t * t * p2y;
      var signo = (k % 2 === 0) ? 1 : -1;
      var amp = 7 + 6 * Math.abs(Math.sin(k * 1.7 + i * 2.3 + ts / 260));
      d += ' L' + (bx + nx * signo * amp).toFixed(1) + ' ' + (by + ny * signo * amp).toFixed(1);
    }
    d += ' L' + p2x.toFixed(1) + ' ' + p2y.toFixed(1);
    return d;
  }

  function frame(ts) {
    if (ultimo === null) ultimo = ts;
    var dt = (ts - ultimo) / 1000;
    ultimo = ts;

    if (esMovil()) {
      cards.forEach(function (c) { c.removeAttribute('style'); });
    } else {
      angulo += VEL * dt;
      cards.forEach(function (c, i) {
        var a = (base[i] + angulo) * Math.PI / 180;
        var x = RX * Math.cos(a), y = RY * Math.sin(a);
        c.style.left = '50%';
        c.style.top = '50%';
        c.style.right = 'auto';
        c.style.bottom = 'auto';
        c.style.transform = 'translate(calc(-50% + ' + x.toFixed(1) + 'px), calc(-50% + ' + y.toFixed(1) + 'px))';
        paths[i].setAttribute('d', rayo(i, ts));
      });
    }
    requestAnimationFrame(frame);
  }
  cards.forEach(function (c, i) {
    c.addEventListener('mouseenter', function () { if (paths[i]) paths[i].classList.add('activa'); });
    c.addEventListener('mouseleave', function () { if (paths[i]) paths[i].classList.remove('activa'); });
  });

  requestAnimationFrame(frame);
})();
