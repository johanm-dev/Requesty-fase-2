<?php

namespace App\Models;

use App\Exceptions\DomainValidationException;
use Throwable;

class Producto extends BasePgModel
{
    public function getAll(bool $onlyAvailable = false): array
    {
        $sql = 'SELECT p.id_producto, p.nombre, p.descripcion, p.precio, p.impuesto_porcentaje,
                       p.imagen, p.disponible, p.tiempo_preparacion, p.id_categoria,
                       c.nombre AS categoria, COALESCE(c.activa, TRUE) AS categoria_activa
                FROM producto p
                LEFT JOIN categoria c ON p.id_categoria = c.id_categoria';
        if ($onlyAvailable) {
            $sql .= ' WHERE p.disponible = TRUE AND COALESCE(c.activa, TRUE) = TRUE';
        }
        $sql .= ' ORDER BY p.id_producto ASC';

        $productos = $this->fetchAll($sql);
        if (empty($productos)) {
            return [];
        }

        $recetasRows = $this->fetchAll(
            'SELECT pi.id_producto, pi.id_ingrediente, pi.cantidad,
                    i.nombre AS ingrediente_nombre, i.unidad_medida, i.stock_actual, i.stock_minimo, i.estado
             FROM producto_ingrediente pi
             JOIN ingrediente i ON i.id_ingrediente = pi.id_ingrediente
             ORDER BY i.nombre ASC'
        );

        $porProducto = [];
        foreach ($recetasRows as $r) {
            $pid = (int) $r['id_producto'];
            $porProducto[$pid][] = [
                'id_ingrediente' => (int) $r['id_ingrediente'],
                'nombre' => $r['ingrediente_nombre'],
                'unidad_medida' => $r['unidad_medida'],
                'cantidad' => (float) $r['cantidad'],
                'stock_actual' => (float) $r['stock_actual'],
                'stock_minimo' => (float) $r['stock_minimo'],
                'estado' => $r['estado'],
            ];
        }

        foreach ($productos as &$prod) {
            $pid = (int) $prod['id_producto'];
            $receta = $porProducto[$pid] ?? [];
            $agotados = [];
            foreach ($receta as $ing) {
                if ($ing['stock_actual'] < $ing['cantidad'] || $ing['estado'] !== 'ACTIVO') {
                    $agotados[] = $ing['nombre'];
                }
            }
            $prod['receta'] = $receta;
            $prod['ingredientes'] = array_map(static fn ($i) => $i['nombre'], $receta);
            $prod['ingredientes_agotados'] = $agotados;
            $prod['disponible_stock'] = (bool) $prod['disponible'] && empty($agotados);
        }

        return $productos;
    }

    public function getById(int $id): ?array
    {
        foreach ($this->getAll(false) as $p) {
            if ((int) $p['id_producto'] === $id) {
                return $p;
            }
        }

        return null;
    }

