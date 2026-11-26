<?php

namespace App\Models;

use App\Exceptions\DomainValidationException;
use App\Services\XmlSecurity;
use Throwable;

class Caja extends BasePgModel
{
    public function getResumen(): array
    {
        return $this->fetchAll('SELECT * FROM vw_resumen_caja ORDER BY id_caja DESC');
    }

    public function getById(int $idCaja): ?array
    {
        return $this->fetchOne('SELECT * FROM vw_resumen_caja WHERE id_caja = :id LIMIT 1', [':id' => $idCaja]);
    }

    public function findHeaderById(int $idCaja): ?array
    {
        return $this->fetchOne('SELECT id_caja, id_cajero, estado FROM caja WHERE id_caja = :id LIMIT 1', [':id' => $idCaja]);
    }

    public function abrir(int $idCajero, float $montoApertura, ?string $observaciones = null): array
    {
        $row = $this->fetchOne(
            "INSERT INTO caja (id_cajero, monto_apertura, estado, observaciones)
             VALUES (:id_cajero, :monto, 'ABIERTA', :obs)
             RETURNING id_caja",
            [
                ':id_cajero' => $idCajero,
                ':monto' => $montoApertura,
                ':obs' => $observaciones,
            ]
        );

        return $this->getById((int) $row['id_caja']) ?? $row;
    }

    public function cerrar(int $idCaja, float $efectivoContado, ?string $observaciones = null): array
    {
        $this->execute(
            "UPDATE caja
                SET estado = 'CERRADA',
                    fecha_cierre = NOW(),
                    efectivo_contado = :efectivo,
                    observaciones = COALESCE(:obs, observaciones)
              WHERE id_caja = :id",
            [
                ':efectivo' => $efectivoContado,
                ':obs' => $observaciones,
                ':id' => $idCaja,
            ]
        );

        return $this->getById($idCaja) ?? ['id_caja' => $idCaja, 'estado' => 'CERRADA'];
    }

    public function getPagos(?int $idCaja = null, ?int $idPedido = null): array
    {
        $sql = 'SELECT pg.id_pago, pg.id_pedido, pg.id_caja, pg.metodo_pago, pg.valor,
                       pg.fecha, pg.estado, pg.referencia,
                       pr.id_propina, pr.valor AS propina_valor, pr.distribuida
                FROM pago pg
                LEFT JOIN propina pr ON pr.id_pago = pg.id_pago';
        $conds = [];
        $params = [];
        if ($idCaja !== null) {
            $conds[] = 'pg.id_caja = :id_caja';
            $params[':id_caja'] = $idCaja;
        }
        if ($idPedido !== null) {
            $conds[] = 'pg.id_pedido = :id_pedido';
            $params[':id_pedido'] = $idPedido;
        }
        if (!empty($conds)) {
            $sql .= ' WHERE ' . implode(' AND ', $conds);
        }
        $sql .= ' ORDER BY pg.id_pago DESC LIMIT 200';

        return $this->fetchAll($sql, $params);
    }

