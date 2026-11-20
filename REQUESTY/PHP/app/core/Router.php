<?php
/**
 * Enrutador de Requesty conforme a la especificación de la Guía ADSO Trimestre 4
 */
class Router {
    private array $web = [
        ''                  => ['PedidoController', 'index'],
        'login'             => ['AuthController', 'login'],
        'registro'          => ['AuthController', 'registro'],
        'logout'            => ['AuthController', 'logout'],
        
        // Módulos operativos
        'pedidos'           => ['PedidoController', 'index'],
        'pedidos/crear'     => ['PedidoController', 'crear'],
        'pedidos/guardar'   => ['PedidoController', 'guardar'],
        'pedidos/editar'    => ['PedidoController', 'editar'],
        'pedidos/actualizar'=> ['PedidoController', 'actualizar'],
        'pedidos/eliminar'  => ['PedidoController', 'eliminar'],
        
        'cocina'            => ['CocinaController', 'index'],
        'menu'              => ['MenuController', 'index'],
        'caja'              => ['CajaController', 'index'],
        'mesas'             => ['MesaController', 'index'],
        'reportes'          => ['ReporteController', 'index'],
        'reportes/asistencia'=> ['ReporteController', 'asistencia'],
        'reportes/asistencia/csv' => ['ReporteController', 'exportarCsv'],
        'reportes/ventas/csv' => ['ReporteController', 'exportarVentasCsv'],
        'pqrs'              => ['PqrsController', 'index'],
        'cuentas'           => ['ReporteController', 'cuentas'],
    ];

    public function despachar(string $url, string $metodo): void {
        $url = trim($url, '/');

        // Manejo de peticiones API REST
        if (str_starts_with($url, 'api/') || $url === 'api') {
            $partes = explode('/', $url);
            $recurso = $partes[1] ?? '';
            $param = isset($partes[2]) ? (is_numeric($partes[2]) ? (int)$partes[2] : $partes[2]) : null;

            if ($recurso === 'login' && $metodo === 'POST') {
                (new AuthApiController())->login();
                return;
            }

            if ($recurso === 'pedidos' || $recurso === 'pedido') {
                (new PedidoApiController())->manejar($metodo, $param);
                return;
            }

            if ($recurso === 'productos' || $recurso === 'producto') {
                (new ProductoApiController())->manejar($metodo, $param);
                return;
            }

            // Fallback general para ApiController existente
            if (class_exists('ApiController')) {
                $api = new ApiController();
                if (method_exists($api, 'handle')) {
                    $api->handle($url, $metodo);
                    return;
                }
            }

            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Endpoint no encontrado en API Requesty'], JSON_UNESCAPED_UNICODE);
            return;
        }

        // Redirección por defecto a /pedidos o /login
        if ($url === '') {
            $dest = !empty($_SESSION['usuario_id']) || !empty($_SESSION['usuario']) ? '/pedidos' : '/login';
            header('Location: ' . BASE_URL . $dest);
            exit;
        }

        // Despacho de rutas Web
        if (isset($this->web[$url])) {
            [$clase, $accion] = $this->web[$url];
            if (class_exists($clase)) {
                $obj = new $clase();
                if (method_exists($obj, $accion)) {
                    $obj->$accion();
                    return;
                }
            }
        }

        http_response_code(404);
        require_once APPROOT . '/views/error/404.php';
    }
}
