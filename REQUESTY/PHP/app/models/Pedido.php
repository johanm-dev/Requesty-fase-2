<?php
/**
 * Modelo de Pedidos
 */
class Pedido extends Model {
    protected string $table = 'Pedido';
    private string $jsonStorageFile;

    public function __construct() {
        parent::__construct();
        $this->jsonStorageFile = ROOTPATH . '/database/pedidos_backup.json';
    }

    /**
     * Obtener todos los pedidos
     */
    public function getAll(): array {
        if ($this->isDbConnected()) {
            $sql = "SELECT p.*, m.numero_mesa, c.nombre AS cliente_nombre, u.nombre AS mesero_nombre
                    FROM Pedido p
                    LEFT JOIN Mesa m ON p.id_mesa = m.id_mesa
                    LEFT JOIN Cliente c ON p.id_cliente = c.id_cliente
                    LEFT JOIN Usuario u ON p.id_mesero = u.id_usuario
                    ORDER BY p.id_pedido DESC";
            $pedidos = $this->fetchAll($sql);
            if (!empty($pedidos)) {
                return $pedidos;
            }
        }
        return $this->getFromJson();
    }

    /**
     * Obtener pedido por ID
     */
    public function getById(int $id): ?array {
        if ($this->isDbConnected()) {
            $sql = "SELECT p.*, m.numero_mesa, c.nombre AS cliente_nombre 
                    FROM Pedido p
                    LEFT JOIN Mesa m ON p.id_mesa = m.id_mesa
                    LEFT JOIN Cliente c ON p.id_cliente = c.id_cliente
                    WHERE p.id_pedido = :id LIMIT 1";
            $res = $this->fetchOne($sql, [':id' => $id]);
            if ($res) return $res;
        }

        $all = $this->getFromJson();
        foreach ($all as $item) {
            if ((int)($item['id'] ?? $item['id_pedido'] ?? 0) === $id) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Cambiar estado de un pedido (En cola, En preparación, Listo, Entregado, Cancelado)
     */
    public function updateStatus(int $id, string $estado): bool {
        if ($this->isDbConnected()) {
            $sql = "UPDATE Pedido SET estado = :estado WHERE id_pedido = :id";
            if ($this->execute($sql, [':estado' => $estado, ':id' => $id])) {
                return true;
            }
        }

        $all = $this->getFromJson();
        $updated = false;
        foreach ($all as &$p) {
            if ((int)($p['id'] ?? $p['id_pedido'] ?? 0) === $id) {
                $p['estado'] = $estado;
                $updated = true;
                break;
            }
        }
        if ($updated) {
            return $this->saveToJson($all);
        }
        return false;
    }

    private function getFromJson(): array {
        if (file_exists($this->jsonStorageFile)) {
            $data = file_get_contents($this->jsonStorageFile);
            $decoded = json_decode($data, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    public function saveToJson(array $pedidos): bool {
        $dir = dirname($this->jsonStorageFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return file_put_contents($this->jsonStorageFile, json_encode($pedidos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
}
