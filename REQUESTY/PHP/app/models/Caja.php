<?php
/**
 * Modelo de Caja, Pagos, Propinas (propina_distribucion) y Facturacion DIAN (PostgreSQL 17).
 */
class Caja extends Model {
    public function getResumen(): array {
        if ($this->isDbConnected()) {
            return $this->fetchAll('SELECT * FROM vw_resumen_caja ORDER BY id_caja DESC');
        }
        return NormalizedStore::withTransaction(static function (array &$state): array {
            return NormalizedStore::viewResumenCaja($state);
        }, false);
    }

    public function getById(int $idCaja): ?array {
        if ($this->isDbConnected()) {
            return $this->fetchOne('SELECT * FROM vw_resumen_caja WHERE id_caja = :id LIMIT 1', [':id' => $idCaja]);
        }
        foreach ($this->getResumen() as $r) {
            if ((int)$r['id_caja'] === $idCaja) {
                return $r;
            }
        }
        return null;
    }

    public function findHeaderById(int $idCaja): ?array {
        if ($this->isDbConnected()) {
            return $this->fetchOne('SELECT id_caja, id_cajero, estado FROM caja WHERE id_caja = :id LIMIT 1', [':id' => $idCaja]);
        }
        return NormalizedStore::withTransaction(static function (array &$state) use ($idCaja): ?array {
            foreach ($state['caja'] as $c) {
                if ((int)$c['id_caja'] === $idCaja) {
                    return [
                        'id_caja'   => (int)$c['id_caja'],
                        'id_cajero' => (int)$c['id_cajero'],
                        'estado'    => $c['estado'],
                    ];
                }
            }
            return null;
        }, false);
    }

    public function abrir(int $idCajero, float $montoApertura, ?string $observaciones = null): array {
        if ($this->isDbConnected()) {
            $row = $this->fetchOne(
                "INSERT INTO caja (id_cajero, monto_apertura, estado, observaciones)
                 VALUES (:id_cajero, :monto, 'ABIERTA', :obs)
                 RETURNING id_caja",
                [
                    ':id_cajero' => $idCajero,
                    ':monto'     => $montoApertura,
                    ':obs'       => $observaciones,
                ]
            );
            return $this->getById((int)$row['id_caja']) ?? $row;
        }

        $newId = NormalizedStore::withTransaction(static function (array &$state) use ($idCajero, $montoApertura, $observaciones): int {
            // uq_caja_abierta_cajero: un cajero no puede tener dos cajas abiertas simultaneamente
            foreach ($state['caja'] as $c) {
                if ((int)$c['id_cajero'] === $idCajero && $c['estado'] === 'ABIERTA') {
                    throw new DomainConflictException('El cajero ya tiene una caja abierta activa.');
                }
            }

            $maxId = 0;
            foreach ($state['caja'] as $c) {
                $maxId = max($maxId, (int)$c['id_caja']);
            }
            $newId = $maxId + 1;

            $state['caja'][] = [
                'id_caja'          => $newId,
                'id_cajero'        => $idCajero,
                'fecha_apertura'   => date('c'),
                'fecha_cierre'     => null,
                'monto_apertura'   => round($montoApertura, 2),
                'efectivo_contado' => null,
                'estado'           => 'ABIERTA',
                'observaciones'    => $observaciones,
            ];

            return $newId;
        }, true);

        return $this->getById($newId) ?? ['id_caja' => $newId, 'estado' => 'ABIERTA'];
    }

    public function cerrar(int $idCaja, float $efectivoContado, ?string $observaciones = null): array {
        if ($this->isDbConnected()) {
            $this->execute(
                "UPDATE caja
                    SET estado = 'CERRADA',
                        fecha_cierre = NOW(),
                        efectivo_contado = :efectivo,
                        observaciones = COALESCE(:obs, observaciones)
                  WHERE id_caja = :id",
                [
                    ':efectivo' => $efectivoContado,
                    ':obs'      => $observaciones,
                    ':id'       => $idCaja,
                ]
            );
            return $this->getById($idCaja) ?? ['id_caja' => $idCaja, 'estado' => 'CERRADA'];
        }

        NormalizedStore::withTransaction(static function (array &$state) use ($idCaja, $efectivoContado, $observaciones): void {
            $foundIdx = null;
            foreach ($state['caja'] as $idx => $c) {
                if ((int)$c['id_caja'] === $idCaja) {
                    $foundIdx = $idx;
                    break;
                }
            }
            if ($foundIdx === null) {
                throw new DomainValidationException('Caja no encontrada.');
            }
            if ($state['caja'][$foundIdx]['estado'] === 'CERRADA') {
                throw new DomainConflictException('La caja ya se encuentra cerrada y es inmutable.');
            }
            $state['caja'][$foundIdx]['estado']           = 'CERRADA';
            $state['caja'][$foundIdx]['fecha_cierre']     = date('c');
            $state['caja'][$foundIdx]['efectivo_contado'] = round($efectivoContado, 2);
            if ($observaciones !== null) {
                $state['caja'][$foundIdx]['observaciones'] = $observaciones;
            }
        }, true);

        return $this->getById($idCaja) ?? ['id_caja' => $idCaja, 'estado' => 'CERRADA'];
    }

