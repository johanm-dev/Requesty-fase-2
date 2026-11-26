<?php
/**
 * Requesty - Front Controller Principal (MVC)
 * Punto de entrada unico endurecido.
 */

error_reporting(E_ALL);
ini_set('display_errors', '0');

$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (preg_match('#^/(app|tests|storage|database)(/|$)|/\.(env|htaccess|gitignore)|\\.(sql|log|md|json|lock|example|bak)$#i', $reqPath) === 1) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo '403 Forbidden';
    exit;
}

if (php_sapi_name() === 'cli-server' && preg_match('#^/(css|js|img)/#i', $reqPath) === 1) {
    $staticFile = realpath(__DIR__ . $reqPath);
    $baseDir = realpath(__DIR__);
    if ($staticFile !== false && $baseDir !== false && str_starts_with($staticFile, $baseDir . DIRECTORY_SEPARATOR) && is_file($staticFile)) {
        return false;
    }
}

require_once __DIR__ . '/app/core/App.php';

App::run();
