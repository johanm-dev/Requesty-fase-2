<?php
/**
 * Controlador Base MVC con soporte JWT + RBAC + Anti-IDOR + CSRF.
 */
abstract class Controller {
    protected ?array $jwtUser = null;

    public function model(string $model) {
        $modelClass = ucfirst($model);
        $candidates = [
            APPROOT . '/models/' . $modelClass . '.php',
            APPROOT . '/controllers/models/' . $modelClass . '.php',
        ];

        foreach ($candidates as $modelFile) {
            if (file_exists($modelFile)) {
                require_once $modelFile;
                return new $modelClass();
            }
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

    protected static function normalizeRole(string $role): string {
        $r = strtoupper(trim($role));
        if ($r === 'ADMINISTRADOR') {
            return 'ADMIN';
        }
        return $r;
    }

    public function requireApiAuth(): array {
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
                'rol'    => self::normalizeRole((string)$claims['rol']),
                'jti'    => (string)$claims['jti'],
                'exp'    => (int)$claims['exp'],
            ];
            return $this->jwtUser;
        }

        if (isset($_SESSION['usuario'])) {
            $u = $_SESSION['usuario'];
            $u['rol'] = self::normalizeRole((string)($u['rol'] ?? ''));
            return $u;
        }

        throw new AuthException('Autenticacion requerida (JWT Bearer o sesion activa).');
    }

    public function requireApiRole(array $allowedRoles): array {
        $user = $this->requireApiAuth();
        $userRole = self::normalizeRole((string)($user['rol'] ?? ''));
        $normalized = array_map(static fn($r) => self::normalizeRole((string)$r), $allowedRoles);
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
        $userRole = self::normalizeRole((string)($user['rol'] ?? ''));
        $rolesNormalized = array_map(static fn($r) => self::normalizeRole((string)$r), (array)$roles);
        return in_array($userRole, $rolesNormalized, true);
    }
}
