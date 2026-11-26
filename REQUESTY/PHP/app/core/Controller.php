<?php
/**
 * Controlador Base MVC con soporte JWT + RBAC + Anti-IDOR.
 */
abstract class Controller {
    protected ?array $jwtUser = null;

    public function model(string $model) {
        $modelClass = ucfirst($model);
        $modelFile = APPROOT . '/models/' . $modelClass . '.php';

        if (file_exists($modelFile)) {
            require_once $modelFile;
            return new $modelClass();
        }

        throw new RuntimeException("Modelo {$modelClass} no disponible.");
    }

    public function view(string $view, array $data = []) {
        $viewFile = APPROOT . '/views/' . $view . '.php';

        if (file_exists($viewFile)) {
            extract($data);
            require_once $viewFile;
        } else {
            throw new RuntimeException("Vista {$view} no disponible.");
        }
    }

    public function json($data, int $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function redirect(string $route) {
        header('Location: ' . url($route));
        exit;
    }

    public function getUser(): ?array {
        if ($this->jwtUser !== null) {
            return $this->jwtUser;
        }
        return $_SESSION['usuario'] ?? null;
    }

    public function isLoggedIn(): bool {
        return $this->getUser() !== null;
    }

    public function requireAuth(): void {
        if (!isset($_SESSION['usuario'])) {
            $this->redirect('login');
        }
    }

    public function requireRole(array $roles): void {
        $this->requireAuth();
        if (!$this->hasRole($roles)) {
            throw new ForbiddenException('Tu rol no tiene autorizacion para este modulo.');
        }
    }

    protected function extractBearerToken(): ?string {
        return JwtService::extractBearerToken();
    }

    protected function requireCsrfForWebPost(): void {
        $submitted = $_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!CsrfService::verify(is_string($submitted) ? $submitted : null)) {
            throw new ForbiddenException('Token CSRF invalido o ausente.');
        }
    }

    public function requireApiAuth(): array {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $token = $this->extractBearerToken();
        if ($token !== null) {
            $claims = JwtService::verify($token);
            if ($claims === null) {
                throw new AuthException('Token JWT invalido, expirado o revocado.');
            }
            $this->jwtUser = [
                'id'     => (int)$claims['sub'],
                'nombre' => (string)$claims['nombre'],
                'correo' => (string)$claims['correo'],
                'rol'    => strtoupper((string)$claims['rol']),
                'jti'    => (string)$claims['jti'],
                'exp'    => (int)$claims['exp'],
            ];
            if ($method === 'GET' || $method === 'HEAD') {
                RateLimiter::check('api_read:' . (int)$this->jwtUser['id'], 300, 60);
            }
            return $this->jwtUser;
        }

        if (isset($_SESSION['usuario'])) {
            if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? null;
                if (!CsrfService::verify(is_string($csrfHeader) ? $csrfHeader : null)) {
                    throw new ForbiddenException('Token CSRF invalido o ausente para sesion web.');
                }
            } elseif ($method === 'GET' || $method === 'HEAD') {
                RateLimiter::check('api_read:' . (int)($_SESSION['usuario']['id'] ?? 0), 300, 60);
            }
            return $_SESSION['usuario'];
        }

        throw new AuthException('Autenticacion requerida (JWT Bearer o sesion activa).');
    }

    protected function destroySessionAndCookie(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'] ?: '/',
                    'domain'   => $params['domain'] ?? '',
                    'secure'   => (bool)$params['secure'],
                    'httponly' => (bool)$params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax',
                ]
            );
        }
        session_destroy();
    }

    protected function registrarActividad(int $idUsuario, string $accion, string $modulo): void {
        try {
            $db = Database::getConnection();
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP) === false) {
                $ip = null;
            }
            $stmt = $db->prepare(
                'INSERT INTO actividad (id_usuario, accion, modulo, direccion_ip) VALUES (:u, :a, :m, :ip)'
            );
            $stmt->execute([
                ':u'  => $idUsuario,
                ':a'  => mb_substr($accion, 0, 255, 'UTF-8'),
                ':m'  => mb_substr(strtoupper($modulo), 0, 100, 'UTF-8'),
                ':ip' => $ip,
            ]);
        } catch (Throwable $e) {
            error_log('[Requesty][AuditWarn] ' . $e->getMessage());
        }
    }

    public function requireApiRole(array $allowedRoles): array {
        $user = $this->requireApiAuth();
        $userRole = strtoupper(trim((string)($user['rol'] ?? '')));
        $normalized = array_map(static fn($r) => strtoupper(trim($r)), $allowedRoles);
        if (!in_array($userRole, $normalized, true)) {
            throw new ForbiddenException('Acceso denegado: privilegios insuficientes.');
        }
        return $user;
    }

    public function hasRole($roles): bool {
        $user = $this->getUser();
        if (!$user) {
            return false;
        }
        $userRole = strtoupper(trim((string)($user['rol'] ?? '')));
        $rolesNormalized = array_map(static fn($r) => strtoupper(trim($r)), (array)$roles);
        return in_array($userRole, $rolesNormalized, true);
    }
}
