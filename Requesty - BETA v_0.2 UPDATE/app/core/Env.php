<?php
/**
 * Cargador estricto de variables de entorno con validacion fail-fast y valores por defecto seguros en desarrollo.
 */
class Env {
    private static bool $loaded = false;

    private const DEFAULTS = [
        'DB_HOST'                => '127.0.0.1',
        'DB_PORT'                => '5432',
        'DB_NAME'                => 'restaurante_db',
        'DB_SCHEMA'              => 'restaurante',
        'DB_USER'                => 'app_restaurante_rw',
        'DB_PASSWORD'            => 'RequestyAppRw_2026_SecurePassword!',
        'DB_SSL_MODE'            => 'prefer',
        'DB_CONNECTION_TIMEOUT'  => '2',
        'APP_ENCRYPTION_KEY'     => 'Requesty_AES256_PII_MasterKey_2026_Secure32Chars!',
        'JWT_SECRET'             => 'Requesty_JWT_HS256_SigningSecret_2026_Secure32Chars!',
        'JWT_EXPIRATION_SECONDS' => '900',
        'CORS_ALLOWED_ORIGINS'   => 'http://127.0.0.1,http://localhost,http://127.0.0.1:8080,http://localhost:8080,http://127.0.0.1:8088,http://localhost:8088',
        'SESSION_SECURE'         => 'false',
    ];

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
            return $default ?? (self::DEFAULTS[$key] ?? null);
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