    public function create(array $data): array
    {
        $this->beginTransaction();
        try {
            $idCategoria = $this->resolveCategoriaId($data);
            $row = $this->fetchOne(
                'INSERT INTO producto (
                    nombre, descripcion, precio, impuesto_porcentaje, imagen,
                    disponible, tiempo_preparacion, id_categoria
                 ) VALUES (
                    :nombre, :desc, :precio, :imp, :imagen,
                    :disp, :tiempo, :id_cat
                 ) RETURNING id_producto',
                [
                    ':nombre' => $data['nombre'],
                    ':desc' => $data['descripcion'] ?? null,
                    ':precio' => $data['precio'],
                    ':imp' => $data['impuesto_porcentaje'] ?? 8.00,
                    ':imagen' => $data['imagen'] ?? null,
                    ':disp' => isset($data['disponible']) ? ($data['disponible'] ? 'true' : 'false') : 'true',
                    ':tiempo' => (int) ($data['tiempo_preparacion'] ?? 10),
                    ':id_cat' => $idCategoria,
                ]
            );
            $idProd = (int) $row['id_producto'];
            if (!empty($data['receta']) && is_array($data['receta'])) {
                $this->syncReceta($idProd, $data['receta']);
            }
            $this->commit();

            return $this->getById($idProd) ?? ['id_producto' => $idProd];
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function update(int $id, array $data): array
    {
        $actual = $this->getById($id);
        if (!$actual) {
            throw new DomainValidationException('Producto no encontrado.');
        }

        $this->beginTransaction();
        try {
            $idCategoria = (!empty($data['id_categoria']) || !empty($data['categoria']))
                ? $this->resolveCategoriaId($data)
                : (int) $actual['id_categoria'];

            $this->execute(
                'UPDATE producto
                    SET nombre = :nombre,
                        descripcion = :desc,
                        precio = :precio,
                        impuesto_porcentaje = :imp,
                        imagen = :imagen,
                        disponible = :disp,
                        tiempo_preparacion = :tiempo,
                        id_categoria = :id_cat
                  WHERE id_producto = :id',
                [
                    ':nombre' => $data['nombre'] ?? $actual['nombre'],
                    ':desc' => array_key_exists('descripcion', $data) ? $data['descripcion'] : $actual['descripcion'],
                    ':precio' => $data['precio'] ?? $actual['precio'],
                    ':imp' => $data['impuesto_porcentaje'] ?? $actual['impuesto_porcentaje'],
                    ':imagen' => array_key_exists('imagen', $data) ? $data['imagen'] : $actual['imagen'],
                    ':disp' => (array_key_exists('disponible', $data) ? (bool) $data['disponible'] : (bool) $actual['disponible']) ? 'true' : 'false',
                    ':tiempo' => (int) ($data['tiempo_preparacion'] ?? $actual['tiempo_preparacion']),
                    ':id_cat' => $idCategoria,
                    ':id' => $id,
                ]
            );

            if (array_key_exists('receta', $data) && is_array($data['receta'])) {
                $this->syncReceta($id, $data['receta']);
            }

            $this->commit();

            return $this->getById($id) ?? ['id_producto' => $id];
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function deactivate(int $id): array
    {
        $this->execute('UPDATE producto SET disponible = FALSE WHERE id_producto = :id', [':id' => $id]);

        return $this->getById($id) ?? ['id_producto' => $id, 'disponible' => false];
    }

    public function getCategorias(bool $onlyActive = false): array
    {
        $sql = 'SELECT id_categoria, nombre, descripcion, activa FROM categoria';
        if ($onlyActive) {
            $sql .= ' WHERE activa = TRUE';
        }
        $sql .= ' ORDER BY nombre ASC';

        return $this->fetchAll($sql);
    }

    public function createCategoria(string $nombre, ?string $descripcion = null): array
    {
        return $this->fetchOne(
            'INSERT INTO categoria (nombre, descripcion, activa) VALUES (:n, :d, TRUE) RETURNING id_categoria, nombre, descripcion, activa',
            [':n' => $nombre, ':d' => $descripcion]
        ) ?? [];
    }

    public function updateCategoria(int $id, array $data): array
    {
        $actual = $this->fetchOne('SELECT * FROM categoria WHERE id_categoria = :id', [':id' => $id]);
        if (!$actual) {
            throw new DomainValidationException('Categoria no encontrada.');
        }
        $this->execute(
            'UPDATE categoria SET nombre = :n, descripcion = :d, activa = :a WHERE id_categoria = :id',
            [
                ':n' => $data['nombre'] ?? $actual['nombre'],
                ':d' => array_key_exists('descripcion', $data) ? $data['descripcion'] : $actual['descripcion'],
                ':a' => (array_key_exists('activa', $data) ? (bool) $data['activa'] : (bool) $actual['activa']) ? 'true' : 'false',
                ':id' => $id,
            ]
        );

        return $this->fetchOne('SELECT * FROM categoria WHERE id_categoria = :id', [':id' => $id]) ?? $actual;
    }

    private function resolveCategoriaId(array $data): int
    {
        if (!empty($data['id_categoria'])) {
            return (int) $data['id_categoria'];
        }
        $nombreCat = trim((string) ($data['categoria'] ?? ''));
        if ($nombreCat === '') {
            throw new DomainValidationException('Debe indicar una categoria valida.');
        }
        $cat = $this->fetchOne('SELECT id_categoria FROM categoria WHERE lower(nombre) = lower(:n) LIMIT 1', [':n' => $nombreCat]);
        if ($cat) {
            return (int) $cat['id_categoria'];
        }
        $created = $this->createCategoria($nombreCat);

        return (int) $created['id_categoria'];
    }

    private function syncReceta(int $idProducto, array $receta): void
    {
        $this->execute('DELETE FROM producto_ingrediente WHERE id_producto = :id', [':id' => $idProducto]);

        $stmtFind = $this->db->prepare('SELECT id_ingrediente FROM ingrediente WHERE lower(nombre) = lower(:n) LIMIT 1');
        $stmtCreateIng = $this->db->prepare(
            'INSERT INTO ingrediente (nombre, unidad_medida, stock_actual, stock_minimo, costo)
             VALUES (:n, :u, 0, 10, 0) RETURNING id_ingrediente'
        );
        $stmtIns = $this->db->prepare(
            'INSERT INTO producto_ingrediente (id_producto, id_ingrediente, cantidad)
             VALUES (:p, :i, :c)
             ON CONFLICT (id_producto, id_ingrediente) DO UPDATE SET cantidad = EXCLUDED.cantidad'
        );

        foreach ($receta as $item) {
            $cantidad = (float) ($item['cantidad'] ?? 0);
            if ($cantidad <= 0) {
                throw new DomainValidationException('Cada ingrediente de receta requiere una cantidad positiva.');
            }
            $idIng = !empty($item['id_ingrediente']) ? (int) $item['id_ingrediente'] : 0;
            if ($idIng <= 0) {
                $nombreIng = trim((string) ($item['nombre'] ?? ''));
                if ($nombreIng === '') {
                    continue;
                }
                $stmtFind->execute([':n' => $nombreIng]);
                $found = $stmtFind->fetch();
                if ($found) {
                    $idIng = (int) $found['id_ingrediente'];
                } else {
                    $stmtCreateIng->execute([
                        ':n' => $nombreIng,
                        ':u' => trim((string) ($item['unidad_medida'] ?? 'g')) ?: 'g',
                    ]);
                    $created = $stmtCreateIng->fetch();
                    $idIng = (int) $created['id_ingrediente'];
                }
            }
            $stmtIns->execute([':p' => $idProducto, ':i' => $idIng, ':c' => $cantidad]);
        }
    }
}
