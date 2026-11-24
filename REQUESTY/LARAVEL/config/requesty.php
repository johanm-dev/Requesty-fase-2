<?php

return [
    'schema' => env('DB_SCHEMA', 'restaurante'),
    'encryption_key' => env('APP_ENCRYPTION_KEY', ''),
    'jwt_secret' => env('JWT_SECRET', ''),
    'jwt_ttl' => (int) env('JWT_EXPIRATION_SECONDS', 900),
    'cors_allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))
    ))),

    'roles' => [
        'ADMIN' => 'Administrador',
        'CAJERO' => 'Cajero',
        'MESERO' => 'Mesero',
        'COCINA' => 'Cocina',
        'CLIENTE' => 'Cliente',
    ],

    'permisos_por_rol' => [
        'admin' => ['menu', 'mesas', 'pedidos', 'cocina', 'caja', 'pqrs', 'reportes'],
        'administrador' => ['menu', 'mesas', 'pedidos', 'cocina', 'caja', 'pqrs', 'reportes'],
        'cajero' => ['menu', 'mesas', 'pedidos', 'caja'],
        'mesero' => ['menu', 'mesas', 'pedidos'],
        'cocina' => ['menu', 'pedidos', 'cocina'],
        'cliente' => ['menu', 'pqrs', 'caja'],
    ],

    'pagina_inicial_por_rol' => [
        'admin' => 'mesas',
        'administrador' => 'mesas',
        'cajero' => 'caja',
        'mesero' => 'mesas',
        'cocina' => 'cocina',
        'cliente' => 'menu',
    ],
];
