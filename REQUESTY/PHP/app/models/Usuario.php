<?php
/**
 * Modelo de Usuario (PostgreSQL 17: vw_usuarios_publico + fn_autenticar_usuario + AES-256/Blind Index).
 */
class Usuario extends Model {
    public function getAll(): array {
        $sql = "SELECT id_usuario, nombre, apellido, documento_hash, correo, telefono,
                       estado, fecha_registro, ultimo_inicio_sesion, id_rol, rol_nombre AS rol
                FROM vw_usuarios_publico
                ORDER BY id_usuario ASC";
        return $this->fetchAll($sql);
    }

    public function findById(int $id): ?array {
        $sql = "SELECT id_usuario, nombre, apellido, documento_hash, correo, telefono,
                       estado, fecha_registro, ultimo_inicio_sesion, id_rol, rol_nombre AS rol
                FROM vw_usuarios_publico
                WHERE id_usuario = :id
                LIMIT 1";
        return $this->fetchOne($sql, [':id' => $id]);
    }

    public function findByEmail(string $email): ?array {
        $sql = "SELECT id_usuario, nombre, apellido, documento_hash, correo, telefono,
                       estado, id_rol, rol_nombre AS rol
                FROM vw_usuarios_publico
                WHERE lower(correo) = lower(trim(:correo))
                LIMIT 1";
        return $this->fetchOne($sql, [':correo' => $email]);
    }

    public function findByDocument(string $documento): ?array {
        $sql = "SELECT u.id_usuario, u.nombre, u.apellido, u.documento, u.correo,
                       u.telefono, u.estado, u.id_rol, r.nombre AS rol
                FROM fn_buscar_usuario_por_documento(:doc) u
                JOIN rol r ON r.id_rol = u.id_rol
                LIMIT 1";
        return $this->fetchOne($sql, [':doc' => $documento]);
    }

    public function authenticate(string $email, string $password): ?array {
        $sql = "SELECT a.id_usuario, a.nombre, a.apellido, a.id_rol, a.estado,
                       r.nombre AS rol, v.correo
                FROM fn_autenticar_usuario(:correo, :pass) a
                JOIN rol r ON r.id_rol = a.id_rol
                JOIN vw_usuarios_publico v ON v.id_usuario = a.id_usuario
                LIMIT 1";
        return $this->fetchOne($sql, [
            ':correo' => trim($email),
            ':pass'   => $password,
        ]);
    }

