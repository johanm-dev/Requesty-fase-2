<?php
/**
 * Modelo de Caja, Pagos y Facturación
 */
class Caja extends Model {
    protected string $table = 'Caja';
    private string $jsonStorageFile;

    public function __construct() {
        parent::__construct();
        $this->jsonStorageFile = ROOTPATH . '/database/facturas_backup.json';
    }

    /**
     * Obtener historial de facturas
     */
    public function getFacturas(): array {
        if ($this->isDbConnected()) {
            $sql = "SELECT f.*, p.metodo_pago, p.id_pedido 
                    FROM Factura f 
                    LEFT JOIN Pago p ON f.id_pago = p.id_pago 
                    ORDER BY f.id_factura DESC";
            $facturas = $this->fetchAll($sql);
            if (!empty($facturas)) {
                return $facturas;
            }
        }
        return $this->getFromJson();
    }

    /**
     * Registrar cobro / factura
     */
    public function registrarFactura(array $datos): bool {
        $facturas = $this->getFromJson();
        $facturas[] = array_merge([
            'id' => count($facturas) + 1,
            'numero_factura' => 'FAC-' . str_pad((string)(count($facturas) + 1), 5, '0', STR_PAD_LEFT),
            'fecha' => date('Y-m-d H:i:s'),
            'estado_dian' => 'Aprobada'
        ], $datos);
        return $this->saveToJson($facturas);
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

    public function saveToJson(array $facturas): bool {
        $dir = dirname($this->jsonStorageFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return file_put_contents($this->jsonStorageFile, json_encode($facturas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
}
