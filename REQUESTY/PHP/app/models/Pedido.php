<?php
/**
 * Modelo de Pedidos (PostgreSQL 17: detalle, personalizacion 1FN, descuentos, inventario y notificaciones).
 */
class Pedido extends Model {
    public function getAll(?int $idMesero = null, ?int $idCliente = null, int $limit = 100, int $offset = 0): array {
        $limit = max(1, min($limit, 200));
        $offset = max(0, $offset);
        $sql = "SELECT p.id_pedido, p.fecha_hora, p.estado, p.observaciones,
                       p.subtotal, p.descuento_total, p.impuesto_total, p.total,
                       p.id_cliente, p.id_mesa, p.id_mesero,
                       m.numero_mesa, c.nombre AS cliente_nombre, c.correo AS cliente_correo,
                       u.nombre AS mesero_nombre, u.correo AS mesero_correo,
                       vc.total_pagado, vc.saldo_pendiente, vc.total_propinas,
                       vc.id_factura_activa, vc.numero_factura, vc.estado_dian
                FROM pedido p
                LEFT JOIN mesa m ON p.id_mesa = m.id_mesa
                LEFT JOIN vw_clientes_publico c ON p.id_cliente = c.id_cliente
                LEFT JOIN vw_usuarios_publico u ON p.id_mesero = u.id_usuario
                LEFT JOIN vw_conciliacion_pedidos vc ON vc.id_pedido = p.id_pedido";

        $conds = [];
        $params = [];
        if ($idMesero !== null) {
            $conds[] = 'p.id_mesero = :id_mesero';
            $params[':id_mesero'] = $idMesero;
        }
        if ($idCliente !== null) {
            $conds[] = 'p.id_cliente = :id_cliente';
            $params[':id_cliente'] = $idCliente;
        }
        if (!empty($conds)) {
            $sql .= ' WHERE ' . implode(' AND ', $conds);
        }
        $sql .= ' ORDER BY p.id_pedido DESC LIMIT :limit OFFSET :offset';
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        $pedidos = $this->fetchAll($sql, $params);
        return $this->hydrateDetailsAndDiscounts($pedidos);
    }

