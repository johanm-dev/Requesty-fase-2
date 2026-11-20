<?php
/**
 * Controlador de Mesas
 */
class MesaController extends Controller {

    public function index() {
        $mesaModel = $this->model('Mesa');
        $mesas = $mesaModel->getAll();

        $this->view('mesas/index', [
            'pageTitle'   => 'Gestión de Mesas - Requesty',
            'currentPage' => 'mesas',
            'mesas'       => $mesas,
            'user'        => $this->getUser()
        ]);
    }
}
