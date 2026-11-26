<?php
/**
 * Configuracion general de la aplicacion Requesty MVC (PostgreSQL 17 + AppSec)
 */

if (!defined('APPROOT')) {
    define('APPROOT', dirname(__DIR__));
}
if (!defined('ROOTPATH')) {
    define('ROOTPATH', dirname(APPROOT));
}

require_once APPROOT . '/core/Env.php';
Env::load(ROOTPATH . '/.env');
Env::validateBootSecrets();

$hasBearer = !empty($_SERVER['HTTP_AUTHORIZATION']) || !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
if (!$hasBearer && session_status() === PHP_SESSION_NONE && php_sapi_name() !== 'cli') {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    $secureCookie = filter_var(Env::get('SESSION_SECURE', $isHttps ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secureCookie && $isHttps,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

define('DB_HOST', Env::requireNonEmpty('DB_HOST'));
define('DB_PORT', Env::requireNonEmpty('DB_PORT'));
define('DB_USER', Env::requireNonEmpty('DB_USER'));
define('DB_PASS', Env::requireNonEmpty('DB_PASSWORD'));
define('DB_NAME', Env::requireNonEmpty('DB_NAME'));
define('DB_SCHEMA', Env::requireNonEmpty('DB_SCHEMA'));
define('DB_SSL_MODE', Env::get('DB_SSL_MODE', 'prefer'));
define('DB_CONNECTION_TIMEOUT', (int)Env::get('DB_CONNECTION_TIMEOUT', '2'));

define('SITENAME', 'Requesty');

$isCli = (php_sapi_name() === 'cli' || empty($_SERVER['HTTP_HOST']));
if ($isCli) {
    $folderName = basename(ROOTPATH);
    $baseUrl = 'http://localhost/' . rawurlencode($folderName);
} else {
    $protocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)) ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $scriptDir = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/.');
    $baseUrl = rtrim($protocol . $host . ($scriptDir !== '' ? '/' . $scriptDir : ''), '/');
}

define('BASE_URL', $baseUrl);

function url($route = '', array $params = []) {
    $route = trim(ltrim((string)$route, '/'));
    if ($route === '') {
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

function asset($path = '') {
    $path = ltrim((string)$path, '/');
    $assetUrl = BASE_URL . '/' . $path;
    $filePath = ROOTPATH . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    if (is_file($filePath)) {
        $assetUrl .= (strpos($assetUrl, '?') === false ? '?' : '&') . 'v=' . filemtime($filePath);
    }
    return $assetUrl;
}

const ROLES = [
    'ADMIN'         => 'Administrador',
    'ADMINISTRADOR' => 'Administrador',
    'CAJERO'        => 'Cajero',
    'MESERO'        => 'Mesero',
    'COCINA'        => 'Cocina',
    'CLIENTE'       => 'Cliente',
];

const PERMISOS_POR_ROL = [
    'admin'         => ['menu', 'mesas', 'pedidos', 'cocina', 'caja', 'pqrs', 'reportes'],
    'administrador' => ['menu', 'mesas', 'pedidos', 'cocina', 'caja', 'pqrs', 'reportes'],
    'cajero'        => ['menu', 'mesas', 'pedidos', 'caja'],
    'mesero'        => ['menu', 'mesas', 'pedidos'],
    'cocina'        => ['menu', 'pedidos', 'cocina'],
    'cliente'       => ['menu', 'pqrs'],
];

const PAGINA_INICIAL_POR_ROL = [
    'admin'         => 'mesas',
    'administrador' => 'mesas',
    'cajero'        => 'caja',
    'mesero'        => 'mesas',
    'cocina'        => 'cocina',
    'cliente'       => 'menu',
];
