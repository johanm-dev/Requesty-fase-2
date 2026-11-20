<?php
/**
 * Clase de Autenticación, Control de Roles, Tokens y CSRF
 * Conforme a la Guía ADSO Trimestre 4 (Pasos 2, 4, M0)
 */
class Auth {
    /**
     * Exige que el usuario haya iniciado sesión en la interfaz web
     */
    public static function requerirLogin(): void {
        if (empty($_SESSION['usuario_id']) && empty($_SESSION['usuario']['id'])) {
            header('Location: ' . BASE_URL . '/login');
            exit;
        }
    }

    /**
     * Exige que el usuario posea uno de los roles autorizados
     */
    public static function requerirRol(string ...$roles): void {
        self::requerirLogin();
        $rolActual = $_SESSION['rol'] ?? ($_SESSION['usuario']['rol'] ?? '');
        
        // Mapeo flexible de roles mayúsculas/minúsculas
        $rolesNormalizados = array_map('strtolower', $roles);
        if (!in_array(strtolower($rolActual), $rolesNormalizados, true)) {
            http_response_code(403);
            exit('Acceso denegado: se requieren permisos específicos.');
        }
    }

    /**
     * Genera o retorna el token CSRF de la sesión
     */
    public static function csrf(): string {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    /**
     * Genera el input hidden para formularios POST
     */
    public static function campoCsrf(): string {
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars(self::csrf(), ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Valida el token CSRF en peticiones POST
     */
    public static function verificarCsrf(): void {
        $tokenRecibido = $_POST['csrf'] ?? ($_POST['_csrf'] ?? '');
        if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $tokenRecibido)) {
            http_response_code(419);
            exit('Token CSRF inválido o expirado. Recarga la página e intenta de nuevo.');
        }
    }

    /**
     * Valida el Bearer Token enviado en cabecera HTTP para la API REST
     */
    public static function usuarioPorToken(): ?array {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? 
                  ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? 
                  ($_SERVER['HTTP_X_AUTHORIZATION'] ?? ''));

        if (!preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
            return null;
        }

        $token = $matches[1];

        try {
            $db = Database::conectar();
            $stmt = $db->prepare("SELECT id, nombre, correo, rol FROM usuarios WHERE token = ? LIMIT 1");
            $stmt->execute([$token]);
            return $stmt->fetch() ?: null;
        } catch (Throwable $e) {
            // Soporte fallback para NormalizedStore si la BD relacional está offline
            if (class_exists('NormalizedStore')) {
                $user = NormalizedStore::findOne('usuario', ['token' => $token]);
                return $user ?: null;
            }
            return null;
        }
    }
}
