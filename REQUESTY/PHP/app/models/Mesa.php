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
        return $this->updateStatuses([$numeroMesa], $estado);
    }

    public function updateStatuses(array $numerosMesa, string $estado): bool {
        $nuevoEstado = strtoupper(trim($estado));
        if (!in_array($nuevoEstado, ['DISPONIBLE', 'OCUPADA', 'RESERVADA', 'INACTIVA'], true)) {
            throw new DomainValidationException('El estado de mesa no es valido.');
        }

        $numerosMesa = array_values(array_unique(array_map('intval', $numerosMesa)));
        if ($numerosMesa === [] || min($numerosMesa) < 1) {
            throw new DomainValidationException('Selecciona al menos una mesa valida.');
        }

        if ($this->isDbConnected()) {
            $placeholders = [];
            $params = [];
            foreach ($numerosMesa as $index => $numeroMesa) {
                $placeholder = ':mesa' . $index;
                $placeholders[] = $placeholder;
                $params[$placeholder] = $numeroMesa;
            }

            $this->beginTransaction();
            try {
                $existentes = $this->fetchAll(
                    'SELECT numero_mesa FROM mesa WHERE numero_mesa IN (' . implode(', ', $placeholders) . ') FOR UPDATE',
                    $params
                );
                if (count($existentes) !== count($numerosMesa)) {
                    throw new DomainValidationException('Una o mas mesas seleccionadas ya no existen.');
                }

                $this->execute(
                    'UPDATE mesa SET estado = :estado WHERE numero_mesa IN (' . implode(', ', $placeholders) . ')',
                    array_merge([':estado' => $nuevoEstado], $params)
                );
                $this->commit();
                return true;
            } catch (Throwable $error) {
                $this->rollBack();
                throw $error;
            }
        }

        return NormalizedStore::withTransaction(static function (array &$state) use ($numerosMesa, $nuevoEstado): bool {
            $encontradas = [];
            foreach ($state['mesa'] as $mesa) {
                $numero = (int)$mesa['numero_mesa'];
                if (in_array($numero, $numerosMesa, true)) {
                    $encontradas[$numero] = true;
                }
            }
            if (count($encontradas) !== count($numerosMesa)) {
                throw new DomainValidationException('Una o mas mesas seleccionadas ya no existen.');
            }

            foreach ($state['mesa'] as &$mesa) {
                if (in_array((int)$mesa['numero_mesa'], $numerosMesa, true)) {
                    $mesa['estado'] = $nuevoEstado;
                }
            }
            unset($mesa);
            return true;
        }, true);
    }

    public function createMesa(int $capacidad): array {
        if ($capacidad < 1 || $capacidad > 12) {
            throw new DomainValidationException('La capacidad debe estar entre 1 y 12 puestos.');
        }

        if ($this->isDbConnected()) {
            $this->beginTransaction();
            try {
                $siguiente = $this->fetchOne('SELECT COALESCE(MAX(numero_mesa), 0) + 1 AS numero_mesa FROM mesa');
                $numeroMesa = (int)($siguiente['numero_mesa'] ?? 1);
                $codigoQr = sprintf('QR-MESA-%03d', $numeroMesa);
                $mesa = $this->fetchOne(
                    "INSERT INTO mesa (numero_mesa, capacidad, codigo_qr, estado)
                     VALUES (:numero, :capacidad, :qr, 'DISPONIBLE')
                     RETURNING id_mesa, numero_mesa, capacidad, codigo_qr, estado",
                    [':numero' => $numeroMesa, ':capacidad' => $capacidad, ':qr' => $codigoQr]
                );
                if ($mesa === null) {
                    throw new RuntimeException('No se pudo recuperar la mesa creada.');
                }
                $this->commit();
                return $mesa;
            } catch (Throwable $error) {
                $this->rollBack();
                throw $error;
            }
        }

        return NormalizedStore::withTransaction(static function (array &$state) use ($capacidad): array {
            $numeroMesa = 1;
            $idMesa = 1;
            foreach ($state['mesa'] as $mesa) {
                $numeroMesa = max($numeroMesa, (int)$mesa['numero_mesa'] + 1);
                $idMesa = max($idMesa, (int)$mesa['id_mesa'] + 1);
            }
            $mesa = [
                'id_mesa' => $idMesa,
                'numero_mesa' => $numeroMesa,
                'capacidad' => $capacidad,
                'codigo_qr' => sprintf('QR-MESA-%03d', $numeroMesa),
                'estado' => 'DISPONIBLE',
            ];
            $state['mesa'][] = $mesa;
            return $mesa;
        }, true);
    }
}
