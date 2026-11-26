<?php
/**
 * Conexion segura a PostgreSQL 17 con rol de minimo privilegio (app_restaurante_rw),
 * inyeccion de llave criptografica por sesion y resiliencia transaccional automatica.
 */
class Database {
    private static ?PDO $instance = null;
    private static bool $attempted = false;
    private static ?string $lastError = null;

    private static function offlineMarkerFile(): string {
        $dir = ROOTPATH . '/storage/db';
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        return $dir . '/.pg_offline';
    }

    public static function getConnection(): ?PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }
        if (self::$attempted) {
            return null;
        }
        self::$attempted = true;

        $marker = self::offlineMarkerFile();
        if (is_file($marker) && (time() - (int)@filemtime($marker)) < 120) {
            return null;
        }

        try {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
                DB_HOST,
                DB_PORT,
                DB_NAME,
                DB_SSL_MODE
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => DB_CONNECTION_TIMEOUT,
                PDO::ATTR_PERSISTENT         => true,
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // Fija search_path y la llave AES-256/HMAC en una sola ida a PostgreSQL
            $schema = preg_replace('/[^a-zA-Z0-9_]/', '', DB_SCHEMA);
            $encKey = Env::requireNonEmpty('APP_ENCRYPTION_KEY', 32);
            $stmt = $pdo->prepare(
                "SELECT set_config('search_path', :sp, false), set_config('restaurante.encryption_key', :key, false)"
            );
            $stmt->execute([
                ':sp'  => "{$schema}, public",
                ':key' => $encKey,
            ]);

            if (is_file($marker)) {
                @unlink($marker);
            }

            self::$instance = $pdo;
            self::$lastError = null;
            return self::$instance;
        } catch (Throwable $e) {
            self::$lastError = $e->getMessage();
            @file_put_contents($marker, (string)time());
            self::$instance = null;
            return null;
        }
    }

    public static function isConnected(): bool {
        return self::getConnection() !== null;
    }

    public static function getError(): ?string {
        return self::$lastError;
    }

    public static function reset(): void {
        self::$instance = null;
        self::$attempted = false;
        self::$lastError = null;
        $marker = self::offlineMarkerFile();
        if (is_file($marker)) {
            @unlink($marker);
        }
    }
}
