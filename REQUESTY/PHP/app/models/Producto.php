<?php
/**
 * Modelo de Productos y Menú
 */
class Producto extends Model {
    protected string $table = 'Producto';
    private string $jsonStorageFile;

    public function __construct() {
        parent::__construct();
        $this->jsonStorageFile = ROOTPATH . '/database/productos_backup.json';
    }

    /**
     * Obtener todos los productos del menú
     */
    public function getAll(): array {
        if ($this->isDbConnected()) {
            $sql = "SELECT p.*, c.nombre AS categoria 
                    FROM Producto p 
                    LEFT JOIN Categoria c ON p.id_categoria = c.id_categoria 
                    WHERE p.disponible = 1 
                    ORDER BY p.id_producto ASC";
            $productos = $this->fetchAll($sql);
            if (!empty($productos)) {
                return $productos;
            }
        }
        return $this->getFromJson();
    }

    /**
     * Obtener lista de categorías
     */
    public function getCategorias(): array {
        if ($this->isDbConnected()) {
            $sql = "SELECT * FROM Categoria ORDER BY nombre ASC";
            $cats = $this->fetchAll($sql);
            if (!empty($cats)) return $cats;
        }
        return [
            ['id_categoria' => 1, 'nombre' => 'Platos típicos'],
            ['id_categoria' => 2, 'nombre' => 'Sopas'],
            ['id_categoria' => 3, 'nombre' => 'Carnes'],
            ['id_categoria' => 4, 'nombre' => 'Bebidas']
        ];
    }

    private function getFromJson(): array {
        if (file_exists($this->jsonStorageFile)) {
            $data = file_get_contents($this->jsonStorageFile);
            $decoded = json_decode($data, true);
            if (is_array($decoded) && !empty($decoded)) {
                return $decoded;
            }
        }

        // Productos semilla iniciales
        $default = [
            ['id_producto' => 1, 'nombre' => 'Bandeja Paisa', 'precio' => 45000, 'categoria' => 'Platos típicos', 'descripcion' => 'Arroz, frijoles, carne molida, chicharrón, huevo, tajada, chorizo, arepa y aguacate', 'imagen' => 'img/tarjeta/bandeja-paisa.jpg', 'tiempo' => 15],
            ['id_producto' => 2, 'nombre' => 'Caldo de Costilla', 'precio' => 13000, 'categoria' => 'Sopas', 'descripcion' => 'Costilla de res, papa, cilantro y cebolla', 'imagen' => 'img/tarjeta/caldo-de-costilla.jpg', 'tiempo' => 10],
            ['id_producto' => 3, 'nombre' => 'Huevos Pericos', 'precio' => 8500, 'categoria' => 'Platos típicos', 'descripcion' => 'Huevos revueltos con tomate y cebolla, acompañados de arepa', 'imagen' => 'img/tarjeta/huevos-pericos.jpg', 'tiempo' => 8],
            ['id_producto' => 4, 'nombre' => 'Jugo de Mora', 'precio' => 4500, 'categoria' => 'Bebidas', 'descripcion' => 'Jugo natural en agua o leche', 'imagen' => 'img/tarjeta/jugo-de-mora.jpg', 'tiempo' => 5],
            ['id_producto' => 5, 'nombre' => 'Churrasco', 'precio' => 28000, 'categoria' => 'Carnes', 'descripcion' => 'Corte de res tierno a la plancha con papas a la francesa y ensalada', 'imagen' => 'img/tarjeta/bandeja-paisa.jpg', 'tiempo' => 20]
        ];
        $this->saveToJson($default);
        return $default;
    }

    public function saveToJson(array $productos): bool {
        $dir = dirname($this->jsonStorageFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return file_put_contents($this->jsonStorageFile, json_encode($productos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    }
}
