<?php
/**
 * Modelo de Mesas (PostgreSQL 17).
 */
class Mesa extends Model {
    public function getAll(): array {
        if ($this->isDbConnected()) {
            return $this->fetchAll('SELECT * FROM mesa ORDER BY numero_mesa ASC');
        }
        return NormalizedStore::withTransaction(static function (array &$state): array {
            $rows = $state['mesa'];
            usort($rows, static fn($a, $b) => $a['numero_mesa'] <=> $b['numero_mesa']);
            return $rows;
        }, false);
    }

    public function updateStatus(int $numeroMesa, string $estado): bool {
        $nuevoEstado = strtoupper(trim($estado));
        if ($this->isDbConnected()) {
            return $this->execute(
                'UPDATE mesa SET estado = :estado WHERE numero_mesa = :num',
                [':estado' => $nuevoEstado, ':num' => $numeroMesa]
            );
        }

        return NormalizedStore::withTransaction(static function (array &$state) use ($numeroMesa, $nuevoEstado): bool {
            foreach ($state['mesa'] as &$m) {
                if ((int)$m['numero_mesa'] === $numeroMesa) {
                    $m['estado'] = $nuevoEstado;
                    return true;
                }
            }
            return false;
        }, true);
    }
}
