<?php
class CajaController extends Controller {
    public function index() {
        $this->requireRole(['ADMIN', 'CAJERO', 'CLIENTE']);
        $user = $this->getUser();
        $cajaModel = $this->model('Caja');
        $rol = strtoupper((string)($user['rol'] ?? ''));
        $facturas = ($rol === 'CLIENTE') ? [] : $cajaModel->getFacturas();

        $this->view('caja/index', [
            'pageTitle'   => 'Caja y Facturacion - Requesty',
            'currentPage' => 'caja',
            'facturas'    => $facturas,
            'user'        => $user
        ]);
    }
}
