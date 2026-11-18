<?php
/**
 * Configuración general de la aplicación Requesty MVC
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Rutas base
define('APPROOT', dirname(dirname(__FILE__)));
define('ROOTPATH', dirname(dirname(dirname(__FILE__))));

// Configuración de la base de datos (XAMPP predeterminado)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'REQUESTY');
define('DB_CHARSET', 'utf8mb4');

// Nombre del sitio
define('SITENAME', 'Requesty');

// Detección automática de URL base para XAMPP y servidores web
$isCli = (php_sapi_name() === 'cli' || empty($_SERVER['HTTP_HOST']));
if ($isCli) {
    $folderName = basename(ROOTPATH);
    $baseUrl = 'http://localhost/' . rawurlencode($folderName);
} else {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/.');
    $baseUrl = rtrim($protocol . $host . ($scriptDir !== '' ? '/' . $scriptDir : ''), '/');
}

define('BASE_URL', $baseUrl);

/**
 * Generador de URLs amigables
 * @param string $route (ej: 'pedidos', 'cocina', 'login', 'caja')
 * @param array $params Parámetros adicionales
 * @return string
 */
function url($route = '', array $params = []) {
    $route = trim(ltrim($route, '/'));
    if (empty($route) || $route === 'login') {
        $link = BASE_URL . '/index.php';
    } else {
        $link = BASE_URL . '/index.php?page=' . urlencode($route);
    }
    
    if (!empty($params)) {
        $query = http_build_query($params);
        $link .= (strpos($link, '?') !== false ? '&' : '?') . $query;
    }
    return $link;
}

/**
 * Generador de URLs para assets estáticos (CSS, JS, imágenes)
 * @param string $path
 * @return string
 */
function asset($path = '') {
    $path = ltrim($path, '/');
    return BASE_URL . '/' . $path;
}

/**
 * Roles del sistema
 */
const ROLES = [
    'ADMINISTRADOR' => 'Administrador',
    'CAJERO'        => 'Cajero',
    'MESERO'        => 'Mesero',
    'COCINA'        => 'Cocina',
    'CLIENTE'       => 'Cliente'
];

/**
 * Permisos por rol predeterminados
 */
const PERMISOS_POR_ROL = [
    'administrador' => ['menu', 'mesas', 'pedidos', 'cocina', 'caja', 'pqrs', 'reportes'],
    'admin'         => ['menu', 'mesas', 'pedidos', 'cocina', 'caja', 'pqrs', 'reportes'],
    'cajero'        => ['menu', 'mesas', 'pedidos', 'caja'],
    'mesero'        => ['menu', 'mesas', 'pedidos'],
    'cocina'        => ['menu', 'pedidos', 'cocina'],
    'cliente'       => ['menu', 'pqrs']
];

/**
 * Página de inicio predeterminada por rol
 */
const PAGINA_INICIAL_POR_ROL = [
    'administrador' => 'mesas',
    'admin'         => 'mesas',
    'cajero'        => 'caja',
    'mesero'        => 'mesas',
    'cocina'        => 'cocina',
    'cliente'       => 'menu'
];
