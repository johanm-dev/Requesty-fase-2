<?php
/**
 * Configuración global del sistema Requesty
 */

// Detección dinámica y robusta de BASE_URL compatible con localhost y VirtualHosts
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
$baseUrl = rtrim($scriptDir, '/');

if (!defined('BASE_URL')) {
    define('BASE_URL', $baseUrl);
}

if (!defined('APPROOT')) {
    define('APPROOT', dirname(__DIR__));
}

if (!defined('ROOTPATH')) {
    define('ROOTPATH', dirname(__DIR__, 2));
}

// Helper para generar URLs absolutas amigables
if (!function_exists('url')) {
    function url(string $ruta = ''): string {
        $ruta = ltrim($ruta, '/');
        return BASE_URL . ($ruta !== '' ? '/' . $ruta : '');
    }
}

// Helper para enlazar recursos estáticos (CSS, JS, imágenes) en public/assets o public/
if (!function_exists('asset')) {
    function asset(string $ruta = ''): string {
        $ruta = ltrim($ruta, '/');
        if (file_exists(dirname(__DIR__, 2) . '/public/assets/' . $ruta)) {
            return BASE_URL . '/assets/' . $ruta;
        }
        return BASE_URL . '/' . $ruta;
    }
}
