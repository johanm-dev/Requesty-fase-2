<?php
/**
 * Modelo de Pedidos (PostgreSQL 17: maquina de estados + calculo automatico de totales).
 */
class Pedido extends Model {
    public function getAll(?int $idMesero = null): array {
        $sql = "SELECT p.id_pedido, p.fecha_hora, p.estado, p.observaciones,
                       p.subtotal, p.descuento_total, p.impuesto_total, p.total,
                       p.id_cliente, p.id_mesa, p.id_mesero,
                       m.numero_mesa, c.nombre AS cliente_nombre, u.nombre AS mesero_nombre,
                       vc.total_pagado, vc.saldo_pendiente, vc.numero_factura, vc.estado_dian
                FROM pedido p
                LEFT JOIN mesa m ON p.id_mesa = m.id_mesa
                LEFT JOIN vw_clientes_publico c ON p.id_cliente = c.id_cliente
                LEFT JOIN vw_usuarios_publico u ON p.id_mesero = u.id_usuario
                LEFT JOIN vw_conciliacion_pedidos vc ON vc.id_pedido = p.id_pedido";

        $params = [];
        if ($idMesero !== null) {
            $sql .= " WHERE p.id_mesero = :id_mesero";
            $params[':id_mesero'] = $idMesero;
        }
        $sql .= " ORDER BY p.id_pedido DESC";

        return $this->fetchAll($sql, $params);
    }

    public function getById(int $id): ?array {
        $sql = "SELECT p.*, m.numero_mesa, c.nombre AS cliente_nombre,
                       vc.total_pagado, vc.saldo_pendiente, vc.numero_factura, vc.estado_dian
                FROM pedido p
                LEFT JOIN mesa m ON p.id_mesa = m.id_mesa
                LEFT JOIN vw_clientes_publico c ON p.id_cliente = c.id_cliente
                LEFT JOIN vw_conciliacion_pedidos vc ON vc.id_pedido = p.id_pedido
                WHERE p.id_pedido = :id
                LIMIT 1";
        return $this->fetchOne($sql, [':id' => $id]);
    }

    public function createWithItems(int $idMesero, ?int $idCliente, ?int $idMesa, array $items, ?string $observaciones = null): array {
        $this->beginTransaction();
        try {
            $row = $this->fetchOne(
                "INSERT INTO pedido (estado, observaciones, id_cliente, id_mesa, id_mesero)
                 VALUES ('PENDIENTE', :obs, :id_cliente, :id_mesa, :id_mesero)
                 RETURNING id_pedido",
                [
                    ':obs'        => $observaciones,
                    ':id_cliente' => $idCliente,
                    ':id_mesa'    => $idMesa,
                    ':id_mesero'  => $idMesero,
                ]
            );
            $idPedido = (int)$row['id_pedido'];

            $stmtProd = $this->db->prepare(
                'SELECT precio, impuesto_porcentaje, disponible FROM producto WHERE id_producto = :id LIMIT 1'
            );
            $stmtDet = $this->db->prepare(
                'INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario, impuesto_porcentaje, observaciones)
                 VALUES (:id_pedido, :id_producto, :cantidad, :precio, :impuesto, :obs)'
            );

            foreach ($items as $item) {
                $idProd = (int)($item['id_producto'] ?? 0);
                $cant   = (int)($item['cantidad'] ?? 0);
                if ($idProd <= 0 || $cant <= 0) {
                    throw new DomainValidationException('Cada item requiere id_producto y cantidad positivos.');
                }
                $stmtProd->execute([':id' => $idProd]);
                $prod = $stmtProd->fetch();
                if (!$prod) {
                    throw new DomainValidationException("Producto {$idProd} no existe.");
                }
                $stmtDet->execute([
                    ':id_pedido'   => $idPedido,
                    ':id_producto' => $idProd,
                    ':cantidad'    => $cant,
                    ':precio'      => $prod['precio'],
                    ':impuesto'    => $prod['impuesto_porcentaje'],
                    ':obs'         => $item['observaciones'] ?? null,
                ]);
            }

            $this->commit();
            return $this->getById($idPedido) ?? ['id_pedido' => $idPedido];
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function updateStatus(int $id, string $estado): array {
        $this->execute(
            'UPDATE pedido SET estado = :estado WHERE id_pedido = :id',
            [':estado' => strtoupper(trim($estado)), ':id' => $id]
        );
        return $this->getById($id) ?? ['id_pedido' => $id, 'estado' => $estado];
    }
}
