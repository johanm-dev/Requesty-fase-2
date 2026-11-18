<?php
/**
 * Conexión a la Base de Datos con PDO
 */
class Database {
    private static ?PDO $instance = null;
    private static bool $connectionAttempted = false;
    private static ?string $connectionError = null;

    /**
     * Obtener instancia singleton de PDO
     */
    public static function getConnection(): ?PDO {
        if (self::$instance === null && !self::$connectionAttempted) {
            self::$connectionAttempted = true;
            try {
                $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_TIMEOUT            => 2,
                ];
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                self::$connectionError = $e->getMessage();
                // Si la BD no existe, intentamos conectarnos sin dbname para ver si el servidor MySQL está activo
                try {
                    $dsnBase = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=" . DB_CHARSET;
                    $tempPdo = new PDO($dsnBase, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT, PDO::ATTR_TIMEOUT => 2]);
                    // Si conecta, intentamos crear la base de datos REQUESTY
                    $tempPdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME . " CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    // Reintentar conexión con la BD
                    self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
                    self::$connectionError = null;
                } catch (Exception $ex) {
                    self::$connectionError = $e->getMessage();
                    self::$instance = null;
                }
            }
        }
        return self::$instance;
    }

    /**
     * Verificar si la conexión a la base de datos está activa
     */
    public static function isConnected(): bool {
        return self::getConnection() !== null;
    }

    /**
     * Obtener el último error de conexión si hubo
     */
    public static function getError(): ?string {
        return self::$connectionError;
    }
}
