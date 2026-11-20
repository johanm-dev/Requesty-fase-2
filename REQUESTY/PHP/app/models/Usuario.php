<?php
/**
 * Modelo de Usuario (PostgreSQL 17: vw_usuarios_publico + fn_autenticar_usuario + AES-256/Blind Index + Bcrypt cost >= 12).
 */
class Usuario extends Model {
    public function getAll(): array {
        if ($this->isDbConnected()) {
            $sql = "SELECT id_usuario, nombre, apellido, documento_hash, correo, telefono,
                           estado, fecha_registro, ultimo_inicio_sesion, id_rol, rol_nombre AS rol
                    FROM vw_usuarios_publico
                    ORDER BY id_usuario ASC";
            return $this->fetchAll($sql);
        }

        return NormalizedStore::withTransaction(static function (array &$state): array {
            return NormalizedStore::viewUsuariosPublico($state);
        }, false);
    }

    public function findById(int $id): ?array {
        if ($this->isDbConnected()) {
            $sql = "SELECT id_usuario, nombre, apellido, documento_hash, correo, telefono,
                           estado, fecha_registro, ultimo_inicio_sesion, id_rol, rol_nombre AS rol
                    FROM vw_usuarios_publico
                    WHERE id_usuario = :id
                    LIMIT 1";
            return $this->fetchOne($sql, [':id' => $id]);
        }

        return NormalizedStore::withTransaction(static function (array &$state) use ($id): ?array {
            foreach (NormalizedStore::viewUsuariosPublico($state) as $u) {
                if ((int)$u['id_usuario'] === $id) {
                    return $u;
                }
            }
            return null;
        }, false);
    }

    public function findByEmail(string $email): ?array {
        $clean = strtolower(trim($email));
        if ($this->isDbConnected()) {
            $sql = "SELECT id_usuario, nombre, apellido, documento_hash, correo, telefono,
                           estado, id_rol, rol_nombre AS rol
                    FROM vw_usuarios_publico
                    WHERE lower(correo) = lower(trim(:correo))
                    LIMIT 1";
            return $this->fetchOne($sql, [':correo' => $clean]);
        }

        return NormalizedStore::withTransaction(static function (array &$state) use ($clean): ?array {
            foreach (NormalizedStore::viewUsuariosPublico($state) as $u) {
                if (strtolower($u['correo']) === $clean) {
                    return $u;
                }
            }
            return null;
        }, false);
    }

    public function findByDocument(string $documento): ?array {
        if ($this->isDbConnected()) {
            $sql = "SELECT u.id_usuario, u.nombre, u.apellido, u.documento, u.correo,
                           u.telefono, u.estado, u.id_rol, r.nombre AS rol
                    FROM fn_buscar_usuario_por_documento(:doc) u
                    JOIN rol r ON r.id_rol = u.id_rol
                    LIMIT 1";
            return $this->fetchOne($sql, [':doc' => $documento]);
        }

        $docHash = CryptoService::hashDocument($documento);
        return NormalizedStore::withTransaction(static function (array &$state) use ($docHash): ?array {
            $roles = [];
            foreach ($state['rol'] as $r) {
                $roles[(int)$r['id_rol']] = $r['nombre'];
            }
            foreach ($state['usuario'] as $u) {
                if (hash_equals((string)$u['documento_hash'], $docHash)) {
                    return [
                        'id_usuario' => (int)$u['id_usuario'],
                        'nombre'     => $u['nombre'],
                        'apellido'   => $u['apellido'],
                        'documento'  => CryptoService::decryptDocument($u['documento_cifrado']),
                        'correo'     => $u['correo'],
                        'telefono'   => $u['telefono'],
                        'estado'     => $u['estado'],
                        'id_rol'     => (int)$u['id_rol'],
                        'rol'        => $roles[(int)$u['id_rol']] ?? 'MESERO',
                    ];
                }
            }
            return null;
        }, false);
    }

