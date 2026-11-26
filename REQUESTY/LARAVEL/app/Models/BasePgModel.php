<?php

namespace App\Models;

use App\Services\PgEncryptionContext;
use PDO;
use PDOStatement;

abstract class BasePgModel
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = PgEncryptionContext::getPdo();
    }

    protected function query(string $sql, array $params = []): PDOStatement
    {
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

    protected function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    protected function fetchOne(string $sql, array $params = []): ?array
    {
        $result = $this->query($sql, $params)->fetch();

        return $result ?: null;
    }

    protected function execute(string $sql, array $params = []): bool
    {
        $this->query($sql, $params);

        return true;
    }

    protected function beginTransaction(): void
    {
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
    }

    protected function commit(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }
    }

    protected function rollBack(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    protected function transaction(callable $callback): mixed
    {
        $startedHere = !$this->db->inTransaction();
        if ($startedHere) {
            $this->db->beginTransaction();
        }
        try {
            $result = $callback();
            if ($startedHere && $this->db->inTransaction()) {
                $this->db->commit();
            }

            return $result;
        } catch (\Throwable $e) {
            if ($startedHere && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
