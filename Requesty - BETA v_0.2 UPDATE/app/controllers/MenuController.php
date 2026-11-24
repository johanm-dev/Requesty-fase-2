<?php
/**
 * Controlador de Menú y Catálogo
 */
class MenuController extends Controller {

    public function index() {
        $productoModel = $this->model('Producto');
        $productos = $productoModel->getAll();
        $categorias = $productoModel->getCategorias();

        $this->view('menu/index', [
            'pageTitle'   => 'Menú - Requesty',
            'currentPage' => 'menu',
            'productos'   => $productos,
            'categorias'  => $categorias,
            'user'        => $this->getUser()
        ]);
    }
}
