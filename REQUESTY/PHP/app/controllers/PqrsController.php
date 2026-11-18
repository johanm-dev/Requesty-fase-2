<?php
class PqrsController extends Controller {
    public function index() {
        $this->requireAuth();
        $pqrsModel = $this->model('Pqrs');
        $pqrs = $pqrsModel->getAll();

        $this->view('pqrs/index', [
            'pageTitle'   => 'PQRS - Requesty',
            'currentPage' => 'pqrs',
            'pqrs'        => $pqrs,
            'user'        => $this->getUser()
        ]);
    }
}