    public function registrarPago(array $data): array {
        $hasTip = !empty($data['propina']) && (float)$data['propina'] > 0;
        if ($this->isDbConnected()) {
            if ($hasTip) {
                $this->beginTransaction();
            }
            try {
                $row = $this->fetchOne(
                    "INSERT INTO pago (id_pedido, id_caja, metodo_pago, valor, estado, referencia)
                     VALUES (:id_pedido, :id_caja, :metodo, :valor, :estado, :referencia)
                     RETURNING id_pago, id_pedido, id_caja, metodo_pago, valor, estado, referencia",
                    [
                        ':id_pedido'  => (int)$data['id_pedido'],
                        ':id_caja'    => (int)$data['id_caja'],
                        ':metodo'     => $data['metodo_pago'],
                        ':valor'      => $data['valor'],
                        ':estado'     => $data['estado'] ?? 'COMPLETADO',
                        ':referencia' => $data['referencia'] ?? null,
                    ]
                );

                if ($hasTip) {
                    $this->execute(
                        'INSERT INTO propina (id_pago, valor) VALUES (:id_pago, :valor)',
                        [':id_pago' => (int)$row['id_pago'], ':valor' => (float)$data['propina']]
                    );
                    $this->commit();
                }

                return $row;
            } catch (Throwable $e) {
                if ($hasTip) {
                    $this->rollBack();
                }
                throw $e;
            }
        }

        return NormalizedStore::withTransaction(static function (array &$state) use ($data, $hasTip): array {
            $idPedido = (int)$data['id_pedido'];
            $idCaja = (int)$data['id_caja'];
            $valor = round((float)$data['valor'], 2);
            $estadoPago = strtoupper((string)($data['estado'] ?? 'COMPLETADO'));

            $caja = null;
            foreach ($state['caja'] as $c) {
                if ((int)$c['id_caja'] === $idCaja) {
                    $caja = $c;
                    break;
                }
            }
            if (!$caja) {
                throw new DomainValidationException('Caja no encontrada.');
            }
            if ($caja['estado'] !== 'ABIERTA') {
                throw new DomainConflictException('No se pueden registrar pagos en una caja cerrada.');
            }

            $conc = NormalizedStore::viewConciliacionPedidos($state)[$idPedido] ?? null;
            if (!$conc) {
                throw new DomainValidationException('Pedido no encontrado.');
            }
            if (in_array($conc['estado'], ['CANCELADO', 'PAGADO'], true)) {
                throw new DomainConflictException("No se permiten pagos sobre un pedido en estado {$conc['estado']}.");
            }
            if ($estadoPago === 'COMPLETADO' && ($valor - $conc['saldo_pendiente']) > 0.009) {
                throw new DomainConflictException('El valor del pago excede el saldo pendiente del pedido (sobrepago bloqueado).');
            }

            $maxId = 0;
            foreach ($state['pago'] as $pg) {
                $maxId = max($maxId, (int)$pg['id_pago']);
            }
            $newPagoId = $maxId + 1;

            $row = [
                'id_pago'     => $newPagoId,
                'id_pedido'   => $idPedido,
                'id_caja'     => $idCaja,
                'metodo_pago' => strtoupper((string)$data['metodo_pago']),
                'valor'       => $valor,
                'estado'      => $estadoPago,
                'referencia'  => $data['referencia'] ?? null,
                'fecha_hora'  => date('c'),
            ];
            $state['pago'][] = $row;

            if ($hasTip) {
                $maxTipId = 0;
                foreach ($state['propina'] as $pr) {
                    $maxTipId = max($maxTipId, (int)$pr['id_propina']);
                }
                $state['propina'][] = [
                    'id_propina'  => $maxTipId + 1,
                    'id_pago'     => $newPagoId,
                    'porcentaje'  => null,
                    'valor'       => round((float)$data['propina'], 2),
                    'distribuida' => false,
                ];
            }

            return $row;
        }, true);
    }

