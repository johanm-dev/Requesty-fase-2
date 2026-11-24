<?php

namespace App\Services;

use Illuminate\Http\Request;
use Throwable;

class ActivityLogger
{
    public static function record(int $idUsuario, string $accion, string $modulo, ?Request $request = null): void
    {
        try {
            $pdo = PgEncryptionContext::getPdo();
            $ip = $request?->ip();
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP) === false) {
                $ip = null;
            }
            $stmt = $pdo->prepare(
                'INSERT INTO actividad (id_usuario, accion, modulo, direccion_ip) VALUES (:u, :a, :m, :ip)'
            );
            $stmt->execute([
                ':u' => $idUsuario,
                ':a' => mb_substr($accion, 0, 255, 'UTF-8'),
                ':m' => mb_substr(strtoupper($modulo), 0, 100, 'UTF-8'),
                ':ip' => $ip,
            ]);
        } catch (Throwable $e) {
            error_log('[Requesty][AuditWarn] ' . $e->getMessage());
        }
    }
}
