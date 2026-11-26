<?php
/**
 * Modelo Base MVC sobre PostgreSQL 17 (consultas 100% parametrizadas).
 */
abstract class Model {
    protected PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function isDbConnected(): bool {
        return true;
    }

    protected function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $paramKey = is_int($key) ? ($key + 1) : $key;
            $type = is_int($val)
                ? PDO::PARAM_INT
                : (is_bool($val) ? PDO::PARAM_BOOL : ($val === null ? PDO::PARAM_NULL : PDO::PARAM_STR));
            $stmt->bindValue($paramKey, $val, $type);
        }
        $stmt->execute();
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
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
    }

    protected function commit(): void {
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
    }

    protected function rollBack(): void {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }
}