    public function registrarPago(array $data): array
    {
        $hasTip = !empty($data['propina']) && (float) $data['propina'] > 0;
        if ($hasTip) {
            $this->beginTransaction();
        }
        try {
            $row = $this->fetchOne(
                'INSERT INTO pago (id_pedido, id_caja, metodo_pago, valor, estado, referencia)
                 VALUES (:id_pedido, :id_caja, :metodo, :valor, :estado, :referencia)
                 RETURNING id_pago, id_pedido, id_caja, metodo_pago, valor, estado, referencia',
                [
                    ':id_pedido' => (int) $data['id_pedido'],
                    ':id_caja' => (int) $data['id_caja'],
                    ':metodo' => $data['metodo_pago'],
                    ':valor' => $data['valor'],
                    ':estado' => $data['estado'] ?? 'COMPLETADO',
                    ':referencia' => $data['referencia'] ?? null,
                ]
            );

            if ($hasTip) {
                $tipVal = round((float) $data['propina'], 2);
                $pct = ((float) $data['valor'] > 0) ? min(100.00, round(($tipVal / (float) $data['valor']) * 100, 2)) : 0;
                $propRow = $this->fetchOne(
                    'INSERT INTO propina (id_pago, porcentaje, valor) VALUES (:id_pago, :pct, :valor) RETURNING id_propina',
                    [
                        ':id_pago' => (int) $row['id_pago'],
                        ':pct' => $pct,
                        ':valor' => $tipVal,
                    ]
                );
                $idPropina = (int) $propRow['id_propina'];
                $row['id_propina'] = $idPropina;
                $row['propina'] = $tipVal;

                if (!empty($data['distribuir_mesero'])) {
                    $pedRow = $this->fetchOne('SELECT id_mesero FROM pedido WHERE id_pedido = :p', [':p' => (int) $data['id_pedido']]);
                    if ($pedRow && !empty($pedRow['id_mesero'])) {
                        $this->execute(
                            'INSERT INTO propina_distribucion (id_propina, id_usuario, monto) VALUES (:pr, :u, :m)',
                            [
                                ':pr' => $idPropina,
                                ':u' => (int) $pedRow['id_mesero'],
                                ':m' => $tipVal,
                            ]
                        );
                    }
                }
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

    public function reembolsarPago(int $idPago): array
    {
        $this->execute("UPDATE pago SET estado = 'REEMBOLSADO' WHERE id_pago = :id", [':id' => $idPago]);

        return $this->fetchOne('SELECT * FROM pago WHERE id_pago = :id', [':id' => $idPago]) ?? ['id_pago' => $idPago, 'estado' => 'REEMBOLSADO'];
    }

    public function distribuirPropina(int $idPropina, array $asignaciones): array
    {
        $this->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO propina_distribucion (id_propina, id_usuario, monto) VALUES (:pr, :u, :m)
                 ON CONFLICT (id_propina, id_usuario) DO UPDATE SET monto = EXCLUDED.monto'
            );
            foreach ($asignaciones as $asig) {
                $idUsr = (int) ($asig['id_usuario'] ?? 0);
                $monto = round((float) ($asig['monto'] ?? 0), 2);
                if ($idUsr <= 0 || $monto <= 0) {
                    throw new DomainValidationException('Cada distribucion de propina requiere id_usuario y monto positivo.');
                }
                $stmt->execute([':pr' => $idPropina, ':u' => $idUsr, ':m' => $monto]);
            }
            $this->commit();

            return $this->fetchOne('SELECT * FROM propina WHERE id_propina = :id', [':id' => $idPropina]) ?? ['id_propina' => $idPropina];
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function getFacturas(?int $idMesero = null, ?int $idCliente = null, int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min($limit, 200));
        $offset = max(0, $offset);
        $sql = "SELECT f.*, p.id_mesero, p.id_cliente, p.estado AS pedido_estado,
                       c.nombre AS cliente_nombre, c.correo AS cliente_correo,
                       m.numero_mesa, u.nombre AS mesero_nombre, u.correo AS mesero_correo,
                       (SELECT STRING_AGG(pg.metodo_pago::TEXT, ', ' ORDER BY pg.id_pago)
                          FROM pago pg WHERE pg.id_pedido = f.id_pedido AND pg.estado = 'COMPLETADO') AS metodo_pago,
                       COALESCE((SELECT SUM(pr.valor)
                                   FROM pago pg JOIN propina pr ON pr.id_pago = pg.id_pago
                                  WHERE pg.id_pedido = f.id_pedido AND pg.estado = 'COMPLETADO'), 0)::NUMERIC(12,2) AS propina_total
                FROM factura f
                JOIN pedido p ON f.id_pedido = p.id_pedido
                LEFT JOIN vw_clientes_publico c ON c.id_cliente = p.id_cliente
                LEFT JOIN mesa m ON m.id_mesa = p.id_mesa
                LEFT JOIN vw_usuarios_publico u ON u.id_usuario = p.id_mesero";
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
        $sql .= ' ORDER BY f.id_factura DESC LIMIT :limit OFFSET :offset';
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        return $this->fetchAll($sql, $params);
    }

    public function getFacturaById(int $idFactura): ?array
    {
        return $this->fetchOne(
            'SELECT f.*, p.id_mesero, p.id_cliente,
                    c.nombre AS cliente_nombre, c.correo AS cliente_correo,
                    m.numero_mesa, u.nombre AS mesero_nombre
             FROM factura f
             JOIN pedido p ON f.id_pedido = p.id_pedido
             LEFT JOIN vw_clientes_publico c ON c.id_cliente = p.id_cliente
             LEFT JOIN mesa m ON m.id_mesa = p.id_mesa
             LEFT JOIN vw_usuarios_publico u ON u.id_usuario = p.id_mesero
             WHERE f.id_factura = :id
             LIMIT 1',
            [':id' => $idFactura]
        );
    }

    public function emitirFactura(array $data): array
    {
        $safeXml = XmlSecurity::validateSafeXml($data['xml'] ?? null);

        $this->beginTransaction();
        try {
            $pedido = $this->fetchOne(
                'SELECT id_pedido, subtotal, descuento_total, impuesto_total FROM pedido WHERE id_pedido = :id LIMIT 1',
                [':id' => (int) $data['id_pedido']]
            );
            if (!$pedido) {
                throw new DomainValidationException('Pedido no encontrado para facturacion.');
            }

            $numero = $data['numero_factura'] ?? '';
            if ($numero === '') {
                $seq = $this->fetchOne('SELECT COALESCE(MAX(id_factura), 0) + 1 AS nxt FROM factura');
                $numero = sprintf('FE-%06d', (int) ($seq['nxt'] ?? 1));
            }

            $row = $this->fetchOne(
                "INSERT INTO factura (
                    id_pedido, numero_factura, xml, subtotal, descuento_total, impuesto_total, estado_dian
                 ) VALUES (
                    :id_pedido, :numero, :xml, :sub, :desc, :imp, 'PENDIENTE'
                 )
                 RETURNING id_factura",
                [
                    ':id_pedido' => (int) $data['id_pedido'],
                    ':numero' => $numero,
                    ':xml' => $safeXml,
                    ':sub' => $pedido['subtotal'],
                    ':desc' => $pedido['descuento_total'],
                    ':imp' => $pedido['impuesto_total'],
                ]
            );
            $idFactura = (int) $row['id_factura'];

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

    public function actualizarFacturaEstado(int $idFactura, string $estadoDian, ?string $cufe = null, ?string $xml = null): array
    {
        $safeXml = $xml !== null ? XmlSecurity::validateSafeXml($xml) : null;
        $est = strtoupper(trim($estadoDian));
        $this->execute(
            'UPDATE factura
                SET estado_dian = :est,
                    cufe = COALESCE(:cufe, cufe),
                    xml = COALESCE(:xml, xml)
              WHERE id_factura = :id',
            [
                ':est' => $est,
                ':cufe' => $cufe,
                ':xml' => $safeXml,
                ':id' => $idFactura,
            ]
        );

        return $this->getFacturaById($idFactura) ?? ['id_factura' => $idFactura, 'estado_dian' => $est];
    }

    public function anularFactura(int $idFactura): array
    {
        $this->execute(
            "UPDATE factura SET estado_dian = 'ANULADA' WHERE id_factura = :id",
            [':id' => $idFactura]
        );

        return $this->getFacturaById($idFactura) ?? ['id_factura' => $idFactura, 'estado_dian' => 'ANULADA'];
    }
}
