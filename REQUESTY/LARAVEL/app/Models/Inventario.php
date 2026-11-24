<?php

namespace App\Models;

use App\Exceptions\DomainValidationException;
use Throwable;

class Inventario extends BasePgModel
{
    public function getIngredientes(): array
    {
        $sql = "SELECT i.id_ingrediente, i.nombre, i.unidad_medida, i.stock_actual,
                       i.stock_minimo, i.costo, i.estado,
                       COALESCE(
                           JSON_AGG(DISTINCT p.nombre) FILTER (WHERE p.id_producto IS NOT NULL),
                           '[]'::json
                       ) AS platos_relacionados
                FROM ingrediente i
                LEFT JOIN producto_ingrediente pi ON pi.id_ingrediente = i.id_ingrediente
                LEFT JOIN producto p ON p.id_producto = pi.id_producto AND p.disponible = TRUE
                GROUP BY i.id_ingrediente
                ORDER BY i.nombre ASC";
        $rows = $this->fetchAll($sql);
        foreach ($rows as &$r) {
            if (is_string($r['platos_relacionados'])) {
                $decoded = json_decode($r['platos_relacionados'], true);
                $r['platos_relacionados'] = is_array($decoded) ? $decoded : [];
            }
        }

        return $rows;
    }

    public function getMovimientos(int $limit = 100): array
    {
        $limit = max(1, min($limit, 200));
        $sql = 'SELECT inv.id_movimiento, inv.id_ingrediente, i.nombre AS ingrediente,
                       i.unidad_medida, inv.tipo_movimiento, inv.cantidad, inv.fecha,
                       inv.motivo, inv.id_usuario, u.nombre AS usuario_nombre, inv.id_detalle
                FROM inventario inv
                JOIN ingrediente i ON i.id_ingrediente = inv.id_ingrediente
                LEFT JOIN vw_usuarios_publico u ON u.id_usuario = inv.id_usuario
                ORDER BY inv.id_movimiento DESC
                LIMIT :lim';

        return $this->fetchAll($sql, [':lim' => $limit]);
    }

    public function createIngrediente(array $data, int $idUsuario): array
    {
        $this->beginTransaction();
        try {
            $row = $this->fetchOne(
                "INSERT INTO ingrediente (nombre, unidad_medida, stock_actual, stock_minimo, costo, estado)
                 VALUES (:nombre, :unidad, 0, :minimo, :costo, 'ACTIVO')
                 RETURNING id_ingrediente",
                [
                    ':nombre' => $data['nombre'],
                    ':unidad' => $data['unidad_medida'],
                    ':minimo' => $data['stock_minimo'] ?? 0,
                    ':costo' => $data['costo'] ?? 0,
                ]
            );
            $idIng = (int) $row['id_ingrediente'];

            $stockInicial = (float) ($data['stock_inicial'] ?? 0);
            if ($stockInicial > 0) {
                $this->registrarMovimiento(
                    $idIng,
                    'ENTRADA',
                    $stockInicial,
                    $data['motivo'] ?? 'Registro inicial de insumo',
                    $idUsuario,
                    null,
                    false
                );
            }

            $this->commit();

            return $this->fetchOne('SELECT * FROM ingrediente WHERE id_ingrediente = :id', [':id' => $idIng]) ?? ['id_ingrediente' => $idIng];
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function updateIngrediente(int $idIngrediente, array $data, int $idUsuario): array
    {
        $actual = $this->fetchOne('SELECT * FROM ingrediente WHERE id_ingrediente = :id', [':id' => $idIngrediente]);
        if (!$actual) {
            throw new DomainValidationException('Ingrediente no encontrado.');
        }

        $this->beginTransaction();
        try {
            $this->execute(
                'UPDATE ingrediente
                    SET nombre = :nombre,
                        stock_minimo = :minimo,
                        costo = :costo,
                        estado = :estado
                  WHERE id_ingrediente = :id',
                [
                    ':nombre' => $data['nombre'] ?? $actual['nombre'],
                    ':minimo' => $data['stock_minimo'] ?? $actual['stock_minimo'],
                    ':costo' => $data['costo'] ?? $actual['costo'],
                    ':estado' => $data['estado'] ?? $actual['estado'],
                    ':id' => $idIngrediente,
                ]
            );

            if (array_key_exists('stock_objetivo', $data) && $data['stock_objetivo'] !== null) {
                $objetivo = round((float) $data['stock_objetivo'], 3);
                $actualStock = round((float) $actual['stock_actual'], 3);
                $delta = round($objetivo - $actualStock, 3);
                if (abs($delta) >= 0.001) {
                    $tipo = $delta > 0 ? 'AJUSTE_POSITIVO' : 'AJUSTE_NEGATIVO';
                    $this->registrarMovimiento(
                        $idIngrediente,
                        $tipo,
                        abs($delta),
                        $data['motivo'] ?? 'Ajuste directo desde panel de inventario',
                        $idUsuario,
                        null,
                        false
                    );
                }
            }

            $this->commit();

            return $this->fetchOne('SELECT * FROM ingrediente WHERE id_ingrediente = :id', [':id' => $idIngrediente]) ?? $actual;
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function registrarMovimiento(
        int $idIngrediente,
        string $tipo,
        float $cantidad,
        string $motivo,
        int $idUsuario,
        ?int $idDetalle = null,
        bool $manageTx = true
    ): int {
        if ($manageTx) {
            $this->beginTransaction();
        }
        try {
            $row = $this->fetchOne(
                'INSERT INTO inventario (id_ingrediente, tipo_movimiento, cantidad, motivo, id_usuario, id_detalle)
                 VALUES (:ing, :tipo, :cant, :motivo, :usr, :det)
                 RETURNING id_movimiento',
                [
                    ':ing' => $idIngrediente,
                    ':tipo' => $tipo,
                    ':cant' => $cantidad,
                    ':motivo' => $motivo,
                    ':usr' => $idUsuario,
                    ':det' => $idDetalle,
                ]
            );

            $ing = $this->fetchOne(
                'SELECT id_ingrediente, nombre, stock_actual, stock_minimo, unidad_medida FROM ingrediente WHERE id_ingrediente = :id',
                [':id' => $idIngrediente]
            );
            if ($ing && (float) $ing['stock_actual'] <= (float) $ing['stock_minimo']) {
                $this->notifyLowStock($ing);
            }

            if ($manageTx) {
                $this->commit();
            }

            return (int) ($row['id_movimiento'] ?? 0);
        } catch (Throwable $e) {
            if ($manageTx) {
                $this->rollBack();
            }
            throw $e;
        }
    }

    private function notifyLowStock(array $ing): void
    {
        $admins = $this->fetchAll("SELECT id_usuario FROM vw_usuarios_publico WHERE upper(rol_nombre) = 'ADMIN' AND estado = 'ACTIVO'");
        if (empty($admins)) {
            return;
        }
        $msg = sprintf(
            'Stock bajo/agotado para %s: %s %s (minimo %s %s)',
            $ing['nombre'],
            $ing['stock_actual'],
            $ing['unidad_medida'],
            $ing['stock_minimo'],
            $ing['unidad_medida']
        );
        $stmt = $this->db->prepare(
            "INSERT INTO notificacion (mensaje, tipo, id_usuario, id_ingrediente)
             VALUES (:msg, 'INVENTARIO', :usr, :ing)"
        );
        foreach ($admins as $adm) {
            $stmt->execute([
                ':msg' => $msg,
                ':usr' => (int) $adm['id_usuario'],
                ':ing' => (int) $ing['id_ingrediente'],
            ]);
        }
    }
}
