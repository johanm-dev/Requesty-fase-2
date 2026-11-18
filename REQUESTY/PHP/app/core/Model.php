<?php
/**
 * Modelo Base MVC
 */
abstract class Model {
    protected ?PDO $db = null;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Verificar si la BD está conectada
     */
    public function isDbConnected(): bool {
        return $this->db !== null;
    }

    /**
     * Ejecutar una consulta con parámetros seguros
     */
    protected function query(string $sql, array $params = []): ?PDOStatement {
        if (!$this->isDbConnected()) {
            return null;
        }
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Error en consulta SQL: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Obtener todos los registros de una consulta
     */
    protected function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->query($sql, $params);
        return $stmt ? $stmt->fetchAll() : [];
    }

    /**
     * Obtener un solo registro
     */
    protected function fetchOne(string $sql, array $params = []): ?array {
        $stmt = $this->query($sql, $params);
        $result = $stmt ? $stmt->fetch() : null;
        return $result ?: null;
    }

    /**
     * Ejecutar INSERT, UPDATE o DELETE
     */
    protected function execute(string $sql, array $params = []): bool {
        $stmt = $this->query($sql, $params);
        return $stmt !== null;
    }

    /**
     * Obtener último ID insertado
     */
    protected function lastInsertId(): int {
        return $this->isDbConnected() ? (int)$this->db->lastInsertId() : 0;
    }
}