    /**
     * Registra reparto de propina en propina_distribucion validando tope y marcando propina.distribuida.
     */
    public function distribuirPropina(int $idPropina, array $distribuciones): array {
        if ($this->isDbConnected()) {
            $this->beginTransaction();
            try {
                foreach ($distribuciones as $dist) {
                    $this->execute(
                        "INSERT INTO propina_distribucion (id_propina, id_usuario, monto)
                         VALUES (:prop, :usr, :monto)",
                        [
                            ':prop'  => $idPropina,
                            ':usr'   => (int)$dist['id_usuario'],
                            ':monto' => (float)$dist['monto'],
                        ]
                    );
                }
                $this->commit();
                return ['id_propina' => $idPropina, 'distribuida' => true];
            } catch (Throwable $e) {
                $this->rollBack();
                throw $e;
            }
        }

        return NormalizedStore::withTransaction(static function (array &$state) use ($idPropina, $distribuciones): array {
            $propIdx = null;
            foreach ($state['propina'] as $idx => $pr) {
                if ((int)$pr['id_propina'] === $idPropina) {
                    $propIdx = $idx;
                    break;
                }
            }
            if ($propIdx === null) {
                throw new DomainValidationException('Propina no encontrada.');
            }
            $totalPropina = (float)$state['propina'][$propIdx]['valor'];

            $sumaActual = 0.0;
            foreach ($state['propina_distribucion'] as $pd) {
                if ((int)$pd['id_propina'] === $idPropina) {
                    $sumaActual += (float)$pd['monto'];
                }
            }

            foreach ($distribuciones as $dist) {
                $monto = round((float)$dist['monto'], 2);
                if ($monto <= 0) {
                    throw new DomainValidationException('El monto de distribucion debe ser positivo.');
                }
                if (($sumaActual + $monto - $totalPropina) > 0.009) {
                    throw new DomainConflictException('La suma distribuida excede el valor total de la propina.');
                }
                $sumaActual += $monto;
                $state['propina_distribucion'][] = [
                    'id_propina' => $idPropina,
                    'id_usuario' => (int)$dist['id_usuario'],
                    'monto'      => $monto,
                ];
            }

            if (abs($sumaActual - $totalPropina) < 0.01) {
                $state['propina'][$propIdx]['distribuida'] = true;
            }

            return [
                'id_propina'       => $idPropina,
                'total_propina'    => $totalPropina,
                'suma_distribuida' => round($sumaActual, 2),
                'distribuida'      => $state['propina'][$propIdx]['distribuida'],
            ];
        }, true);
    }

    public function getFacturas(?int $idMesero = null): array {
        if ($this->isDbConnected()) {
            $sql = "SELECT f.*, p.id_mesero, p.id_cliente, p.estado AS pedido_estado
                    FROM factura f
                    JOIN pedido p ON f.id_pedido = p.id_pedido";
            $params = [];
            if ($idMesero !== null) {
                $sql .= " WHERE p.id_mesero = :id_mesero";
                $params[':id_mesero'] = $idMesero;
            }
            $sql .= " ORDER BY f.id_factura DESC";
            return $this->fetchAll($sql, $params);
        }

        return NormalizedStore::withTransaction(static function (array &$state) use ($idMesero): array {
            $pedidos = [];
            foreach ($state['pedido'] as $p) {
                $pedidos[(int)$p['id_pedido']] = $p;
            }
            $out = [];
            foreach ($state['factura'] as $f) {
                $p = $pedidos[(int)$f['id_pedido']] ?? null;
                if ($idMesero !== null && (!$p || (int)$p['id_mesero'] !== $idMesero)) {
                    continue;
                }
                $out[] = array_merge($f, [
                    'id_mesero'     => $p ? (int)$p['id_mesero'] : null,
                    'id_cliente'    => $p ? (int)$p['id_cliente'] : null,
                    'pedido_estado' => $p['estado'] ?? null,
                ]);
            }
            usort($out, static fn($a, $b) => $b['id_factura'] <=> $a['id_factura']);
            return $out;
        }, false);
    }

    public function getFacturaById(int $idFactura): ?array {
        if ($this->isDbConnected()) {
            return $this->fetchOne(
                "SELECT f.*, p.id_mesero, p.id_cliente
                 FROM factura f
                 JOIN pedido p ON f.id_pedido = p.id_pedido
                 WHERE f.id_factura = :id
                 LIMIT 1",
                [':id' => $idFactura]
            );
        }

        foreach ($this->getFacturas() as $f) {
            if ((int)$f['id_factura'] === $idFactura) {
                return $f;
            }
        }
        return null;
    }

