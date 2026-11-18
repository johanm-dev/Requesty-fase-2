<?php
class ReporteController extends Controller {
    public function index() {
        $this->requireRole(['ADMIN']);
        $usuarioModel = $this->model('Usuario');
        $usuarios = $usuarioModel->getAll();

        $cajaModel = $this->model('Caja');
        $facturas = $cajaModel->getFacturas();

        $this->view('reportes/index', [
            'pageTitle'   => 'Reportes y Administracion - Requesty',
            'currentPage' => 'reportes',
            'usuarios'    => $usuarios,
            'facturas'    => $facturas,
            'user'        => $this->getUser()
        ]);
    }

    public function cuentas() {
        $this->requireRole(['ADMIN']);
        $usuarioModel = $this->model('Usuario');
        $usuarios = $usuarioModel->getAll();

        $this->view('cuentas/index', [
            'pageTitle'   => 'Cuentas del Personal - Requesty',
            'currentPage' => 'reportes',
            'usuarios'    => $usuarios,
            'user'        => $this->getUser()
        ]);
    }
}
