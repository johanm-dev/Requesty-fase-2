<?php
/**
 * Inicializador principal de la aplicación
 */
class App {
    public static function run() {
        // Cargar configuración
        require_once dirname(__DIR__) . '/config/config.php';
        require_once dirname(__DIR__) . '/config/Database.php';

        // Cargar clases base del núcleo
        require_once dirname(__DIR__) . '/core/Model.php';
        require_once dirname(__DIR__) . '/core/Controller.php';
        require_once dirname(__DIR__) . '/core/Router.php';

        // Iniciar enrutador
        new Router();
    }
}
