<?php
class PedidoController extends Controller {
    public function index() {
        $this->requireRole(['ADMIN', 'CAJERO', 'MESERO']);
        $user = $this->getUser();
        $pedidoModel = $this->model('Pedido');
        $filterMesero = (strtoupper((string)$user['rol']) === 'MESERO') ? (int)$user['id'] : null;
        $pedidos = $pedidoModel->getAll($filterMesero);

        $this->view('pedidos/index', [
            'pageTitle'   => 'Gestion de Pedidos - Requesty',
            'currentPage' => 'pedidos',
            'pedidos'     => $pedidos,
            'user'        => $user
        ]);
    }
}
