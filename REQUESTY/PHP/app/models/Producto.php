<?php
/**
 * Modelo de Productos y Menu (PostgreSQL 17).
 */
class Producto extends Model {
    public function getAll(): array {
        $sql = "SELECT p.*, c.nombre AS categoria
                FROM producto p
                LEFT JOIN categoria c ON p.id_categoria = c.id_categoria
                WHERE p.disponible = TRUE
                ORDER BY p.id_producto ASC";
        return $this->fetchAll($sql);
    }

    public function getCategorias(): array {
        return $this->fetchAll('SELECT * FROM categoria WHERE activa = TRUE ORDER BY nombre ASC');
    }
}
