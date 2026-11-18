<?php
/**
 * Excepciones de dominio y manejador global con sanitizacion de logs y SQLSTATE.
 */
class DomainValidationException extends RuntimeException {
    public function __construct(string $message, int $code = 422, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

class DomainConflictException extends RuntimeException {
    public function __construct(string $message, int $code = 409, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

class AuthException extends RuntimeException {
    public function __construct(string $message = 'No autenticado.', int $code = 401, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

class ForbiddenException extends RuntimeException {
    public function __construct(string $message = 'Acceso denegado para este rol o recurso.', int $code = 403, ?Throwable $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

class ExceptionHandler {
    public static function register(): void {
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([self::class, 'handle']);
    }

    public static function handle(Throwable $e): void {
        SecurityHeaders::apply();

        [$status, $code, $publicMessage] = self::mapException($e);

        self::logMasked($status, $e);

        if (self::expectsJson()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'code'    => $code,
                'message' => $publicMessage,
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        http_response_code($status);
        echo "<h1>Error {$status}</h1><p>" . htmlspecialchars($publicMessage, ENT_QUOTES, 'UTF-8') . "</p>";
        exit;
    }

    public static function mapException(Throwable $e): array {
        if ($e instanceof AuthException) {
            return [401, 'UNAUTHORIZED', $e->getMessage()];
        }
        if ($e instanceof ForbiddenException) {
            return [403, 'FORBIDDEN', $e->getMessage()];
        }
        if ($e instanceof DomainValidationException) {
            $status = in_array($e->getCode(), [400, 422], true) ? $e->getCode() : 422;
            return [$status, 'VALIDATION_ERROR', $e->getMessage()];
        }
        if ($e instanceof DomainConflictException) {
            return [409, 'BUSINESS_CONFLICT', $e->getMessage()];
        }
        if ($e instanceof PDOException) {
            return self::mapPdoException($e);
        }
        return [500, 'INTERNAL_ERROR', 'Error interno al procesar la solicitud.'];
    }

    private static function mapPdoException(PDOException $e): array {
        $sqlState = (string)($e->errorInfo[0] ?? $e->getCode());
        $raw = strtolower($e->getMessage());

        if ($sqlState === '23505') {
            return [409, 'DUPLICATE_RESOURCE', 'El registro ya existe o viola una regla de unicidad activa.'];
        }
        if ($sqlState === '23503') {
            return [422, 'REFERENCE_ERROR', 'La referencia asociada no existe o esta restringida.'];
        }
        if ($sqlState === '23514' || $sqlState === '22P02' || $sqlState === '23502') {
            if (str_contains($raw, 'xml')) {
                return [422, 'INVALID_XML', 'El XML proporcionado infringe las politicas de seguridad.'];
            }
            return [422, 'CONSTRAINT_VIOLATION', 'Los datos enviados no cumplen las reglas de integridad.'];
        }
        if ($sqlState === '42501') {
            return [403, 'DB_PERMISSION_DENIED', 'Operacion no permitida por politica de seguridad.'];
        }
        if ($sqlState === '57014') {
            return [504, 'QUERY_TIMEOUT', 'La operacion excedio el tiempo limite de ejecucion.'];
        }
        if ($sqlState === 'P0001') {
            if (
                str_contains($raw, 'transicion') ||
                str_contains($raw, 'cerrada') ||
                str_contains($raw, 'excede') ||
                str_contains($raw, 'cancelado') ||
                str_contains($raw, 'pagado') ||
                str_contains($raw, 'terminal') ||
                str_contains($raw, 'inmutable') ||
                str_contains($raw, 'no se permite delete') ||
                str_contains($raw, 'factura activa') ||
                str_contains($raw, 'pagos en estado pendiente')
            ) {
                return [409, 'STATE_CONFLICT', 'La operacion entra en conflicto con el estado actual del recurso.'];
            }
            if (str_contains($raw, 'stock insuficiente')) {
                return [422, 'INSUFFICIENT_STOCK', 'Stock insuficiente para completar la operacion.'];
            }
            if (str_contains($raw, 'contrasena')) {
                return [422, 'WEAK_PASSWORD', 'La contrasena no cumple los requisitos minimos de seguridad.'];
            }
            return [422, 'BUSINESS_RULE_ERROR', 'La operacion incumple una regla de negocio.'];
        }

        return [500, 'DATABASE_ERROR', 'No fue posible completar la operacion en este momento.'];
    }

    public static function sanitizeForLog(string $text): string {
        $patterns = [
            '#Bearer\s+[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+#i' => 'Bearer [REDACTED]',
            '#(contrasena|password|jwt_secret|app_encryption_key|db_password)\s*[:=]\s*[^\s,;]+#i' => '$1=[REDACTED]',
            '#(\$2[aby]\$\d{2}\$[./A-Za-z0-9]{53})#' => '[BCRYPT_REDACTED]',
        ];
        $sanitized = preg_replace(array_keys($patterns), array_values($patterns), $text);
        return $sanitized ?? '[LOG_SANITIZATION_ERROR]';
    }

    private static function logMasked(int $status, Throwable $e): void {
        $msg = self::sanitizeForLog(sprintf(
            '[Requesty][HTTP %d][%s] %s',
            $status,
            get_class($e),
            $e->getMessage()
        ));
        error_log($msg);
    }

    private static function expectsJson(): bool {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        $page = $_GET['page'] ?? $_GET['url'] ?? '';
        return str_starts_with((string)$page, 'api/')
            || str_contains((string)$uri, 'api/')
            || str_contains((string)$accept, 'application/json')
            || str_contains((string)$contentType, 'application/json')
            || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }
}
