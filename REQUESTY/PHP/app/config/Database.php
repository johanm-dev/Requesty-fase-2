<?php
/**
 * Conexion segura a PostgreSQL 17 con rol de minimo privilegio (app_restaurante_rw)
 * e inyeccion de llave criptografica por sesion.
 */
class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

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

        self::$instance = $pdo;
        return self::$instance;
    }

    public static function isConnected(): bool {
        try {
            self::getConnection();
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function reset(): void {
        self::$instance = null;
    }
}
