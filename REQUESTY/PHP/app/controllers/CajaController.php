<?php
class CajaController extends Controller {
    public function index() {
        $this->requireRole(['ADMIN', 'CAJERO']);
        $cajaModel = $this->model('Caja');
        $facturas = $cajaModel->getFacturas();

        $this->view('caja/index', [
            'pageTitle'   => 'Caja y Facturacion - Requesty',
            'currentPage' => 'caja',
            'facturas'    => $facturas,
            'user'        => $this->getUser()
        ]);
    }
}
