<?php
/**
 * Inicializador principal de la aplicacion con AppSec bootstrap.
 */
class App {
    public static function run(): void {
        $coreDir = __DIR__;
        $appDir  = dirname($coreDir);

        require_once $coreDir . '/Env.php';
        require_once $coreDir . '/ExceptionHandler.php';
        require_once $coreDir . '/SecurityHeaders.php';
        require_once $coreDir . '/CsrfService.php';
        require_once $coreDir . '/JwtService.php';
        require_once $coreDir . '/RateLimiter.php';
        require_once $coreDir . '/XmlSecurity.php';
        require_once $coreDir . '/DtoValidator.php';
        require_once $coreDir . '/CryptoService.php';
        require_once $coreDir . '/NormalizedStore.php';

        ExceptionHandler::register();

        Env::load(dirname($appDir) . '/.env');
        Env::validateBootSecrets();

        SecurityHeaders::apply();

        require_once $appDir . '/config/config.php';
        require_once $appDir . '/config/Database.php';

        require_once $coreDir . '/Model.php';
        require_once $coreDir . '/Controller.php';
        require_once $coreDir . '/Router.php';

        new Router();
    }
}
