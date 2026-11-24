<?php
/**
 * Modelo de Productos, Menu, Recetas y Kardex Inmutable de Inventario (PostgreSQL 17).
 */
class Producto extends Model {
    public function getAll(): array {
        if ($this->isDbConnected()) {
            $sql = "SELECT p.*, c.nombre AS categoria
                    FROM producto p
                    LEFT JOIN categoria c ON p.id_categoria = c.id_categoria
                    WHERE p.disponible = TRUE
                    ORDER BY p.id_producto ASC";
            return $this->fetchAll($sql);
        }

        return NormalizedStore::withTransaction(static function (array &$state): array {
            $cats = [];
            foreach ($state['categoria'] as $c) {
                $cats[(int)$c['id_categoria']] = $c['nombre'];
            }
            $out = [];
            foreach ($state['producto'] as $p) {
                if (!empty($p['disponible'])) {
                    $out[] = array_merge($p, [
                        'categoria' => $cats[(int)$p['id_categoria']] ?? 'General',
                    ]);
                }
            }
            return $out;
        }, false);
    }

    public function getCategorias(): array {
        if ($this->isDbConnected()) {
            return $this->fetchAll('SELECT * FROM categoria WHERE activa = TRUE ORDER BY nombre ASC');
        }
        return NormalizedStore::withTransaction(static function (array &$state): array {
            return array_values(array_filter($state['categoria'], static fn($c) => !empty($c['activa'])));
        }, false);
    }

    public function getIngredientes(): array {
        if ($this->isDbConnected()) {
            return $this->fetchAll('SELECT id_ingrediente, nombre, unidad_medida, stock_actual, stock_minimo, estado FROM ingrediente ORDER BY id_ingrediente ASC');
        }
        return NormalizedStore::withTransaction(static function (array &$state): array {
            return $state['ingrediente'];
        }, false);
    }

    public function registrarMovimientoInventario(array $data): int {
        if ($this->isDbConnected()) {
            $row = $this->fetchOne(
                'INSERT INTO inventario (id_ingrediente, tipo_movimiento, cantidad, motivo, id_usuario, id_detalle)
                 VALUES (:ing, :tipo, :cant, :motivo, :usr, :det) RETURNING id_movimiento',
                [
                    ':ing'    => (int)$data['id_ingrediente'],
                    ':tipo'   => strtoupper((string)$data['tipo_movimiento']),
                    ':cant'   => (float)$data['cantidad'],
                    ':motivo' => $data['motivo'],
                    ':usr'    => (int)$data['id_usuario'],
                    ':det'    => isset($data['id_detalle']) ? (int)$data['id_detalle'] : null,
                ]
            );
            return (int)$row['id_movimiento'];
        }

        return NormalizedStore::withTransaction(static function (array &$state) use ($data): int {
            $idIng = (int)$data['id_ingrediente'];
            $tipo = strtoupper((string)$data['tipo_movimiento']);
            $cant = round((float)$data['cantidad'], 2);

            $ingIdx = null;
            foreach ($state['ingrediente'] as $idx => $ing) {
                if ((int)$ing['id_ingrediente'] === $idIng) {
                    $ingIdx = $idx;
                    break;
                }
            }
            if ($ingIdx === null) {
                throw new DomainValidationException('Ingrediente no encontrado.');
            }

            $stockActual = (float)$state['ingrediente'][$ingIdx]['stock_actual'];
            if (in_array($tipo, ['SALIDA', 'AJUSTE_NEGATIVO', 'MERMA'], true)) {
                if ($cant > $stockActual) {
                    throw new DomainValidationException('Stock insuficiente para completar la operacion.', 422);
                }
                $nuevoStock = round($stockActual - $cant, 2);
            } else {
                $nuevoStock = round($stockActual + $cant, 2);
            }

            // Blindaje de existencias: actualizacion atomica via trigger de kardex
            $state['ingrediente'][$ingIdx]['stock_actual'] = $nuevoStock;
            if ($state['ingrediente'][$ingIdx]['estado'] !== 'INACTIVO') {
                $state['ingrediente'][$ingIdx]['estado'] = ($nuevoStock <= 0.0) ? 'AGOTADO' : 'ACTIVO';
            }

            $maxMovId = 0;
            foreach ($state['inventario'] as $mov) {
                $maxMovId = max($maxMovId, (int)$mov['id_movimiento']);
            }
            $newMovId = $maxMovId + 1;

            $state['inventario'][] = [
                'id_movimiento'   => $newMovId,
                'id_ingrediente'  => $idIng,
                'tipo_movimiento' => $tipo,
                'cantidad'        => $cant,
                'motivo'          => $data['motivo'],
                'id_usuario'      => (int)$data['id_usuario'],
                'id_detalle'      => isset($data['id_detalle']) ? (int)$data['id_detalle'] : null,
                'fecha_hora'      => date('c'),
            ];

            return $newMovId;
        }, true);
    }
}
