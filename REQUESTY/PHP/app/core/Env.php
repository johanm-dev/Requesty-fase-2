<?php
/**
 * Cargador estricto de variables de entorno con validacion fail-fast.
 */
class Env {
    private static bool $loaded = false;

    public static function load(?string $envFile = null): void {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if ($envFile !== null && is_file($envFile) && is_readable($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $trimmed = trim($line);
                    if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                        continue;
                    }
                    $eqPos = strpos($trimmed, '=');
                    if ($eqPos === false) {
                        continue;
                    }
                    $key = trim(substr($trimmed, 0, $eqPos));
                    $val = trim(substr($trimmed, $eqPos + 1));
                    $val = trim($val, "\"'");
                    if ($key !== '' && getenv($key) === false) {
                        putenv("{$key}={$val}");
                        $_ENV[$key] = $val;
                    }
                }
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string {
        $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($val === false || $val === null || trim((string)$val) === '') {
            return $default;
        }
        return (string)$val;
    }

    public static function requireNonEmpty(string $key, int $minLength = 1): string {
        $val = self::get($key);
        if ($val === null || strlen($val) < $minLength) {
            throw new RuntimeException("Configuracion critica faltante o invalida: {$key}");
        }
        return $val;
    }

    public static function validateBootSecrets(): void {
        self::requireNonEmpty('DB_HOST');
        self::requireNonEmpty('DB_PORT');
        self::requireNonEmpty('DB_NAME');
        self::requireNonEmpty('DB_SCHEMA');
        self::requireNonEmpty('DB_USER');
        self::requireNonEmpty('DB_PASSWORD');
        self::requireNonEmpty('APP_ENCRYPTION_KEY', 32);
        self::requireNonEmpty('JWT_SECRET', 32);

        $cors = self::requireNonEmpty('CORS_ALLOWED_ORIGINS');
        $origins = array_map('trim', explode(',', $cors));
        foreach ($origins as $origin) {
            if ($origin === '*') {
                throw new RuntimeException('CORS_ALLOWED_ORIGINS no puede usar comodin *');
            }
        }
    }
}