    public function emitirFactura(array $data): array {
        $safeXml = XmlSecurity::validateSafeXml($data['xml'] ?? null);

        if ($this->isDbConnected()) {
            $this->beginTransaction();
            try {
                $pedido = $this->fetchOne(
                    'SELECT id_pedido, subtotal, descuento_total, impuesto_total FROM pedido WHERE id_pedido = :id LIMIT 1',
                    [':id' => (int)$data['id_pedido']]
                );
                if (!$pedido) {
                    throw new DomainValidationException('Pedido no encontrado para facturacion.');
                }

                $row = $this->fetchOne(
                    "INSERT INTO factura (
                        id_pedido, numero_factura, xml, subtotal, descuento_total, impuesto_total, estado_dian
                     ) VALUES (
                        :id_pedido, :numero, :xml, :sub, :desc, :imp, 'PENDIENTE'
                     )
                     RETURNING id_factura",
                    [
                        ':id_pedido' => (int)$data['id_pedido'],
                        ':numero'    => $data['numero_factura'],
                        ':xml'       => $safeXml,
                        ':sub'       => $pedido['subtotal'],
                        ':desc'      => $pedido['descuento_total'],
                        ':imp'       => $pedido['impuesto_total'],
                    ]
                );
                $idFactura = (int)$row['id_factura'];

                if (!empty($data['aceptar']) && !empty($data['cufe'])) {
                    $this->execute(
                        "UPDATE factura SET estado_dian = 'ACEPTADA', cufe = :cufe WHERE id_factura = :id",
                        [':cufe' => $data['cufe'], ':id' => $idFactura]
                    );
                }

                $this->commit();
                return $this->getFacturaById($idFactura) ?? ['id_factura' => $idFactura];
            } catch (Throwable $e) {
                $this->rollBack();
                throw $e;
            }
        }

        $idFactura = NormalizedStore::withTransaction(static function (array &$state) use ($data, $safeXml): int {
            $idPedido = (int)$data['id_pedido'];
            $pedido = null;
            foreach ($state['pedido'] as $p) {
                if ((int)$p['id_pedido'] === $idPedido) {
                    $pedido = $p;
                    break;
                }
            }
            if (!$pedido) {
                throw new DomainValidationException('Pedido no encontrado para facturacion.');
            }
            if ($pedido['estado'] === 'CANCELADO') {
                throw new DomainConflictException('No se puede facturar un pedido cancelado.');
            }

            // uq_factura_activa_pedido: solo permite refacturar si la factura previa fue RECHAZADA o ANULADA
            foreach ($state['factura'] as $f) {
                if ((int)$f['id_pedido'] === $idPedido && in_array($f['estado_dian'], ['PENDIENTE', 'ACEPTADA'], true)) {
                    throw new DomainConflictException('El pedido ya cuenta con una factura activa (PENDIENTE o ACEPTADA).');
                }
                if (strcasecmp((string)$f['numero_factura'], (string)$data['numero_factura']) === 0) {
                    throw new DomainConflictException('El numero de factura ya existe.');
                }
            }

            $aceptar = !empty($data['aceptar']);
            $cufe = $data['cufe'] ?? null;
            if ($aceptar && empty($cufe)) {
                throw new DomainValidationException('Se requiere CUFE para aprobar una factura en estado ACEPTADA.');
            }

            $maxId = 0;
            foreach ($state['factura'] as $f) {
                $maxId = max($maxId, (int)$f['id_factura']);
            }
            $newId = $maxId + 1;

            $state['factura'][] = [
                'id_factura'      => $newId,
                'id_pedido'       => $idPedido,
                'numero_factura'  => $data['numero_factura'],
                'fecha_emision'   => date('c'),
                'cufe'            => $aceptar ? $cufe : null,
                'xml'             => $safeXml,
                'subtotal'        => (float)$pedido['subtotal'],
                'descuento_total' => (float)$pedido['descuento_total'],
                'impuesto_total'  => (float)$pedido['impuesto_total'],
                'total'           => (float)$pedido['total'],
                'estado_dian'     => $aceptar ? 'ACEPTADA' : 'PENDIENTE',
            ];

            return $newId;
        }, true);

        return $this->getFacturaById($idFactura) ?? ['id_factura' => $idFactura];
    }

    public function anularFactura(int $idFactura): array {
        if ($this->isDbConnected()) {
            $this->execute(
                "UPDATE factura SET estado_dian = 'ANULADA' WHERE id_factura = :id",
                [':id' => $idFactura]
            );
            return $this->getFacturaById($idFactura) ?? ['id_factura' => $idFactura, 'estado_dian' => 'ANULADA'];
        }

        NormalizedStore::withTransaction(static function (array &$state) use ($idFactura): void {
            $found = false;
            foreach ($state['factura'] as &$f) {
                if ((int)$f['id_factura'] === $idFactura) {
                    $f['estado_dian'] = 'ANULADA';
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new DomainValidationException('Factura no encontrada.');
            }
        }, true);

        return $this->getFacturaById($idFactura) ?? ['id_factura' => $idFactura, 'estado_dian' => 'ANULADA'];
    }
}