    public function create(array $data): int {
        $rolNombre = strtoupper(trim((string)($data['rol'] ?? 'MESERO')));
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

    public function getRoles(): array {
        return $this->fetchAll('SELECT id_rol, nombre, descripcion FROM rol ORDER BY id_rol ASC');
    }

    public function update(int $id, array $data, int $actorId): array {
        $actual = $this->findById($id);
        if (!$actual) {
            throw new DomainValidationException('Usuario no encontrado.');
        }

        $nuevoRol = isset($data['rol']) ? strtoupper(trim((string)$data['rol'])) : strtoupper((string)$actual['rol']);
        $nuevoEstado = isset($data['estado']) ? strtoupper(trim((string)$data['estado'])) : strtoupper((string)$actual['estado']);

        $rolActual = strtoupper((string)$actual['rol']);
        if ($id === $actorId && $nuevoEstado !== 'ACTIVO') {
            throw new DomainConflictException('No puedes inactivar la cuenta que estas usando.');
        }
        if ($id === $actorId && $rolActual === 'ADMIN' && $nuevoRol !== 'ADMIN') {
            throw new DomainConflictException('No puedes cambiar tu propia cuenta de administrador a otro rol.');
        }

        $dejaDeSerAdminActivo = ($rolActual === 'ADMIN' && $actual['estado'] === 'ACTIVO')
            && ($nuevoRol !== 'ADMIN' || $nuevoEstado !== 'ACTIVO');
        if ($dejaDeSerAdminActivo) {
            $rowCnt = $this->fetchOne(
                "SELECT COUNT(*) AS cnt FROM vw_usuarios_publico WHERE upper(rol_nombre) = 'ADMIN' AND estado = 'ACTIVO' AND id_usuario <> :id",
                [':id' => $id]
            );
            if ((int)($rowCnt['cnt'] ?? 0) === 0) {
                throw new DomainConflictException('Debe quedar al menos una cuenta de administrador activa.');
            }
        }

        $rolRow = $this->fetchOne('SELECT id_rol FROM rol WHERE upper(nombre) = :nombre LIMIT 1', [':nombre' => $nuevoRol]);
        if (!$rolRow) {
            throw new DomainValidationException('El rol especificado no existe.');
        }

        $sets = [
            'nombre = :nombre',
            'apellido = :apellido',
            'correo = lower(trim(:correo))',
            'telefono = :telefono',
            'estado = :estado',
            'id_rol = :id_rol',
        ];
        $params = [
            ':nombre'   => $data['nombre'] ?? $actual['nombre'],
            ':apellido' => $data['apellido'] ?? $actual['apellido'],
            ':correo'   => $data['correo'] ?? $actual['correo'],
            ':telefono' => array_key_exists('telefono', $data) ? $data['telefono'] : $actual['telefono'],
            ':estado'   => $nuevoEstado,
            ':id_rol'   => (int)$rolRow['id_rol'],
            ':id'       => $id,
        ];
        if (!empty($data['contrasena'])) {
            $sets[] = 'contrasena = :contrasena';
            $params[':contrasena'] = $data['contrasena'];
        }

        $sql = 'UPDATE usuario SET ' . implode(', ', $sets) . ' WHERE id_usuario = :id';
        $this->execute($sql, $params);
        return $this->findById($id) ?? ['id_usuario' => $id];
    }

    public function registrarClienteConUsuario(array $data): array {
        $this->beginTransaction();
        try {
            $correo = strtolower(trim((string)$data['correo']));
            $existe = $this->findByEmail($correo);
            if ($existe) {
                throw new DomainConflictException('Ya existe una cuenta registrada con ese correo.');
            }

            $partes = preg_split('/\s+/', trim((string)$data['nombre']), 2) ?: ['', ''];
            $nombre = $partes[0];
            $apellido = $partes[1] ?? 'Cliente';

            $idUsuario = $this->create([
                'nombre'     => $nombre,
                'apellido'   => $apellido,
                'documento'  => $data['documento'],
                'correo'     => $correo,
                'telefono'   => $data['telefono'] ?? null,
                'contrasena' => $data['contrasena'],
                'rol'        => 'CLIENTE',
            ]);

            $cliExistente = $this->fetchOne(
                'SELECT id_cliente FROM vw_clientes_publico WHERE lower(correo) = lower(trim(:correo)) LIMIT 1',
                [':correo' => $correo]
            );
            if ($cliExistente) {
                $idCliente = (int)$cliExistente['id_cliente'];
            } else {
                $rowCli = $this->fetchOne(
                    "INSERT INTO cliente (tipo_documento, documento_cifrado, documento_hash, nombre, telefono, correo)
                     VALUES (:tipo_doc, fn_cifrar_documento(:doc_enc), fn_hash_documento(:doc_hash), :nombre, :telefono, lower(trim(:correo)))
                     RETURNING id_cliente",
                    [
                        ':tipo_doc' => $data['tipo_documento'] ?? 'CC',
                        ':doc_enc'  => $data['documento'],
                        ':doc_hash' => $data['documento'],
                        ':nombre'   => trim((string)$data['nombre']),
                        ':telefono' => $data['telefono'] ?? null,
                        ':correo'   => $correo,
                    ]
                );
                $idCliente = (int)($rowCli['id_cliente'] ?? 0);
            }

            $this->commit();
            return ['id_usuario' => $idUsuario, 'id_cliente' => $idCliente];
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }
}
