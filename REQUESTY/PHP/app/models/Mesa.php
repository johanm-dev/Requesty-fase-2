<?php
/**
 * Modelo de Mesas (PostgreSQL 17).
 */
class Mesa extends Model {
    public function getAll(): array {
        return $this->fetchAll('SELECT * FROM mesa ORDER BY numero_mesa ASC');
    }

    public function updateStatus(int $numeroMesa, string $estado): bool {
        return $this->execute(
            'UPDATE mesa SET estado = :estado WHERE numero_mesa = :num',
            [':estado' => strtoupper(trim($estado)), ':num' => $numeroMesa]
        );
    }
}
