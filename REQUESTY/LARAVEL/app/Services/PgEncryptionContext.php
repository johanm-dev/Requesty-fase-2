<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use PDO;
use RuntimeException;

/**
 * Configures search_path and PostgreSQL session encryption key for pgcrypto helpers.
 */
class PgEncryptionContext
{
    private static ?PDO $configuredPdo = null;

    public static function apply(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'pgsql') {
            return;
        }
        self::applyToPdo($connection->getPdo());
    }

    public static function getPdo(): PDO
    {
        $pdo = DB::connection('pgsql')->getPdo();
        self::applyToPdo($pdo);

        return $pdo;
    }

    public static function applyToPdo(PDO $pdo): void
    {
        if (self::$configuredPdo === $pdo) {
            return;
        }

        $schemaRaw = (string) config('requesty.schema', 'restaurante');
        $schema = preg_replace('/[^a-zA-Z0-9_]/', '', $schemaRaw) ?: 'restaurante';
        $encKey = (string) config('requesty.encryption_key', '');
        if (strlen($encKey) < 32) {
            throw new RuntimeException('APP_ENCRYPTION_KEY debe tener al menos 32 caracteres.');
        }

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        $stmt = $pdo->prepare(
            "SELECT set_config('search_path', :sp, false), set_config('restaurante.encryption_key', :key, false)"
        );
        $stmt->execute([
            ':sp' => "{$schema}, public",
            ':key' => $encKey,
        ]);

        self::$configuredPdo = $pdo;
    }

    public static function reset(): void
    {
        self::$configuredPdo = null;
    }
}
