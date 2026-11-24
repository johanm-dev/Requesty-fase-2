<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Página no encontrada | Requesty</title>
    <script src="<?= asset('js/theme.js') ?>"></script>
    <link rel="stylesheet" href="<?= asset('css/base.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/theme.css') ?>">
    <style>
        .error-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            text-align: center;
            padding: 20px;
            background: var(--palette-light);
        }
        .error-code {
            font-size: 96px;
            font-weight: 800;
            color: var(--palette-red);
            margin: 0;
            line-height: 1;
        }
        .error-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--palette-ink);
            margin: 16px 0 8px;
        }
        .error-desc {
            color: var(--palette-muted);
            max-width: 480px;
            margin-bottom: 24px;
            line-height: 1.6;
        }
        .error-btn {
            background: var(--palette-red);
            color: var(--palette-ink);
            padding: 12px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.2s;
        }
        .error-btn:hover {
            background: var(--color-primary-dark);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-brand-box">
            <img src="<?= asset('img/logo.png') ?>" alt="Logo Requesty" class="error-brand-logo">
        </div>
        <button type="button" class="btn-theme-toggle error-theme-toggle" data-theme-toggle aria-label="Cambiar a modo oscuro" title="Cambiar a modo oscuro" aria-pressed="false">
            <i class="fa-solid fa-moon theme-toggle-icon" aria-hidden="true"></i>
        </button>
        <h1 class="error-code">404</h1>
        <h2 class="error-title">Página no encontrada</h2>
        <p class="error-desc">La página o módulo que buscas no existe o ha sido movida como parte de la migración a la arquitectura MVC de Requesty.</p>
        <a href="<?= url('login') ?>" class="error-btn">Volver al inicio</a>
    </div>
</body>
</html>
