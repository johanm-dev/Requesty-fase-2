<?php
/**
 * Modelo de Cliente (PostgreSQL 17: cifrado AES-256 + Blind Index HMAC-SHA256).
 */
class Cliente extends Model {
    public function getAll(): array {
        if ($this->isDbConnected()) {
            $sql = "SELECT id_cliente, tipo_documento, documento_hash, nombre, telefono, correo, fecha_registro, activo
                    FROM vw_clientes_publico
                    ORDER BY id_cliente DESC";
            return $this->fetchAll($sql);
        }

        return NormalizedStore::withTransaction(static function (array &$state): array {
            $rows = NormalizedStore::viewClientesPublico($state);
            usort($rows, static fn($a, $b) => $b['id_cliente'] <=> $a['id_cliente']);
            return $rows;
        }, false);
    }

    public function findById(int $id): ?array {
        if ($this->isDbConnected()) {
            $sql = "SELECT id_cliente, tipo_documento, documento_hash, nombre, telefono, correo, fecha_registro, activo
                    FROM vw_clientes_publico
                    WHERE id_cliente = :id
                    LIMIT 1";
            return $this->fetchOne($sql, [':id' => $id]);
        }

        return NormalizedStore::withTransaction(static function (array &$state) use ($id): ?array {
            foreach (NormalizedStore::viewClientesPublico($state) as $c) {
                if ((int)$c['id_cliente'] === $id) {
                    return $c;
                }
            }
            return null;
        }, false);
    }

    public function findByDocument(string $documento): ?array {
        if ($this->isDbConnected()) {
            $sql = "SELECT id_cliente, tipo_documento, documento, nombre, telefono, correo, activo
                    FROM fn_buscar_cliente_por_documento(:doc)
                    LIMIT 1";
            return $this->fetchOne($sql, [':doc' => $documento]);
        }

        $docHash = CryptoService::hashDocument($documento);
        return NormalizedStore::withTransaction(static function (array &$state) use ($docHash): ?array {
            foreach ($state['cliente'] as $c) {
                if (!empty($c['documento_hash']) && hash_equals((string)$c['documento_hash'], $docHash)) {
                    return [
                        'id_cliente'     => (int)$c['id_cliente'],
                        'tipo_documento' => $c['tipo_documento'],
                        'documento'      => CryptoService::decryptDocument($c['documento_cifrado']),
                        'nombre'         => $c['nombre'],
                        'telefono'       => $c['telefono'],
                        'correo'         => $c['correo'],
                        'activo'         => (bool)$c['activo'],
                    ];
                }
            }
            return null;
        }, false);
    }

    public function create(array $data): int {
        $hasDoc = !empty($data['documento']);
        if ($this->isDbConnected()) {
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

        $docEnc = $hasDoc ? CryptoService::encryptDocument((string)$data['documento']) : null;
        $docHash = $hasDoc ? CryptoService::hashDocument((string)$data['documento']) : null;
        $correo = !empty($data['correo']) ? strtolower(trim((string)$data['correo'])) : null;

        return NormalizedStore::withTransaction(static function (array &$state) use ($data, $docEnc, $docHash, $correo): int {
            foreach ($state['cliente'] as $c) {
                if ($docHash !== null && !empty($c['documento_hash']) && hash_equals((string)$c['documento_hash'], $docHash)) {
                    throw new DomainConflictException('El documento del cliente ya se encuentra registrado.');
                }
                if ($correo !== null && !empty($c['correo']) && strtolower((string)$c['correo']) === $correo) {
                    throw new DomainConflictException('El correo del cliente ya se encuentra registrado.');
                }
            }

            $maxId = 0;
            foreach ($state['cliente'] as $c) {
                $maxId = max($maxId, (int)$c['id_cliente']);
            }
            $newId = $maxId + 1;

            $state['cliente'][] = [
                'id_cliente'        => $newId,
                'tipo_documento'    => strtoupper(trim((string)($data['tipo_documento'] ?? 'CC'))),
                'documento_cifrado' => $docEnc,
                'documento_hash'    => $docHash,
                'nombre'            => trim((string)$data['nombre']),
                'telefono'          => $data['telefono'] ?? null,
                'correo'            => $correo,
                'fecha_registro'    => date('c'),
                'activo'            => true,
            ];

            return $newId;
        }, true);
    }
}