    public function authenticate(string $email, string $password): ?array {
        $cleanEmail = strtolower(trim($email));
        if ($this->isDbConnected()) {
            $sql = "SELECT a.id_usuario, a.nombre, a.apellido, a.id_rol, a.estado,
                           r.nombre AS rol, v.correo
                    FROM fn_autenticar_usuario(:correo, :pass) a
                    JOIN rol r ON r.id_rol = a.id_rol
                    JOIN vw_usuarios_publico v ON v.id_usuario = a.id_usuario
                    LIMIT 1";
            return $this->fetchOne($sql, [
                ':correo' => $cleanEmail,
                ':pass'   => $password,
            ]);
        }

        return NormalizedStore::withTransaction(static function (array &$state) use ($cleanEmail, $password): ?array {
            $roles = [];
            foreach ($state['rol'] as $r) {
                $roles[(int)$r['id_rol']] = $r['nombre'];
            }
            foreach ($state['usuario'] as &$u) {
                if (strtolower($u['correo']) === $cleanEmail) {
                    if ($u['estado'] !== 'ACTIVO') {
                        return null;
                    }
                    if (!CryptoService::verifyPassword($password, (string)$u['contrasena'])) {
                        return null;
                    }
                    $u['ultimo_inicio_sesion'] = date('c');
                    return [
                        'id_usuario' => (int)$u['id_usuario'],
                        'nombre'     => $u['nombre'],
                        'apellido'   => $u['apellido'],
                        'id_rol'     => (int)$u['id_rol'],
                        'estado'     => $u['estado'],
                        'rol'        => $roles[(int)$u['id_rol']] ?? 'MESERO',
                        'correo'     => $u['correo'],
                    ];
                }
            }
            return null;
        }, true);
    }

    public function create(array $data): int {
        $rolNombre = strtoupper(trim((string)($data['rol'] ?? 'MESERO')));
        if ($rolNombre === 'ADMINISTRADOR') {
            $rolNombre = 'ADMIN';
        }

        if ($this->isDbConnected()) {
            $rolRow = $this->fetchOne(
                'SELECT id_rol FROM rol WHERE upper(nombre) = :nombre LIMIT 1',
                [':nombre' => $rolNombre]
            );
            if (!$rolRow) {
                throw new DomainValidationException('El rol especificado no existe.');
            }

            $sql = "INSERT INTO usuario (
                        nombre, apellido, documento_cifrado, documento_hash,
                        correo, telefono, contrasena, estado, id_rol
                    ) VALUES (
                        :nombre, :apellido, fn_cifrar_documento(:doc_enc), fn_hash_documento(:doc_hash),
                        lower(trim(:correo)), :telefono, :contrasena, 'ACTIVO', :id_rol
                    )
                    RETURNING id_usuario";

            $row = $this->fetchOne($sql, [
                ':nombre'     => $data['nombre'],
                ':apellido'   => $data['apellido'] ?? '',
                ':doc_enc'    => $data['documento'],
                ':doc_hash'   => $data['documento'],
                ':correo'     => $data['correo'],
                ':telefono'   => $data['telefono'] ?? null,
                ':contrasena' => $data['contrasena'],
                ':id_rol'     => (int)$rolRow['id_rol'],
            ]);

            return (int)($row['id_usuario'] ?? 0);
        }

        $docPlain = (string)($data['documento'] ?? '');
        $docHash = CryptoService::hashDocument($docPlain);
        $docEnc = CryptoService::encryptDocument($docPlain);
        $passHash = CryptoService::hashPassword((string)($data['contrasena'] ?? ''), true);
        $email = strtolower(trim((string)$data['correo']));

        return NormalizedStore::withTransaction(static function (array &$state) use ($data, $rolNombre, $docHash, $docEnc, $passHash, $email): int {
            $idRol = null;
            foreach ($state['rol'] as $r) {
                if (strtoupper($r['nombre']) === $rolNombre) {
                    $idRol = (int)$r['id_rol'];
                    break;
                }
            }
            if ($idRol === null) {
                throw new DomainValidationException('El rol especificado no existe.');
            }

            foreach ($state['usuario'] as $u) {
                if (strtolower($u['correo']) === $email || hash_equals((string)$u['documento_hash'], $docHash)) {
                    throw new DomainConflictException('El usuario o documento ya se encuentra registrado.');
                }
            }

            $maxId = 0;
            foreach ($state['usuario'] as $u) {
                $maxId = max($maxId, (int)$u['id_usuario']);
            }
            $newId = $maxId + 1;

            $state['usuario'][] = [
                'id_usuario'           => $newId,
                'nombre'               => trim((string)$data['nombre']),
                'apellido'             => trim((string)($data['apellido'] ?? '')),
                'documento_cifrado'    => $docEnc,
                'documento_hash'       => $docHash,
                'correo'               => $email,
                'telefono'             => $data['telefono'] ?? null,
                'contrasena'           => $passHash,
                'estado'               => 'ACTIVO',
                'fecha_registro'       => date('c'),
                'ultimo_inicio_sesion' => null,
                'id_rol'               => $idRol,
            ];

            return $newId;
        }, true);
    }
}
