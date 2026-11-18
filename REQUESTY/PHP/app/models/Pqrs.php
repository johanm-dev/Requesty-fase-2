<?php
/**
 * Modelo de PQRS
 */
class Pqrs extends Model {
    protected string $table = 'PQRS';
    private string $jsonStorageFile;

    public function __construct() {
        parent::__construct();
        $this->jsonStorageFile = ROOTPATH . '/database/pqrs_backup.json';
    }

    /**
     * Obtener todas las PQRS
     */
    public function getAll(): array {
        if ($this->isDbConnected()) {
            $sql = "SELECT pq.*, c.nombre AS cliente_nombre 
                    FROM PQRS pq 
                    LEFT JOIN Cliente c ON pq.id_cliente = c.id_cliente 
                    ORDER BY pq.id_pqrs DESC";
            $res = $this->fetchAll($sql);
            if (!empty($res)) return $res;
        }
        return $this->getFromJson();
    }

    /**
     * Registrar una nueva PQRS
     */
    public function create(array $data): bool {
        if ($this->isDbConnected()) {
            $sql = "INSERT INTO PQRS (tipo, descripcion, id_cliente, id_pedido, estado) 
                    VALUES (:tipo, :descripcion, :id_cliente, :id_pedido, 'Pendiente')";
            return $this->execute($sql, [
                ':tipo' => $data['tipo'],
                ':descripcion' => $data['descripcion'],
                ':id_cliente' => $data['id_cliente'] ?? null,
                ':id_pedido' => $data['id_pedido'] ?? null
            ]);
        }

        $all = $this->getFromJson();
        $all[] = [
            'id' => count($all) + 1,
            'tipo' => $data['tipo'],
            'cliente' => $data['cliente'] ?? 'Cliente anónimo',
            'pedido' => $data['pedido'] ?? '-',
            'descripcion' => $data['descripcion'],
            'fecha' => date('Y-m-d H:i'),
            'estado' => 'Pendiente'
        ];
        return $this->saveToJson($all);
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

    public function saveToJson(array $pqrs): bool {
        $dir = dirname($this->jsonStorageFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return file_put_contents($this->jsonStorageFile, json_encode($pqrs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
}