    public function getById(int $id): ?array {
        $sql = "SELECT p.*, m.numero_mesa, c.nombre AS cliente_nombre, c.correo AS cliente_correo,
                       u.nombre AS mesero_nombre, u.correo AS mesero_correo,
                       vc.total_pagado, vc.saldo_pendiente, vc.total_propinas,
                       vc.id_factura_activa, vc.numero_factura, vc.estado_dian
                FROM pedido p
                LEFT JOIN mesa m ON p.id_mesa = m.id_mesa
                LEFT JOIN vw_clientes_publico c ON p.id_cliente = c.id_cliente
                LEFT JOIN vw_usuarios_publico u ON p.id_mesero = u.id_usuario
                LEFT JOIN vw_conciliacion_pedidos vc ON vc.id_pedido = p.id_pedido
                WHERE p.id_pedido = :id
                LIMIT 1";
        $row = $this->fetchOne($sql, [':id' => $id]);
        if (!$row) {
            return null;
        }
        $hydrated = $this->hydrateDetailsAndDiscounts([$row]);
        return $hydrated[0] ?? $row;
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
            $this->insertItemsBatch($idPedido, $items);
            $this->commit();
            return $this->getById($idPedido) ?? ['id_pedido' => $idPedido];
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function updateItems(int $idPedido, array $items, ?string $observaciones = null, ?int $idMesa = null, ?int $idCliente = null): array {
        $this->beginTransaction();
        try {
            $this->execute('DELETE FROM detalle_pedido WHERE id_pedido = :id', [':id' => $idPedido]);
            $this->insertItemsBatch($idPedido, $items);
            $this->execute(
                'UPDATE pedido
                    SET observaciones = COALESCE(:obs, observaciones),
                        id_mesa = COALESCE(:mesa, id_mesa),
                        id_cliente = COALESCE(:cli, id_cliente)
                  WHERE id_pedido = :id',
                [
                    ':obs'  => $observaciones,
                    ':mesa' => $idMesa,
                    ':cli'  => $idCliente,
                    ':id'   => $idPedido,
                ]
            );
            $this->commit();
            return $this->getById($idPedido) ?? ['id_pedido' => $idPedido];
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function updateStatus(int $id, string $estado, ?int $actorUserId = null): array {
        $estadoNorm = strtoupper(trim($estado));
        $this->beginTransaction();
        try {
            $pedido = $this->fetchOne('SELECT * FROM pedido WHERE id_pedido = :id FOR UPDATE', [':id' => $id]);
            if (!$pedido) {
                throw new DomainValidationException('Pedido no encontrado.');
            }

            $estadoPrevio = (string)$pedido['estado'];
            $this->execute(
                'UPDATE pedido SET estado = :estado WHERE id_pedido = :id',
                [':estado' => $estadoNorm, ':id' => $id]
            );

            if ($estadoPrevio === 'PENDIENTE' && $estadoNorm === 'EN_PREPARACION') {
                $this->deductInventoryForOrder($id, $actorUserId ?? (int)$pedido['id_mesero']);
            }

            if ($estadoNorm === 'LISTO') {
                $mesaTxt = $pedido['id_mesa'] ? " (Mesa #{$pedido['id_mesa']})" : '';
                $this->execute(
                    "INSERT INTO notificacion (mensaje, tipo, id_usuario, id_pedido)
                     VALUES (:msg, 'PEDIDO', :usr, :ped)",
                    [
                        ':msg' => "Pedido #{$id} listo para entregar{$mesaTxt}",
                        ':usr' => (int)$pedido['id_mesero'],
                        ':ped' => $id,
                    ]
                );
            }

            $this->commit();
            return $this->getById($id) ?? ['id_pedido' => $id, 'estado' => $estadoNorm];
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function applyDiscount(int $idPedido, int $idDescuento, int $autorizadoPor): array {
        $desc = $this->fetchOne('SELECT id_descuento, porcentaje, activo FROM descuento WHERE id_descuento = :id', [':id' => $idDescuento]);
        if (!$desc || !$desc['activo']) {
            throw new DomainValidationException('El descuento seleccionado no existe o esta inactivo.');
        }
        $this->execute(
            'INSERT INTO pedido_descuento (id_pedido, id_descuento, autorizado_por, porcentaje_aplicado)
             VALUES (:ped, :desc, :aut, :pct)',
            [
                ':ped'  => $idPedido,
                ':desc' => $idDescuento,
                ':aut'  => $autorizadoPor,
                ':pct'  => $desc['porcentaje'],
            ]
        );
        return $this->getById($idPedido) ?? ['id_pedido' => $idPedido];
    }

    public function removeDiscount(int $idPedido, int $idDescuento): array {
        $this->execute(
            'DELETE FROM pedido_descuento WHERE id_pedido = :ped AND id_descuento = :desc',
            [':ped' => $idPedido, ':desc' => $idDescuento]
        );
        return $this->getById($idPedido) ?? ['id_pedido' => $idPedido];
    }

    private function insertItemsBatch(int $idPedido, array $items): void {
        $stmtProd = $this->db->prepare(
            'SELECT id_producto, precio, impuesto_porcentaje, disponible FROM producto WHERE id_producto = :id LIMIT 1'
        );
        $stmtDet = $this->db->prepare(
            'INSERT INTO detalle_pedido (id_pedido, id_producto, cantidad, precio_unitario, impuesto_porcentaje, observaciones)
             VALUES (:id_pedido, :id_producto, :cantidad, :precio, :impuesto, :obs)
             RETURNING id_detalle'
        );
        $stmtPers = $this->db->prepare(
            'INSERT INTO personalizacion_pedido (id_detalle, cantidad, termino_coccion, observaciones)
             VALUES (:id_det, :cant, :termino, :obs) RETURNING id_personalizacion'
        );
        $stmtPersIng = $this->db->prepare(
            'INSERT INTO personalizacion_ingrediente (id_personalizacion, id_ingrediente, tipo_ajuste, cantidad, costo_extra)
             VALUES (:id_pers, :id_ing, :tipo, :cant, :costo)
             ON CONFLICT (id_personalizacion, id_ingrediente, tipo_ajuste) DO UPDATE
                SET cantidad = EXCLUDED.cantidad, costo_extra = EXCLUDED.costo_extra'
        );
        $stmtFindIng = $this->db->prepare('SELECT id_ingrediente FROM ingrediente WHERE lower(nombre) = lower(:n) LIMIT 1');

        foreach ($items as $item) {
            $idProd = (int)($item['id_producto'] ?? 0);
            $cant = (int)($item['cantidad'] ?? 0);
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
            $detRow = $stmtDet->fetch();
            $idDetalle = (int)$detRow['id_detalle'];

            $termino = isset($item['termino_coccion']) ? trim((string)$item['termino_coccion']) : '';
            $persObs = isset($item['observaciones']) ? trim((string)$item['observaciones']) : '';
            $excluidos = is_array($item['ingredientes_excluidos'] ?? null) ? $item['ingredientes_excluidos'] : [];
            $extras = is_array($item['extras'] ?? null) ? $item['extras'] : [];

            if ($termino !== '' || $persObs !== '' || !empty($excluidos) || !empty($extras)) {
                $stmtPers->execute([
                    ':id_det'  => $idDetalle,
                    ':cant'    => $cant,
                    ':termino' => $termino !== '' ? $termino : null,
                    ':obs'     => $persObs !== '' ? $persObs : null,
                ]);
                $persRow = $stmtPers->fetch();
                $idPers = (int)$persRow['id_personalizacion'];

                foreach ($excluidos as $excl) {
                    $idIng = $this->resolveIngredienteId($excl, $stmtFindIng);
                    if ($idIng > 0) {
                        $stmtPersIng->execute([
                            ':id_pers' => $idPers,
                            ':id_ing'  => $idIng,
                            ':tipo'    => 'ELIMINAR',
                            ':cant'    => 1,
                            ':costo'   => 0,
                        ]);
                    }
                }
                foreach ($extras as $ext) {
                    $idIng = $this->resolveIngredienteId($ext['id_ingrediente'] ?? ($ext['nombre'] ?? ''), $stmtFindIng);
                    $extCant = max(1, (float)($ext['cantidad'] ?? 1));
                    $costoExtra = max(0, (float)($ext['costo_extra'] ?? ($ext['precio'] ?? 0)));
                    if ($idIng > 0) {
                        $stmtPersIng->execute([
                            ':id_pers' => $idPers,
                            ':id_ing'  => $idIng,
                            ':tipo'    => 'EXTRA',
                            ':cant'    => $extCant,
                            ':costo'   => $costoExtra,
                        ]);
                    }
                }
            }
        }
    }

    private function resolveIngredienteId($val, PDOStatement $stmtFind): int {
        if (is_int($val) || (is_string($val) && ctype_digit($val))) {
            return (int)$val;
        }
        $nombre = trim((string)$val);
        if ($nombre === '') {
            return 0;
        }
        $stmtFind->execute([':n' => $nombre]);
        $row = $stmtFind->fetch();
        return $row ? (int)$row['id_ingrediente'] : 0;
    }

    private function deductInventoryForOrder(int $idPedido, int $idUsuario): void {
        $yaDescontado = $this->fetchOne(
            "SELECT COUNT(*) AS cnt
             FROM inventario inv
             JOIN detalle_pedido dp ON dp.id_detalle = inv.id_detalle
             WHERE dp.id_pedido = :id AND inv.tipo_movimiento = 'SALIDA'",
            [':id' => $idPedido]
        );
        if ((int)($yaDescontado['cnt'] ?? 0) > 0) {
            return;
        }

        $detalles = $this->fetchAll('SELECT id_detalle, id_producto, cantidad FROM detalle_pedido WHERE id_pedido = :id', [':id' => $idPedido]);
        if (empty($detalles)) {
            return;
        }

        $stmtReceta = $this->db->prepare(
            'SELECT pi.id_ingrediente, pi.cantidad
             FROM producto_ingrediente pi
             WHERE pi.id_producto = :prod'
        );
        $stmtPers = $this->db->prepare(
            "SELECT pin.id_ingrediente, pin.tipo_ajuste, pin.cantidad, pp.cantidad AS cant_pers
             FROM personalizacion_pedido pp
             JOIN personalizacion_ingrediente pin ON pin.id_personalizacion = pp.id_personalizacion
             WHERE pp.id_detalle = :det"
        );
        $stmtInsMov = $this->db->prepare(
            "INSERT INTO inventario (id_ingrediente, tipo_movimiento, cantidad, motivo, id_usuario, id_detalle)
             VALUES (:ing, 'SALIDA', :cant, :motivo, :usr, :det)"
        );
        $stmtCheckIng = $this->db->prepare(
            'SELECT id_ingrediente, nombre, stock_actual, stock_minimo, unidad_medida FROM ingrediente WHERE id_ingrediente = :id'
        );
        $stmtAdmins = $this->db->query("SELECT id_usuario FROM vw_usuarios_publico WHERE upper(rol_nombre) = 'ADMIN' AND estado = 'ACTIVO'");
        $adminIds = array_column($stmtAdmins->fetchAll(), 'id_usuario');
        $stmtNotif = $this->db->prepare(
            "INSERT INTO notificacion (mensaje, tipo, id_usuario, id_ingrediente) VALUES (:m, 'INVENTARIO', :u, :i)"
        );

        foreach ($detalles as $det) {
            $idDet = (int)$det['id_detalle'];
            $porciones = (float)$det['cantidad'];

            $stmtReceta->execute([':prod' => (int)$det['id_producto']]);
            $consumos = [];
            foreach ($stmtReceta->fetchAll() as $r) {
                $consumos[(int)$r['id_ingrediente']] = (float)$r['cantidad'] * $porciones;
            }

            $stmtPers->execute([':det' => $idDet]);
            foreach ($stmtPers->fetchAll() as $aj) {
                $idIng = (int)$aj['id_ingrediente'];
                if ($aj['tipo_ajuste'] === 'ELIMINAR') {
                    unset($consumos[$idIng]);
                } elseif ($aj['tipo_ajuste'] === 'EXTRA') {
                    $consumos[$idIng] = ($consumos[$idIng] ?? 0) + ((float)$aj['cantidad'] * (float)$aj['cant_pers']);
                }
            }

            foreach ($consumos as $idIng => $cant) {
                if ($cant <= 0) {
                    continue;
                }
                $stmtInsMov->execute([
                    ':ing'    => $idIng,
                    ':cant'   => round($cant, 3),
                    ':motivo' => "Consumo pedido #{$idPedido}",
                    ':usr'    => $idUsuario,
                    ':det'    => $idDet,
                ]);

                $stmtCheckIng->execute([':id' => $idIng]);
                $ingRow = $stmtCheckIng->fetch();
                if ($ingRow && (float)$ingRow['stock_actual'] <= (float)$ingRow['stock_minimo']) {
                    foreach ($adminIds as $aid) {
                        $stmtNotif->execute([
                            ':m' => "Stock bajo de {$ingRow['nombre']}: {$ingRow['stock_actual']} {$ingRow['unidad_medida']}",
                            ':u' => (int)$aid,
                            ':i' => $idIng,
                        ]);
                    }
                }
            }
        }
    }

    private function hydrateDetailsAndDiscounts(array $pedidos): array {
        if (empty($pedidos)) {
            return [];
        }
        $ids = array_values(array_unique(array_map(static fn($p) => (int)$p['id_pedido'], $pedidos)));
        $params = [];
        $placeholders = [];
        foreach ($ids as $idx => $idPedido) {
            $ph = ':pid' . $idx;
            $placeholders[] = $ph;
            $params[$ph] = $idPedido;
        }
        $inClause = implode(', ', $placeholders);

        $detRows = $this->fetchAll(
            "SELECT dp.id_detalle, dp.id_pedido, dp.id_producto, dp.cantidad,
                    dp.precio_unitario, dp.impuesto_porcentaje, dp.subtotal, dp.observaciones,
                    pr.nombre AS producto_nombre, c.nombre AS categoria
             FROM detalle_pedido dp
             JOIN producto pr ON pr.id_producto = dp.id_producto
             LEFT JOIN categoria c ON c.id_categoria = pr.id_categoria
             WHERE dp.id_pedido IN ({$inClause})
             ORDER BY dp.id_detalle ASC",
            $params
        );

        $persRows = $this->fetchAll(
            "SELECT pp.id_personalizacion, pp.id_detalle, pp.termino_coccion, pp.observaciones,
                    pin.id_ingrediente, i.nombre AS ingrediente_nombre, pin.tipo_ajuste, pin.cantidad, pin.costo_extra
             FROM personalizacion_pedido pp
             LEFT JOIN personalizacion_ingrediente pin ON pin.id_personalizacion = pp.id_personalizacion
             LEFT JOIN ingrediente i ON i.id_ingrediente = pin.id_ingrediente
             JOIN detalle_pedido dp ON dp.id_detalle = pp.id_detalle
             WHERE dp.id_pedido IN ({$inClause})",
            $params
        );

        $persPorDet = [];
        foreach ($persRows as $pr) {
            $idDet = (int)$pr['id_detalle'];
            if (!isset($persPorDet[$idDet])) {
                $persPorDet[$idDet] = [
                    'termino_coccion'        => $pr['termino_coccion'],
                    'observaciones'          => $pr['observaciones'],
                    'ingredientes_excluidos' => [],
                    'extras'                 => [],
                ];
            }
            if ($pr['tipo_ajuste'] === 'ELIMINAR' && $pr['ingrediente_nombre']) {
                $persPorDet[$idDet]['ingredientes_excluidos'][] = $pr['ingrediente_nombre'];
            } elseif ($pr['tipo_ajuste'] === 'EXTRA' && $pr['ingrediente_nombre']) {
                $persPorDet[$idDet]['extras'][] = [
                    'id_ingrediente' => (int)$pr['id_ingrediente'],
                    'nombre'         => $pr['ingrediente_nombre'],
                    'cantidad'       => (float)$pr['cantidad'],
                    'costo_extra'    => (float)$pr['costo_extra'],
                ];
            }
        }

        $itemsPorPed = [];
        foreach ($detRows as $d) {
            $idDet = (int)$d['id_detalle'];
            $pers = $persPorDet[$idDet] ?? [
                'termino_coccion'        => null,
                'observaciones'          => null,
                'ingredientes_excluidos' => [],
                'extras'                 => [],
            ];
            $d['termino_coccion'] = $pers['termino_coccion'];
            $d['ingredientes_excluidos'] = $pers['ingredientes_excluidos'];
            $d['extras'] = $pers['extras'];
            $itemsPorPed[(int)$d['id_pedido']][] = $d;
        }

        $descRows = $this->fetchAll(
            "SELECT pd.id_pedido_descuento, pd.id_pedido, pd.id_descuento, pd.porcentaje_aplicado,
                    pd.monto_descontado, d.nombre AS descuento_nombre
             FROM pedido_descuento pd
             JOIN descuento d ON d.id_descuento = pd.id_descuento
             WHERE pd.id_pedido IN ({$inClause})",
            $params
        );
        $descPorPed = [];
        foreach ($descRows as $dr) {
            $descPorPed[(int)$dr['id_pedido']][] = $dr;
        }

        foreach ($pedidos as &$ped) {
            $pid = (int)$ped['id_pedido'];
            $ped['items'] = $itemsPorPed[$pid] ?? [];
            $ped['descuentos'] = $descPorPed[$pid] ?? [];
        }

        return $pedidos;
    }
}
