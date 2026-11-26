<?php
/**
 * Enrutador MVC de la aplicacion con rutas REST endurecidas (PostgreSQL 17 + AppSec).
 */
class Router {
    protected string $currentController = 'AuthController';
    protected string $currentMethod = 'login';
    protected array $params = [];

    protected array $routes = [
        ''                           => ['controller' => 'AuthController',    'action' => 'index'],
        'login'                      => ['controller' => 'AuthController',    'action' => 'login'],
        'registro'                   => ['controller' => 'AuthController',    'action' => 'registro'],
        'logout'                     => ['controller' => 'AuthController',    'action' => 'logout'],

        'pedidos'                    => ['controller' => 'PedidoController',  'action' => 'index'],
        'cocina'                     => ['controller' => 'CocinaController',  'action' => 'index'],
        'menu'                       => ['controller' => 'MenuController',    'action' => 'index'],
        'caja'                       => ['controller' => 'CajaController',    'action' => 'index'],
        'mesas'                      => ['controller' => 'MesaController',    'action' => 'index'],
        'reportes'                   => ['controller' => 'ReporteController', 'action' => 'index'],
        'pqrs'                       => ['controller' => 'PqrsController',    'action' => 'index'],
        'cuentas'                    => ['controller' => 'ReporteController', 'action' => 'cuentas'],
        'inventario'                 => ['controller' => 'ReporteController', 'action' => 'inventario'],

        // Rutas API REST endurecidas (Informe Tecnico PostgreSQL 17)
        'api/login'                  => ['controller' => 'ApiController',     'action' => 'sesion'],
        'api/sesion'                 => ['controller' => 'ApiController',     'action' => 'sesion'],
        'api/logout'                 => ['controller' => 'ApiController',     'action' => 'logout'],
        'api/usuarios'               => ['controller' => 'ApiController',     'action' => 'usuarios'],
        'api/clientes'               => ['controller' => 'ApiController',     'action' => 'clientes'],
        'api/pedidos'                => ['controller' => 'ApiController',     'action' => 'pedidos'],
        'api/pedidos/personalizacion'=> ['controller' => 'ApiController',     'action' => 'pedidoPersonalizacion'],
        'api/pedidos/descuento'      => ['controller' => 'ApiController',     'action' => 'pedidoDescuento'],
        'api/mesas/crear'            => ['controller' => 'ApiController',     'action' => 'crearMesa'],
        'api/mesas'                  => ['controller' => 'ApiController',     'action' => 'mesas'],
        'api/productos'              => ['controller' => 'ApiController',     'action' => 'productos'],
        'api/pqrs'                   => ['controller' => 'ApiController',     'action' => 'pqrs'],
        'api/caja'                   => ['controller' => 'ApiController',     'action' => 'caja'],
        'api/caja/abrir'             => ['controller' => 'ApiController',     'action' => 'cajaAbrir'],
        'api/caja/cerrar'            => ['controller' => 'ApiController',     'action' => 'cajaCerrar'],
        'api/pagos'                  => ['controller' => 'ApiController',     'action' => 'pagos'],
        'api/propinas/distribuir'    => ['controller' => 'ApiController',     'action' => 'propinaDistribuir'],
        'api/facturas'               => ['controller' => 'ApiController',     'action' => 'facturas'],
        'api/facturas/anular'        => ['controller' => 'ApiController',     'action' => 'facturaAnular'],
        'api/descuentos'             => ['controller' => 'ApiController',     'action' => 'descuentos'],
        'api/actividad'              => ['controller' => 'ApiController',     'action' => 'actividad'],
        'api/inventario'             => ['controller' => 'ApiController',     'action' => 'inventario'],
        'api/asistencia'             => ['controller' => 'ApiController',     'action' => 'asistencia'],
        'api/conciliacion'           => ['controller' => 'ApiController',     'action' => 'conciliacion'],
        'api/estado-db'              => ['controller' => 'ApiController',     'action' => 'estadoDb'],
    ];

    public function __construct() {
        $this->dispatch();
    }

    public function dispatch(): void {
        $route = $this->getRequestedRoute();

        if (isset($this->routes[$route])) {
            $this->currentController = $this->routes[$route]['controller'];
            $this->currentMethod = $this->routes[$route]['action'];
        } else {
            $parts = explode('/', $route);
            $candidateController = ucfirst(strtolower($parts[0])) . 'Controller';

            if (file_exists(APPROOT . '/controllers/' . $candidateController . '.php')) {
                $this->currentController = $candidateController;
                array_shift($parts);
                $this->currentMethod = !empty($parts[0]) ? strtolower($parts[0]) : 'index';
                if (!empty($parts)) {
                    array_shift($parts);
                }
                $this->params = $parts;
            } else {
                $this->currentController = 'ErrorController';
                $this->currentMethod = 'notFound';
            }
        }

        $controllerFile = APPROOT . '/controllers/' . $this->currentController . '.php';
        if (file_exists($controllerFile)) {
            require_once $controllerFile;
            $controllerInstance = new $this->currentController();

            if (method_exists($controllerInstance, $this->currentMethod)) {
                call_user_func_array([$controllerInstance, $this->currentMethod], $this->params);
            } else {
                $this->handleNotFound();
            }
        } else {
            $this->handleNotFound();
        }
    }

    protected function getRequestedRoute(): string {
        if (isset($_GET['page']) && trim((string)$_GET['page']) !== '') {
            return trim((string)$_GET['page'], '/');
        }
        if (isset($_GET['url']) && trim((string)$_GET['url']) !== '') {
            return trim((string)$_GET['url'], '/');
        }
        if (!empty($_SERVER['PATH_INFO'])) {
            return trim((string)$_SERVER['PATH_INFO'], '/');
        }
        if (!empty($_SERVER['REQUEST_URI'])) {
            $path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
            $scriptDir = trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/.');
            $trimmed = trim($path, '/');
            if ($scriptDir !== '' && str_starts_with(strtolower($trimmed), strtolower($scriptDir))) {
                $trimmed = trim(substr($trimmed, strlen($scriptDir)), '/');
            }
            if (str_starts_with($trimmed, 'index.php')) {
                $trimmed = trim(substr($trimmed, strlen('index.php')), '/');
            }
            if ($trimmed !== '') {
                return $trimmed;
            }
        }
        return '';
    }

    protected function handleNotFound(): void {
        http_response_code(404);
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $page = $_GET['page'] ?? '';
        if (str_contains((string)$uri, 'api/') || str_starts_with((string)$page, 'api/')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'code' => 'NOT_FOUND', 'message' => 'Recurso no encontrado.']);
            exit;
        }
        $errorFile = APPROOT . '/views/error/404.php';
        if (file_exists($errorFile)) {
            require_once $errorFile;
        } else {
            echo '<h1>404 - Pagina no encontrada</h1>';
        }
        exit;
    }
}
