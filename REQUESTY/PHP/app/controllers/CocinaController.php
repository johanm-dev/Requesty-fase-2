<?php
class CocinaController extends Controller {
    public function index() {
        $this->requireRole(['ADMIN', 'CAJERO', 'MESERO']);
        $pedidoModel = $this->model('Pedido');
        $pedidos = $pedidoModel->getAll();

        $this->view('cocina/index', [
            'pageTitle'   => 'Cocina - Requesty',
            'currentPage' => 'cocina',
            'pedidos'     => $pedidos,
            'user'        => $this->getUser()
        ]);
    }
}
