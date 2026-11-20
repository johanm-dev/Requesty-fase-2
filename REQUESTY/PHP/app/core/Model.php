<?php
/**
 * Modelo Base MVC sobre PostgreSQL 17 (consultas 100% parametrizadas + motor normalizado de respaldo).
 */
abstract class Model {
    protected ?PDO $db = null;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function isDbConnected(): bool {
        return $this->db !== null;
    }

    protected function query(string $sql, array $params = []): PDOStatement {
        if ($this->db === null) {
            throw new RuntimeException('Conexion PostgreSQL no activa.');
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    protected function fetchAll(string $sql, array $params = []): array {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    protected function fetchOne(string $sql, array $params = []): ?array {
        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    protected function execute(string $sql, array $params = []): bool {
        $this->query($sql, $params);
        return true;
    }

    protected function beginTransaction(): void {
        if ($this->db !== null && !$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
    }

    protected function commit(): void {
        if ($this->db !== null && $this->db->inTransaction()) {
            $this->db->commit();
        }
    }

    protected function rollBack(): void {
        if ($this->db !== null && $this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }
}
