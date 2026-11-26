<?php
class MesaController extends Controller {
    public function index() {
        $this->requireRole(['ADMIN', 'CAJERO', 'MESERO']);
        $mesaModel = $this->model('Mesa');
        $mesas = $mesaModel->getAll();

        $this->view('mesas/index', [
            'pageTitle'   => 'Gestion de Mesas - Requesty',
            'currentPage' => 'mesas',
            'mesas'       => $mesas,
            'user'        => $this->getUser()
        ]);
    }
}
