<?php
/**
 * Modelo de PQRS (PostgreSQL 17).
 */
class Pqrs extends Model {
    public function getAll(?int $idCliente = null): array {
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
}
