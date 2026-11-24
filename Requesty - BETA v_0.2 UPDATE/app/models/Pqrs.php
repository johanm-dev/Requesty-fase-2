<?php
/**
 * Modelo de PQRS (PostgreSQL 17: validacion de coherencia cliente-pedido trg_pqrs_validar_cliente).
 */
class Pqrs extends Model {
    public function getAll(?int $idCliente = null): array {
        if ($this->isDbConnected()) {
            $sql = "SELECT pq.*, c.nombre AS cliente_nombre
                    FROM pqrs pq
                    LEFT JOIN vw_clientes_publico c ON pq.id_cliente = c.id_cliente";
            $params = [];
            if ($idCliente !== null) {
                $sql .= " WHERE pq.id_cliente = :id_cliente";
                $params[':id_cliente'] = $idCliente;
            }
            $sql .= " ORDER BY pq.id_pqrs DESC";
            return $this->fetchAll($sql, $params);
        }

        return NormalizedStore::withTransaction(static function (array &$state) use ($idCliente): array {
            $clientes = [];
            foreach ($state['cliente'] as $c) {
                $clientes[(int)$c['id_cliente']] = $c['nombre'];
            }
            $out = [];
            foreach ($state['pqrs'] as $pq) {
                if ($idCliente !== null && (int)$pq['id_cliente'] !== $idCliente) {
                    continue;
                }
                $out[] = array_merge($pq, [
                    'cliente_nombre' => $clientes[(int)$pq['id_cliente']] ?? 'Cliente',
                ]);
            }
            usort($out, static fn($a, $b) => $b['id_pqrs'] <=> $a['id_pqrs']);
            return $out;
        }, false);
    }

    public function create(array $data): int {
        $tipo = strtoupper(trim((string)$data['tipo']));
        $descripcion = (string)$data['descripcion'];
        $idCliente = (int)$data['id_cliente'];
        $idPedido = isset($data['id_pedido']) ? (int)$data['id_pedido'] : null;

        if ($this->isDbConnected()) {
            $row = $this->fetchOne(
                "INSERT INTO pqrs (tipo, descripcion, id_cliente, id_pedido, estado)
                 VALUES (:tipo, :descripcion, :id_cliente, :id_pedido, 'ABIERTA')
                 RETURNING id_pqrs",
                [
                    ':tipo'        => $tipo,
                    ':descripcion' => $descripcion,
                    ':id_cliente'  => $idCliente,
                    ':id_pedido'   => $idPedido,
                ]
            );
            return (int)($row['id_pqrs'] ?? 0);
        }

        return NormalizedStore::withTransaction(static function (array &$state) use ($tipo, $descripcion, $idCliente, $idPedido): int {
            if ($idPedido !== null) {
                $pedido = null;
                foreach ($state['pedido'] as $p) {
                    if ((int)$p['id_pedido'] === $idPedido) {
                        $pedido = $p;
                        break;
                    }
                }
                if (!$pedido) {
                    throw new DomainValidationException('El pedido asociado a la PQRS no existe.');
                }
                if ((int)$pedido['id_cliente'] !== $idCliente) {
                    throw new DomainConflictException('El titular del pedido no coincide con el cliente de la solicitud PQRS.');
                }
            }

            $maxId = 0;
            foreach ($state['pqrs'] as $pq) {
                $maxId = max($maxId, (int)$pq['id_pqrs']);
            }
            $newId = $maxId + 1;

            $state['pqrs'][] = [
                'id_pqrs'        => $newId,
                'tipo'           => $tipo,
                'descripcion'    => $descripcion,
                'estado'         => 'ABIERTA',
                'fecha_creacion' => date('c'),
                'id_cliente'     => $idCliente,
                'id_pedido'      => $idPedido,
            ];

            return $newId;
        }, true);
    }
}
