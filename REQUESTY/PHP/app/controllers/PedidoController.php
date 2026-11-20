<?php
/**
 * Controlador de Pedidos
 */
class PedidoController extends Controller {

    public function index() {
        $pedidoModel = $this->model('Pedido');
        $pedidos = $pedidoModel->getAll();

        $this->view('pedidos/index', [
            'pageTitle'   => 'Gestión de Pedidos - Requesty',
            'currentPage' => 'pedidos',
            'pedidos'     => $pedidos,
            'user'        => $this->getUser()
        ]);
    }
}
