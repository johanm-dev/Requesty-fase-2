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
}
