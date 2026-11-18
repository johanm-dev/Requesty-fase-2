<?php
/**
 * Controlador de Reportes y Administración
 */
class ReporteController extends Controller {

    public function index() {
        $usuarioModel = $this->model('Usuario');
        $usuarios = $usuarioModel->getAll();

        $cajaModel = $this->model('Caja');
        $facturas = $cajaModel->getFacturas();

        $this->view('reportes/index', [
            'pageTitle'   => 'Reportes y Administración - Requesty',
            'currentPage' => 'reportes',
            'usuarios'    => $usuarios,
            'facturas'    => $facturas,
            'user'        => $this->getUser()
        ]);
    }

    public function cuentas() {
        // Redireccionar o mostrar vista de cuentas
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
