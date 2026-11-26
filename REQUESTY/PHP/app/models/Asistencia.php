<?php
/**
 * Modelo de Asistencia de Personal (PostgreSQL 17: tabla asistencia + vista vw_asistencia_horas).
 */
class Asistencia extends Model {
    public function getAll(?int $idUsuario = null, int $limit = 200, int $offset = 0): array {
        $limit = max(1, min($limit, 200));
        $offset = max(0, $offset);
        $sql = "SELECT v.id_asistencia, v.id_usuario, v.fecha, v.hora_entrada, v.hora_salida,
                       v.horas_trabajadas, v.horas_extra,
                       u.nombre, u.apellido, u.rol_nombre AS rol
                FROM vw_asistencia_horas v
                JOIN vw_usuarios_publico u ON u.id_usuario = v.id_usuario";
        $params = [];
        if ($idUsuario !== null) {
            $sql .= " WHERE v.id_usuario = :id_usuario";
            $params[':id_usuario'] = $idUsuario;
        }
        $sql .= " ORDER BY v.id_asistencia DESC LIMIT :limit OFFSET :offset";
        $params[':limit'] = $limit;
        $params[':offset'] = $offset;
        return $this->fetchAll($sql, $params);
    }

    public function registrarEntrada(int $idUsuario): array {
        $abierta = $this->fetchOne(
            'SELECT id_asistencia FROM asistencia WHERE id_usuario = :u AND hora_salida IS NULL ORDER BY id_asistencia DESC LIMIT 1',
            [':u' => $idUsuario]
        );
        if ($abierta) {
            throw new DomainConflictException('El usuario ya tiene una jornada abierta sin hora de salida.');
        }
        $row = $this->fetchOne(
            'INSERT INTO asistencia (id_usuario, hora_entrada) VALUES (:u, NOW()) RETURNING id_asistencia',
            [':u' => $idUsuario]
        );
        return $this->fetchOne('SELECT * FROM vw_asistencia_horas WHERE id_asistencia = :id', [':id' => (int)$row['id_asistencia']]) ?? $row;
    }

    public function registrarSalida(int $idAsistencia, ?int $onlyUserId = null): array {
        $actual = $this->fetchOne('SELECT * FROM asistencia WHERE id_asistencia = :id LIMIT 1', [':id' => $idAsistencia]);
        if (!$actual) {
            throw new DomainValidationException('Registro de asistencia no encontrado.');
        }
        if ($onlyUserId !== null && (int)$actual['id_usuario'] !== $onlyUserId) {
            throw new ForbiddenException('No puedes cerrar la jornada de otro empleado.');
        }
        if ($actual['hora_salida'] !== null) {
            throw new DomainConflictException('La jornada ya tiene hora de salida registrada.');
        }
        $this->execute('UPDATE asistencia SET hora_salida = NOW() WHERE id_asistencia = :id', [':id' => $idAsistencia]);
        return $this->fetchOne('SELECT * FROM vw_asistencia_horas WHERE id_asistencia = :id', [':id' => $idAsistencia]) ?? $actual;
    }
}
