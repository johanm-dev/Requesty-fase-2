<?php
/**
 * Enrutador MVC de la aplicación
 */
class Router {
    protected string $currentController = 'AuthController';
    protected string $currentMethod = 'login';
    protected array $params = [];

    // Mapeo amigable de rutas a Controlador y Método
    protected array $routes = [
        ''                  => ['controller' => 'AuthController',     'action' => 'index'],
        'login'             => ['controller' => 'AuthController',     'action' => 'login'],
        'registro'          => ['controller' => 'AuthController',     'action' => 'registro'],
        'logout'            => ['controller' => 'AuthController',     'action' => 'logout'],
        
        'pedidos'           => ['controller' => 'PedidoController',   'action' => 'index'],
        'cocina'            => ['controller' => 'CocinaController',   'action' => 'index'],
        'menu'              => ['controller' => 'MenuController',     'action' => 'index'],
        'caja'              => ['controller' => 'CajaController',     'action' => 'index'],
        'mesas'             => ['controller' => 'MesaController',     'action' => 'index'],
        'reportes'          => ['controller' => 'ReporteController',  'action' => 'index'],
        'pqrs'              => ['controller' => 'PqrsController',     'action' => 'index'],
        'cuentas'           => ['controller' => 'ReporteController',  'action' => 'cuentas'],
        
        // Rutas API para sincronización AJAX
        'api/usuarios'      => ['controller' => 'ApiController',      'action' => 'usuarios'],
        'api/pedidos'       => ['controller' => 'ApiController',      'action' => 'pedidos'],
        'api/mesas'         => ['controller' => 'ApiController',      'action' => 'mesas'],
        'api/productos'     => ['controller' => 'ApiController',      'action' => 'productos'],
        'api/pqrs'          => ['controller' => 'ApiController',      'action' => 'pqrs'],
        'api/caja'          => ['controller' => 'ApiController',      'action' => 'caja'],
        'api/sesion'        => ['controller' => 'ApiController',      'action' => 'sesion'],
        'api/inventario'    => ['controller' => 'ApiController',      'action' => 'inventario'],
    ];

    public function __construct() {
        $this->dispatch();
    }

    /**
     * Resolver la ruta actual y ejecutar el controlador
     */
    public function dispatch() {
        $route = $this->getRequestedRoute();

        // Verificar si la ruta exacta existe en el mapa
        if (isset($this->routes[$route])) {
            $this->currentController = $this->routes[$route]['controller'];
            $this->currentMethod = $this->routes[$route]['action'];
        } else {
            // Intentar desglosar controlador/accion de la URL (ej: pedidos/crear)
            $parts = explode('/', $route);
            $candidateController = ucfirst(strtolower($parts[0])) . 'Controller';
            
            if (file_exists(APPROOT . '/controllers/' . $candidateController . '.php')) {
                $this->currentController = $candidateController;
                array_shift($parts);
                if (isset($parts[0]) && !empty($parts[0])) {
                    $this->currentMethod = strtolower($parts[0]);
                    array_shift($parts);
                } else {
                    $this->currentMethod = 'index';
                }
                $this->params = $parts;
            } else {
                // Ruta no encontrada
                $this->currentController = 'ErrorController';
                $this->currentMethod = 'notFound';
            }
        }

        // Cargar archivo del controlador
        $controllerFile = APPROOT . '/controllers/' . $this->currentController . '.php';
        if (file_exists($controllerFile)) {
            require_once $controllerFile;
            $controllerInstance = new $this->currentController();

            if (method_exists($controllerInstance, $this->currentMethod)) {
                call_user_func_array([$controllerInstance, $this->currentMethod], $this->params);
            } else {
                $this->handleNotFound("Método {$this->currentMethod} no encontrado en {$this->currentController}");
            }
        } else {
            $this->handleNotFound("Controlador {$this->currentController} no encontrado");
        }
    }

    /**
     * Obtener la ruta solicitada desde $_GET o PATH_INFO
     */
    protected function getRequestedRoute(): string {
        // Prioridad 1: parámetro 'page' o 'url'
        if (isset($_GET['page']) && trim($_GET['page']) !== '') {
            return trim($_GET['page'], '/');
        }
        if (isset($_GET['url']) && trim($_GET['url']) !== '') {
            return trim($_GET['url'], '/');
        }
        
        // Prioridad 2: parámetros explícitos ?c=...&a=...
        if (isset($_GET['controller']) && trim($_GET['controller']) !== '') {
            $c = strtolower(trim($_GET['controller']));
            $a = isset($_GET['action']) ? strtolower(trim($_GET['action'])) : 'index';
            return $c . '/' . $a;
        }

        // Prioridad 3: PATH_INFO si está disponible
        if (!empty($_SERVER['PATH_INFO'])) {
            return trim($_SERVER['PATH_INFO'], '/');
        }

        // Si no hay parámetro, ruta raíz ''
        return '';
    }

    /**
     * Manejador de error 404
     */
    protected function handleNotFound(string $message = '') {
        http_response_code(404);
        $errorFile = APPROOT . '/views/error/404.php';
        if (file_exists($errorFile)) {
            require_once $errorFile;
        } else {
            echo "<h1>404 - Página no encontrada</h1><p>" . htmlspecialchars($message) . "</p>";
        }
        exit;
    }
}
