<?php
/**
 * Controlador Base MVC
 */
abstract class Controller {

    /**
     * Cargar e instanciar un Modelo
     * @param string $model Nombre del modelo (ej: 'Usuario', 'Pedido')
     * @return object
     */
    public function model(string $model) {
        $modelClass = ucfirst($model);
        $modelFile = APPROOT . '/models/' . $modelClass . '.php';

        if (file_exists($modelFile)) {
            require_once $modelFile;
            return new $modelClass();
        }

        throw new Exception("El modelo {$modelClass} no existe en {$modelFile}");
    }

    /**
     * Cargar y renderizar una Vista
     * @param string $view Ruta de la vista relativa a app/views/ (ej: 'pedidos/index')
     * @param array $data Datos pasados a la vista
     */
    public function view(string $view, array $data = []) {
        $viewFile = APPROOT . '/views/' . $view . '.php';

        if (file_exists($viewFile)) {
            // Extraer variables para la vista
            extract($data);
            require_once $viewFile;
        } else {
            throw new Exception("La vista {$view} no existe en {$viewFile}");
        }
    }

    /**
     * Responder en formato JSON (para APIs y llamadas AJAX)
     * @param mixed $data
     * @param int $status
     */
    public function json($data, int $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Redireccionar a una ruta
     * @param string $route
     */
    public function redirect(string $route) {
        header('Location: ' . url($route));
        exit;
    }

    /**
     * Obtener el usuario autenticado en sesión
     * @return array|null
     */
    public function getUser(): ?array {
        return $_SESSION['usuario'] ?? null;
    }

    /**
     * Verificar si hay una sesión activa
     * @return bool
     */
    public function isLoggedIn(): bool {
        return isset($_SESSION['usuario']);
    }

    /**
     * Exigir inicio de sesión (redirecciona a login si no está logueado)
     */
    public function requireAuth() {
        if (!$this->isLoggedIn()) {
            $this->redirect('login');
        }
    }

    /**
     * Verificar si el usuario tiene un rol determinado
     * @param array|string $roles
     * @return bool
     */
    public function hasRole($roles): bool {
        if (!$this->isLoggedIn()) {
            return false;
        }
        $userRole = strtolower(trim($_SESSION['usuario']['rol'] ?? ''));
        $roles = (array)$roles;
        $rolesNormalized = array_map(function($r) { return strtolower(trim($r)); }, $roles);
        
        return in_array($userRole, $rolesNormalized, true);
    }
}
