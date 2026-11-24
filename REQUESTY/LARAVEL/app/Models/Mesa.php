<?php

namespace App\Models;

use App\Exceptions\DomainValidationException;

class Mesa extends BasePgModel
{
    public function getAll(): array
    {
        $sql = "SELECT m.id_mesa, m.numero_mesa, m.capacidad, m.codigo_qr, m.estado,
                       m.ocupantes_actuales, m.hora_ocupacion, m.id_cliente_actual,
                       c.nombre AS cliente_nombre, c.correo AS cliente_correo,
                       COALESCE(p.activos, 0) AS pedidos_activos
                FROM mesa m
                LEFT JOIN vw_clientes_publico c ON c.id_cliente = m.id_cliente_actual
                LEFT JOIN (
                    SELECT id_mesa, COUNT(*) AS activos
                    FROM pedido
                    WHERE estado IN ('PENDIENTE', 'EN_PREPARACION', 'LISTO', 'ENTREGADO')
                    GROUP BY id_mesa
                ) p ON p.id_mesa = m.id_mesa
                ORDER BY m.numero_mesa ASC";

        return $this->fetchAll($sql);
    }

    public function findByNumero(int $numeroMesa): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM mesa WHERE numero_mesa = :num LIMIT 1',
            [':num' => $numeroMesa]
        );
    }

    public function assign(int $numeroMesa, int $ocupantes, ?int $idCliente): array
    {
        $mesa = $this->findByNumero($numeroMesa);
        if (!$mesa) {
            throw new DomainValidationException('La mesa especificada no existe.');
        }
        if ($ocupantes < 1 || $ocupantes > (int) $mesa['capacidad']) {
            throw new DomainValidationException("La capacidad de la mesa {$numeroMesa} es de 1 a {$mesa['capacidad']} personas.");
        }
        $this->execute(
            "UPDATE mesa
                SET estado = 'OCUPADA',
                    ocupantes_actuales = :oc,
                    hora_ocupacion = NOW(),
                    id_cliente_actual = :cli
              WHERE numero_mesa = :num",
            [
                ':oc' => $ocupantes,
                ':cli' => $idCliente,
                ':num' => $numeroMesa,
            ]
        );

        return $this->fetchOne(
            'SELECT m.*, c.nombre AS cliente_nombre, c.correo AS cliente_correo
             FROM mesa m
             LEFT JOIN vw_clientes_publico c ON c.id_cliente = m.id_cliente_actual
             WHERE m.numero_mesa = :num',
            [':num' => $numeroMesa]
        ) ?? $mesa;
    }

    public function release(int $numeroMesa): array
    {
        $this->execute(
            "UPDATE mesa
                SET estado = 'DISPONIBLE',
                    ocupantes_actuales = 0,
                    hora_ocupacion = NULL,
                    id_cliente_actual = NULL
              WHERE numero_mesa = :num",
            [':num' => $numeroMesa]
        );

        return $this->findByNumero($numeroMesa) ?? ['numero_mesa' => $numeroMesa, 'estado' => 'DISPONIBLE'];
    }

    public function updateStatus(int $numeroMesa, string $estado): bool
    {
        $est = strtoupper(trim($estado));
        if ($est === 'DISPONIBLE') {
            $this->release($numeroMesa);

            return true;
        }

        return $this->execute(
            'UPDATE mesa SET estado = :estado WHERE numero_mesa = :num',
            [':estado' => $est, ':num' => $numeroMesa]
        );
    }
}
