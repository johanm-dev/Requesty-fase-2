<?php

namespace App\Models;

class Notificacion extends BasePgModel
{
    public function getForUser(int $idUsuario, bool $onlyUnread = false): array
    {
        $sql = 'SELECT id_notificacion, mensaje, tipo, fecha, leida, id_usuario, id_pedido, id_ingrediente
                FROM notificacion
                WHERE id_usuario = :u';
        if ($onlyUnread) {
            $sql .= ' AND leida = FALSE';
        }
        $sql .= ' ORDER BY id_notificacion DESC LIMIT 50';

        return $this->fetchAll($sql, [':u' => $idUsuario]);
    }

    public function marcarLeida(int $idNotificacion, int $idUsuario): bool
    {
        $this->execute(
            'UPDATE notificacion SET leida = TRUE WHERE id_notificacion = :id AND id_usuario = :u',
            [':id' => $idNotificacion, ':u' => $idUsuario]
        );

        return true;
    }

    public function marcarTodasLeidas(int $idUsuario): bool
    {
        $this->execute('UPDATE notificacion SET leida = TRUE WHERE id_usuario = :u AND leida = FALSE', [':u' => $idUsuario]);

        return true;
    }

    public function crear(string $mensaje, string $tipo, int $idUsuario, ?int $idPedido = null, ?int $idIngrediente = null): int
    {
        $row = $this->fetchOne(
            'INSERT INTO notificacion (mensaje, tipo, id_usuario, id_pedido, id_ingrediente)
             VALUES (:m, :t, :u, :p, :i) RETURNING id_notificacion',
            [
                ':m' => $mensaje,
                ':t' => strtoupper(trim($tipo)),
                ':u' => $idUsuario,
                ':p' => $idPedido,
                ':i' => $idIngrediente,
            ]
        );

        return (int) ($row['id_notificacion'] ?? 0);
    }
}
