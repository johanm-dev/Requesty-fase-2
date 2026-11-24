<?php

namespace App\Models;

class Cliente extends BasePgModel
{
    public function getAll(int $limit = 100, int $offset = 0): array
    {
        $limit = max(1, min($limit, 200));
        $offset = max(0, $offset);
        $sql = 'SELECT id_cliente, tipo_documento, documento_hash, nombre, telefono, correo, fecha_registro, activo
                FROM vw_clientes_publico
                ORDER BY id_cliente DESC
                LIMIT :limit OFFSET :offset';

        return $this->fetchAll($sql, [':limit' => $limit, ':offset' => $offset]);
    }

    public function findById(int $id): ?array
    {
        $sql = 'SELECT id_cliente, tipo_documento, documento_hash, nombre, telefono, correo, fecha_registro, activo
                FROM vw_clientes_publico
                WHERE id_cliente = :id
                LIMIT 1';

        return $this->fetchOne($sql, [':id' => $id]);
    }

    public function findByDocument(string $documento): ?array
    {
        $sql = 'SELECT id_cliente, tipo_documento, documento, nombre, telefono, correo, activo
                FROM fn_buscar_cliente_por_documento(:doc)
                LIMIT 1';

        return $this->fetchOne($sql, [':doc' => $documento]);
    }

    public function findByEmail(string $correo): ?array
    {
        $sql = 'SELECT id_cliente, tipo_documento, documento_hash, nombre, telefono, correo, fecha_registro, activo
                FROM vw_clientes_publico
                WHERE lower(correo) = lower(trim(:correo))
                LIMIT 1';

        return $this->fetchOne($sql, [':correo' => $correo]);
    }

    public function findOrCreateByEmail(string $nombre, string $correo, ?string $telefono = null): array
    {
        $correoNorm = strtolower(trim($correo));
        $existente = $this->findByEmail($correoNorm);
        if ($existente) {
            if ($nombre !== '' && $nombre !== $existente['nombre']) {
                $this->execute(
                    'UPDATE cliente SET nombre = :n WHERE id_cliente = :id',
                    [':n' => $nombre, ':id' => (int) $existente['id_cliente']]
                );
                $existente['nombre'] = $nombre;
            }

            return $existente;
        }
        $id = $this->create([
            'tipo_documento' => 'CC',
            'nombre' => $nombre !== '' ? $nombre : explode('@', $correoNorm)[0],
            'telefono' => $telefono,
            'correo' => $correoNorm,
        ]);

        return $this->findById($id) ?? ['id_cliente' => $id, 'nombre' => $nombre, 'correo' => $correoNorm];
    }

    public function create(array $data): int
    {
        $hasDoc = !empty($data['documento']);
        if ($hasDoc) {
            $sql = 'INSERT INTO cliente (
                        tipo_documento, documento_cifrado, documento_hash, nombre, telefono, correo
                    ) VALUES (
                        :tipo_doc, fn_cifrar_documento(:doc_enc), fn_hash_documento(:doc_hash),
                        :nombre, :telefono, :correo
                    )
                    RETURNING id_cliente';
            $params = [
                ':tipo_doc' => $data['tipo_documento'] ?? 'CC',
                ':doc_enc' => $data['documento'],
                ':doc_hash' => $data['documento'],
                ':nombre' => $data['nombre'],
                ':telefono' => $data['telefono'] ?? null,
                ':correo' => $data['correo'] ?? null,
            ];
        } else {
            $sql = 'INSERT INTO cliente (
                        tipo_documento, documento_cifrado, documento_hash, nombre, telefono, correo
                    ) VALUES (
                        :tipo_doc, NULL, NULL, :nombre, :telefono, :correo
                    )
                    RETURNING id_cliente';
            $params = [
                ':tipo_doc' => $data['tipo_documento'] ?? 'CC',
                ':nombre' => $data['nombre'],
                ':telefono' => $data['telefono'] ?? null,
                ':correo' => $data['correo'] ?? null,
            ];
        }

        $row = $this->fetchOne($sql, $params);

        return (int) ($row['id_cliente'] ?? 0);
    }
}
