<?php
/**
 * Modelo de Mesas
 */
class Mesa extends Model {
    protected string $table = 'Mesa';
    private string $jsonStorageFile;

    public function __construct() {
        parent::__construct();
        $this->jsonStorageFile = ROOTPATH . '/database/mesas_backup.json';
    }

    /**
     * Obtener todas las mesas
     */
    public function getAll(): array {
        if ($this->isDbConnected()) {
            $sql = "SELECT * FROM Mesa ORDER BY numero_mesa ASC";
            $mesas = $this->fetchAll($sql);
            if (!empty($mesas)) {
                return $mesas;
            }
        }
        return $this->getFromJson();
    }

    /**
     * Actualizar estado de una mesa
     */
    public function updateStatus(int $numeroMesa, string $estado): bool {
        if ($this->isDbConnected()) {
            $sql = "UPDATE Mesa SET estado = :estado WHERE numero_mesa = :num";
            return $this->execute($sql, [':estado' => $estado, ':num' => $numeroMesa]);
        }

        $all = $this->getFromJson();
        foreach ($all as &$m) {
            if ((int)$m['numero'] === $numeroMesa) {
                $m['estado'] = $estado;
                break;
            }
        }
        return $this->saveToJson($all);
    }

    private function getFromJson(): array {
        if (file_exists($this->jsonStorageFile)) {
            $data = file_get_contents($this->jsonStorageFile);
            $decoded = json_decode($data, true);
            if (is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }
        }

        // 18 mesas por defecto según SQL
        $mesas = [];
        for ($i = 1; $i <= 18; $i++) {
            $mesas[] = [
                'id_mesa'     => $i,
                'numero'      => $i,
                'numero_mesa' => $i,
                'capacidad'   => 4,
                'estado'      => $i <= 3 ? 'Ocupada' : 'Libre',
                'clientes'    => $i <= 3 ? rand(2, 4) : 0,
                'tiempo'      => $i <= 3 ? '25 min' : '-'
            ];
        }
        $this->saveToJson($mesas);
        return $mesas;
    }

    public function saveToJson(array $mesas): bool {
        $dir = dirname($this->jsonStorageFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return file_put_contents($this->jsonStorageFile, json_encode($mesas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
}
