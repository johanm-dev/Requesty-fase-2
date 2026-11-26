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
        // Redireccionar a vista de cuentas del personal en reportes
        header("Location: " . url('reportes') . "#cuentas");
        exit;
    }

    public function inventario() {
        // Redireccionar a vista de inventario en reportes
        header("Location: " . url('reportes') . "#inventario");
        exit;
    }
}
