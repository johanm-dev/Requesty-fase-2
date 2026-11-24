<?php
/**
 * Modelo de PQRS (PostgreSQL 17: lista, registro y cambio de estado).
 */
class Pqrs extends Model {
    public function getAll(?int $idCliente = null): array {
        $sql = "SELECT pq.id_pqrs, pq.tipo, pq.descripcion, pq.fecha, pq.estado,
                       pq.id_cliente, pq.id_pedido,
                       c.nombre AS cliente_nombre, c.correo AS cliente_correo
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

    public function getById(int $id): ?array {
        return $this->fetchOne(
            "SELECT pq.*, c.nombre AS cliente_nombre, c.correo AS cliente_correo
             FROM pqrs pq
             LEFT JOIN vw_clientes_publico c ON pq.id_cliente = c.id_cliente
             WHERE pq.id_pqrs = :id LIMIT 1",
            [':id' => $id]
        );
    }

    public function create(array $data): int {
        $row = $this->fetchOne(
            "INSERT INTO pqrs (tipo, descripcion, id_cliente, id_pedido, estado)
             VALUES (:tipo, :descripcion, :id_cliente, :id_pedido, 'ABIERTA')
             RETURNING id_pqrs",
            [
                ':tipo'        => strtoupper(trim((string)$data['tipo'])),
                ':descripcion' => $data['descripcion'],
                ':id_cliente'  => (int)$data['id_cliente'],
                ':id_pedido'   => isset($data['id_pedido']) ? (int)$data['id_pedido'] : null,
            ]
        );
        return (int)($row['id_pqrs'] ?? 0);
    }

    public function updateEstado(int $idPqrs, string $estado): array {
        $actual = $this->getById($idPqrs);
        if (!$actual) {
            throw new DomainValidationException('PQRS no encontrado.');
        }
        $this->execute(
            'UPDATE pqrs SET estado = :estado WHERE id_pqrs = :id',
            [':estado' => strtoupper(trim($estado)), ':id' => $idPqrs]
        );
        return $this->getById($idPqrs) ?? ['id_pqrs' => $idPqrs, 'estado' => $estado];
    }
}
