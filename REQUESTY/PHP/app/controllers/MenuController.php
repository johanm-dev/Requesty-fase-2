<?php
class MenuController extends Controller {
    public function index() {
        $this->requireAuth();
        $productoModel = $this->model('Producto');
        $productos = $productoModel->getAll();
        $categorias = $productoModel->getCategorias();

        $this->view('menu/index', [
            'pageTitle'   => 'Menu - Requesty',
            'currentPage' => 'menu',
            'productos'   => $productos,
            'categorias'  => $categorias,
            'user'        => $this->getUser()
        ]);
    }
}
