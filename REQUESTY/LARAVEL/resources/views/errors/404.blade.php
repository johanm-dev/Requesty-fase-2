<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Página no encontrada | Requesty</title>
    <link rel="stylesheet" href="{{ asset('css/base.css') }}">
    <style>
        .error-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            text-align: center;
            padding: 20px;
            background: #F7EEED;
        }
        .error-code {
            font-size: 96px;
            font-weight: 800;
            color: #9D2227;
            margin: 0;
            line-height: 1;
        }
        .error-title {
            font-size: 28px;
            font-weight: 700;
            color: #3E2834;
            margin: 16px 0 8px;
        }
        .error-desc {
            color: #5E504F;
            max-width: 480px;
            margin-bottom: 24px;
            line-height: 1.6;
        }
        .error-btn {
            background: #9D2227;
            color: #ffffff;
            padding: 12px 28px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: background 0.2s;
        }
        .error-btn:hover {
            background: #7D1A1E;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1 class="error-code">404</h1>
        <h2 class="error-title">Página no encontrada</h2>
        <p class="error-desc">La página o módulo que buscas no existe o ha sido movida en Requesty Laravel.</p>
        <a href="{{ url('/login') }}" class="error-btn">Volver al inicio</a>
    </div>
</body>
</html>
