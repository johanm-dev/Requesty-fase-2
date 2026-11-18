<?php
/**
 * Modelo de Cliente (PostgreSQL 17: cifrado AES-256 + Blind Index HMAC-SHA256).
 */
class Cliente extends Model {
    public function getAll(): array {
        $sql = "SELECT id_cliente, tipo_documento, documento_hash, nombre, telefono, correo, fecha_registro, activo
                FROM vw_clientes_publico
                ORDER BY id_cliente DESC";
        return $this->fetchAll($sql);
    }

    public function findById(int $id): ?array {
        $sql = "SELECT id_cliente, tipo_documento, documento_hash, nombre, telefono, correo, fecha_registro, activo
                FROM vw_clientes_publico
                WHERE id_cliente = :id
                LIMIT 1";
        return $this->fetchOne($sql, [':id' => $id]);
    }

    public function findByDocument(string $documento): ?array {
        $sql = "SELECT id_cliente, tipo_documento, documento, nombre, telefono, correo, activo
                FROM fn_buscar_cliente_por_documento(:doc)
                LIMIT 1";
        return $this->fetchOne($sql, [':doc' => $documento]);
    }

    public function create(array $data): int {
        $hasDoc = !empty($data['documento']);
        if ($hasDoc) {
            $sql = "INSERT INTO cliente (
                        tipo_documento, documento_cifrado, documento_hash, nombre, telefono, correo
                    ) VALUES (
                        :tipo_doc, fn_cifrar_documento(:doc_enc), fn_hash_documento(:doc_hash),
                        :nombre, :telefono, :correo
                    )
                    RETURNING id_cliente";
            $params = [
                ':tipo_doc' => $data['tipo_documento'] ?? 'CC',
                ':doc_enc'  => $data['documento'],
                ':doc_hash' => $data['documento'],
                ':nombre'   => $data['nombre'],
                ':telefono' => $data['telefono'] ?? null,
                ':correo'   => $data['correo'] ?? null,
            ];
        } else {
            $sql = "INSERT INTO cliente (
                        tipo_documento, documento_cifrado, documento_hash, nombre, telefono, correo
                    ) VALUES (
                        :tipo_doc, NULL, NULL, :nombre, :telefono, :correo
                    )
                    RETURNING id_cliente";
            $params = [
                ':tipo_doc' => $data['tipo_documento'] ?? 'CC',
                ':nombre'   => $data['nombre'],
                ':telefono' => $data['telefono'] ?? null,
                ':correo'   => $data['correo'] ?? null,
            ];
        }

        $row = $this->fetchOne($sql, $params);
        return (int)($row['id_cliente'] ?? 0);
    }
}
