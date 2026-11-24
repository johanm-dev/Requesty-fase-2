<?php
class PqrsController extends Controller {
    public function index() {
        $this->requireAuth();
        $user = $this->getUser();
        /** @var Pqrs $pqrsModel */
        $pqrsModel = $this->model('Pqrs');

        $filterCliente = null;
        if (strtoupper((string)($user['rol'] ?? '')) === 'CLIENTE') {
            /** @var Cliente $cliModel */
            $cliModel = $this->model('Cliente');
            $cli = $cliModel->findByEmail((string)($user['correo'] ?? ''));
            $filterCliente = $cli ? (int)$cli['id_cliente'] : -1;
        }
        $pqrs = $pqrsModel->getAll($filterCliente);

        $this->view('pqrs/index', [
            'pageTitle'   => 'PQRS - Requesty',
            'currentPage' => 'pqrs',
            'pqrs'        => $pqrs,
            'user'        => $user
        ]);
    }
}
