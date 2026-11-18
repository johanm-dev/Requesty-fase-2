<?php
/**
 * Controlador de Caja y Facturación
 */
class CajaController extends Controller {

    public function index() {
        $cajaModel = $this->model('Caja');
        $facturas = $cajaModel->getFacturas();

        $this->view('caja/index', [
            'pageTitle'   => 'Caja y Facturación - Requesty',
            'currentPage' => 'caja',
            'facturas'    => $facturas,
            'user'        => $this->getUser()
        ]);
    }
}
