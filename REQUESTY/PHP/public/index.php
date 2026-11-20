<?php
/**
 * Punto de entrada único del sistema Requesty (Front Controller)
 * Conforme a la Guía ADSO / Trimestre 4
 */

// Configuración de cookies de sesión seguras
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax'
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APPROOT', dirname(__DIR__) . '/app');

require_once APPROOT . '/config/config.php';
require_once APPROOT . '/config/Database.php';
require_once APPROOT . '/core/Auth.php';
require_once APPROOT . '/core/Router.php';

// Autoloading de controladores y modelos conforme a la guía
spl_autoload_register(function (string $clase) {
    foreach (['controllers', 'controllers/api', 'models', 'core'] as $carpeta) {
        $archivo = APPROOT . "/{$carpeta}/{$clase}.php";
        if (is_file($archivo)) {
            require_once $archivo;
            return;
        }
    }
});

// Manejador global de excepciones para evitar volcado de errores en pantalla
set_exception_handler(function (Throwable $e) {
    error_log("Error no controlado: " . $e->getMessage() . " en " . $e->getFile() . ":" . $e->getLine());
    http_response_code(500);
    $url = $_GET['url'] ?? '';
    if (str_starts_with($url, 'api/') || str_starts_with($url, 'api')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Error interno del servidor'], JSON_UNESCAPED_UNICODE);
    } else {
        echo 'Ocurrió un error. Intenta de nuevo.';
    }
});

// Despachar la petición
$url = $_GET['url'] ?? '';
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

(new Router())->despachar($url, $metodo);
