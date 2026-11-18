<?php
/**
 * Modelo de Caja, Pagos y Facturacion DIAN (PostgreSQL 17).
 */
class Caja extends Model {
    public function getResumen(): array {
        return $this->fetchAll('SELECT * FROM vw_resumen_caja ORDER BY id_caja DESC');
    }

    public function getById(int $idCaja): ?array {
        return $this->fetchOne('SELECT * FROM vw_resumen_caja WHERE id_caja = :id LIMIT 1', [':id' => $idCaja]);
    }

    public function findHeaderById(int $idCaja): ?array {
        return $this->fetchOne('SELECT id_caja, id_cajero, estado FROM caja WHERE id_caja = :id LIMIT 1', [':id' => $idCaja]);
    }

    public function abrir(int $idCajero, float $montoApertura, ?string $observaciones = null): array {
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

    public function cerrar(int $idCaja, float $efectivoContado, ?string $observaciones = null): array {
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

    public function registrarPago(array $data): array {
        $hasTip = !empty($data['propina']) && (float)$data['propina'] > 0;
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

    public function getFacturas(?int $idMesero = null): array {
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

    public function getFacturaById(int $idFactura): ?array {
        return $this->fetchOne(
            "SELECT f.*, p.id_mesero, p.id_cliente
             FROM factura f
             JOIN pedido p ON f.id_pedido = p.id_pedido
             WHERE f.id_factura = :id
             LIMIT 1",
            [':id' => $idFactura]
        );
    }

    public function emitirFactura(array $data): array {
        $safeXml = XmlSecurity::validateSafeXml($data['xml'] ?? null);

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

    public function anularFactura(int $idFactura): array {
        $this->execute(
            "UPDATE factura SET estado_dian = 'ANULADA' WHERE id_factura = :id",
            [':id' => $idFactura]
        );
        return $this->getFacturaById($idFactura) ?? ['id_factura' => $idFactura, 'estado_dian' => 'ANULADA'];
    }
}
