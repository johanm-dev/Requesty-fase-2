<?php
/**
 * Modelo de Pedidos (PostgreSQL 17: maquina de estados estricta + calculo automatico fiscal + 1FN personalizacion_ingrediente + pedido_descuento).
 */
class Pedido extends Model {
    public function getAll(?int $idMesero = null): array {
        if ($this->isDbConnected()) {
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

        return NormalizedStore::withTransaction(static function (array &$state) use ($idMesero): array {
            $conc = NormalizedStore::viewConciliacionPedidos($state);
            $mesas = [];
            foreach ($state['mesa'] as $m) {
                $mesas[(int)$m['id_mesa']] = $m['numero_mesa'];
            }
            $clientes = [];
            foreach ($state['cliente'] as $c) {
                $clientes[(int)$c['id_cliente']] = $c['nombre'];
            }
            $usuarios = [];
            foreach ($state['usuario'] as $u) {
                $usuarios[(int)$u['id_usuario']] = $u['nombre'];
            }

            $out = [];
            foreach ($state['pedido'] as $p) {
                if ($idMesero !== null && (int)$p['id_mesero'] !== $idMesero) {
                    continue;
                }
                $idPed = (int)$p['id_pedido'];
                $vc = $conc[$idPed] ?? [];
                $out[] = array_merge($p, [
                    'numero_mesa'     => $mesas[(int)($p['id_mesa'] ?? 0)] ?? null,
                    'cliente_nombre'  => $clientes[(int)($p['id_cliente'] ?? 0)] ?? null,
                    'mesero_nombre'   => $usuarios[(int)($p['id_mesero'] ?? 0)] ?? null,
                    'total_pagado'    => $vc['total_pagado'] ?? 0.0,
                    'saldo_pendiente' => $vc['saldo_pendiente'] ?? (float)$p['total'],
                    'numero_factura'  => $vc['numero_factura'] ?? null,
                    'estado_dian'     => $vc['estado_dian'] ?? null,
                ]);
            }
            usort($out, static fn($a, $b) => $b['id_pedido'] <=> $a['id_pedido']);
            return $out;
        }, false);
    }

    public function getById(int $id): ?array {
        if ($this->isDbConnected()) {
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

        foreach ($this->getAll() as $p) {
            if ((int)$p['id_pedido'] === $id) {
                return $p;
            }
        }
        return null;
    }

    public function createWithItems(int $idMesero, ?int $idCliente, ?int $idMesa, array $items, ?string $observaciones = null): array {
        if ($this->isDbConnected()) {
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

        $idPedido = NormalizedStore::withTransaction(static function (array &$state) use ($idMesero, $idCliente, $idMesa, $items, $observaciones): int {
            $prods = [];
            foreach ($state['producto'] as $pr) {
                $prods[(int)$pr['id_producto']] = $pr;
            }

            $maxPedId = 0;
            foreach ($state['pedido'] as $p) {
                $maxPedId = max($maxPedId, (int)$p['id_pedido']);
            }
            $newPedId = $maxPedId + 1;

            $maxDetId = 0;
            foreach ($state['detalle_pedido'] as $d) {
                $maxDetId = max($maxDetId, (int)$d['id_detalle']);
            }

            $subtotal = 0.0;
            $impuestoTotal = 0.0;

            foreach ($items as $item) {
                $idProd = (int)($item['id_producto'] ?? 0);
                $cant   = (int)($item['cantidad'] ?? 0);
                if ($idProd <= 0 || $cant <= 0) {
                    throw new DomainValidationException('Cada item requiere id_producto y cantidad positivos.');
                }
                if (!isset($prods[$idProd])) {
                    throw new DomainValidationException("Producto {$idProd} no existe.");
                }
                $pr = $prods[$idProd];
                $precio = (float)$pr['precio'];
                $impPct = (float)$pr['impuesto_porcentaje'];
                $lineaSub = round($precio * $cant, 2);
                $lineaImp = round($lineaSub * ($impPct / 100.0), 2);
                $subtotal += $lineaSub;
                $impuestoTotal += $lineaImp;

                $maxDetId++;
                $state['detalle_pedido'][] = [
                    'id_detalle'          => $maxDetId,
                    'id_pedido'           => $newPedId,
                    'id_producto'         => $idProd,
                    'cantidad'            => $cant,
                    'precio_unitario'     => $precio,
                    'impuesto_porcentaje' => $impPct,
                    'observaciones'       => $item['observaciones'] ?? null,
                ];
            }

            $subtotal = round($subtotal, 2);
            $impuestoTotal = round($impuestoTotal, 2);
            $total = round($subtotal + $impuestoTotal, 2);

            $state['pedido'][] = [
                'id_pedido'       => $newPedId,
                'fecha_hora'      => date('c'),
                'estado'          => 'PENDIENTE',
                'observaciones'   => $observaciones,
                'subtotal'        => $subtotal,
                'descuento_total' => 0.00,
                'impuesto_total'  => $impuestoTotal,
                'total'           => $total,
                'id_cliente'      => $idCliente,
                'id_mesa'         => $idMesa,
                'id_mesero'       => $idMesero,
            ];

            return $newPedId;
        }, true);

        return $this->getById($idPedido) ?? ['id_pedido' => $idPedido];
    }

    public function updateStatus(int $id, string $estado): array {
        $nuevoEstado = strtoupper(trim($estado));
        if ($this->isDbConnected()) {
            $this->execute(
                'UPDATE pedido SET estado = :estado WHERE id_pedido = :id',
                [':estado' => $nuevoEstado, ':id' => $id]
            );
            return $this->getById($id) ?? ['id_pedido' => $id, 'estado' => $nuevoEstado];
        }

        NormalizedStore::withTransaction(static function (array &$state) use ($id, $nuevoEstado): void {
            $foundIdx = null;
            foreach ($state['pedido'] as $idx => $p) {
                if ((int)$p['id_pedido'] === $id) {
                    $foundIdx = $idx;
                    break;
                }
            }
            if ($foundIdx === null) {
                throw new DomainValidationException('Pedido no encontrado.');
            }

            $actual = $state['pedido'][$foundIdx]['estado'];
            if ($actual === $nuevoEstado) {
                return;
            }

            if (in_array($actual, ['PAGADO', 'CANCELADO'], true)) {
                throw new DomainConflictException("El pedido se encuentra en estado terminal ({$actual}).");
            }

            $validTransitions = [
                'PENDIENTE'      => ['EN_PREPARACION', 'CANCELADO'],
                'EN_PREPARACION' => ['LISTO', 'CANCELADO'],
                'LISTO'          => ['ENTREGADO', 'CANCELADO'],
                'ENTREGADO'      => ['PAGADO', 'CANCELADO'],
            ];

            $allowed = $validTransitions[$actual] ?? [];
            if (!in_array($nuevoEstado, $allowed, true)) {
                throw new DomainConflictException("Transicion de estado invalida: {$actual} -> {$nuevoEstado}.");
            }

            $conc = NormalizedStore::viewConciliacionPedidos($state)[$id] ?? null;

            if ($nuevoEstado === 'CANCELADO') {
                if ($conc && ($conc['total_pagado'] > 0 || $conc['estado_dian'] !== null)) {
                    throw new DomainConflictException('No se puede cancelar un pedido con pagos o factura activa.');
                }
            }

            if ($nuevoEstado === 'PAGADO') {
                if (!$conc || $conc['saldo_pendiente'] > 0.009) {
                    throw new DomainConflictException('El pedido aun tiene saldo pendiente por pagar.');
                }
            }

            $state['pedido'][$foundIdx]['estado'] = $nuevoEstado;
        }, true);

        return $this->getById($id) ?? ['id_pedido' => $id, 'estado' => $nuevoEstado];
    }

    /**
     * Registra personalizacion_pedido y personalizacion_ingrediente (1FN) recalculando totales.
     */
    public function agregarPersonalizacion(array $data): array {
        $idDetalle = (int)$data['id_detalle'];
        $cantidad = (int)($data['cantidad'] ?? 1);
        $termino = $data['termino_coccion'] ?? null;
        $obs = $data['observaciones'] ?? null;
        $ajustes = is_array($data['ingredientes'] ?? null) ? $data['ingredientes'] : [];

        if ($this->isDbConnected()) {
            $this->beginTransaction();
            try {
                $row = $this->fetchOne(
                    "INSERT INTO personalizacion_pedido (id_detalle, cantidad, termino_coccion, observaciones)
                     VALUES (:det, :cant, :term, :obs) RETURNING id_personalizacion",
                    [':det' => $idDetalle, ':cant' => $cantidad, ':term' => $termino, ':obs' => $obs]
                );
                $idPers = (int)$row['id_personalizacion'];
                foreach ($ajustes as $aj) {
                    $this->execute(
                        "INSERT INTO personalizacion_ingrediente (id_personalizacion, id_ingrediente, tipo_ajuste, cantidad, costo_extra)
                         VALUES (:id_p, :id_i, :tipo, :cant, :costo)",
                        [
                            ':id_p'  => $idPers,
                            ':id_i'  => (int)$aj['id_ingrediente'],
                            ':tipo'  => strtoupper((string)$aj['tipo_ajuste']),
                            ':cant'  => (float)($aj['cantidad'] ?? 1),
                            ':costo' => (float)($aj['costo_extra'] ?? 0),
                        ]
                    );
                }
                $this->commit();
                return ['id_personalizacion' => $idPers];
            } catch (Throwable $e) {
                $this->rollBack();
                throw $e;
            }
        }

        return NormalizedStore::withTransaction(static function (array &$state) use ($idDetalle, $cantidad, $termino, $obs, $ajustes): array {
            $detalle = null;
            foreach ($state['detalle_pedido'] as $d) {
                if ((int)$d['id_detalle'] === $idDetalle) {
                    $detalle = $d;
                    break;
                }
            }
            if (!$detalle) {
                throw new DomainValidationException('Detalle de pedido no existe.');
            }

            $yaPersonalizadas = 0;
            foreach ($state['personalizacion_pedido'] as $pp) {
                if ((int)$pp['id_detalle'] === $idDetalle) {
                    $yaPersonalizadas += (int)$pp['cantidad'];
                }
            }
            if (($yaPersonalizadas + $cantidad) > (int)$detalle['cantidad']) {
                throw new DomainConflictException('La cantidad personalizada excede las unidades del detalle de pedido.');
            }

            $maxPersId = 0;
            foreach ($state['personalizacion_pedido'] as $pp) {
                $maxPersId = max($maxPersId, (int)$pp['id_personalizacion']);
            }
            $newPersId = $maxPersId + 1;

            $state['personalizacion_pedido'][] = [
                'id_personalizacion' => $newPersId,
                'id_detalle'         => $idDetalle,
                'cantidad'           => $cantidad,
                'termino_coccion'    => $termino,
                'observaciones'      => $obs,
            ];

            $costoExtraTotal = 0.0;
            foreach ($ajustes as $aj) {
                $tipo = strtoupper((string)$aj['tipo_ajuste']);
                $costo = ($tipo === 'ELIMINAR') ? 0.0 : round((float)($aj['costo_extra'] ?? 0), 2);
                $cantIng = round((float)($aj['cantidad'] ?? 1), 2);
                $state['personalizacion_ingrediente'][] = [
                    'id_personalizacion' => $newPersId,
                    'id_ingrediente'     => (int)$aj['id_ingrediente'],
                    'tipo_ajuste'        => $tipo,
                    'cantidad'           => $cantIng,
                    'costo_extra'        => $costo,
                ];
                $costoExtraTotal += ($costo * $cantIng);
            }

            self::recalculateOrderTotalsInState($state, (int)$detalle['id_pedido']);
            return ['id_personalizacion' => $newPersId, 'costo_extra_agregado' => $costoExtraTotal];
        }, true);
    }

    /**
     * Aplica un descuento en pedido_descuento y recalcula subtotal, descuento_total, impuesto_total y total.
     */
    public function aplicarDescuento(int $idPedido, int $idDescuento, int $autorizadoPor, ?float $porcentajeAplicado = null): array {
        if ($this->isDbConnected()) {
            $desc = $this->fetchOne('SELECT porcentaje FROM descuento WHERE id_descuento = :id LIMIT 1', [':id' => $idDescuento]);
            if (!$desc) {
                throw new DomainValidationException('Descuento no existe.');
            }
            $pct = $porcentajeAplicado ?? (float)$desc['porcentaje'];
            $this->execute(
                "INSERT INTO pedido_descuento (id_pedido, id_descuento, autorizado_por, porcentaje_aplicado)
                 VALUES (:ped, :desc, :aut, :pct)",
                [':ped' => $idPedido, ':desc' => $idDescuento, ':aut' => $autorizadoPor, ':pct' => $pct]
            );
            return $this->getById($idPedido) ?? ['id_pedido' => $idPedido];
        }

        NormalizedStore::withTransaction(static function (array &$state) use ($idPedido, $idDescuento, $autorizadoPor, $porcentajeAplicado): void {
            $desc = null;
            foreach ($state['descuento'] as $d) {
                if ((int)$d['id_descuento'] === $idDescuento) {
                    $desc = $d;
                    break;
                }
            }
            if (!$desc) {
                throw new DomainValidationException('Descuento no existe.');
            }
            $pct = $porcentajeAplicado ?? (float)$desc['porcentaje'];
            $state['pedido_descuento'][] = [
                'id_pedido'           => $idPedido,
                'id_descuento'        => $idDescuento,
                'autorizado_por'      => $autorizadoPor,
                'porcentaje_aplicado' => $pct,
                'monto_descontado'    => 0.0,
            ];
            self::recalculateOrderTotalsInState($state, $idPedido);
        }, true);

        return $this->getById($idPedido) ?? ['id_pedido' => $idPedido];
    }

    public static function recalculateOrderTotalsInState(array &$state, int $idPedido): void {
        $detalles = [];
        $subtotal = 0.0;
        $impPctPromedio = 8.0;

        foreach ($state['detalle_pedido'] as $d) {
            if ((int)$d['id_pedido'] === $idPedido) {
                $detalles[(int)$d['id_detalle']] = $d;
                $subtotal += ((float)$d['precio_unitario'] * (int)$d['cantidad']);
                $impPctPromedio = (float)$d['impuesto_porcentaje'];
            }
        }

        $persIds = [];
        foreach ($state['personalizacion_pedido'] as $pp) {
            if (isset($detalles[(int)$pp['id_detalle']])) {
                $persIds[(int)$pp['id_personalizacion']] = true;
            }
        }

        foreach ($state['personalizacion_ingrediente'] as $pi) {
            if (isset($persIds[(int)$pi['id_personalizacion']]) && $pi['tipo_ajuste'] === 'EXTRA') {
                $subtotal += ((float)$pi['costo_extra'] * (float)$pi['cantidad']);
            }
        }

        $subtotal = round($subtotal, 2);
        $descuentoTotal = 0.0;
        foreach ($state['pedido_descuento'] as &$pd) {
            if ((int)$pd['id_pedido'] === $idPedido) {
                $monto = round($subtotal * ((float)$pd['porcentaje_aplicado'] / 100.0), 2);
                $pd['monto_descontado'] = $monto;
                $descuentoTotal += $monto;
            }
        }
        $descuentoTotal = min($subtotal, round($descuentoTotal, 2));
        $baseGravable = max(0.0, $subtotal - $descuentoTotal);
        $impuestoTotal = round($baseGravable * ($impPctPromedio / 100.0), 2);
        $total = round($baseGravable + $impuestoTotal, 2);

        foreach ($state['pedido'] as &$p) {
            if ((int)$p['id_pedido'] === $idPedido) {
                $p['subtotal']        = $subtotal;
                $p['descuento_total'] = $descuentoTotal;
                $p['impuesto_total']  = $impuestoTotal;
                $p['total']           = $total;
                break;
            }
        }
    }

    public function getConciliacion(): array {
        if ($this->isDbConnected()) {
            return $this->fetchAll('SELECT * FROM vw_conciliacion_pedidos ORDER BY id_pedido DESC');
        }
        return NormalizedStore::withTransaction(static function (array &$state): array {
            return array_values(NormalizedStore::viewConciliacionPedidos($state));
        }, false);
    }
}
