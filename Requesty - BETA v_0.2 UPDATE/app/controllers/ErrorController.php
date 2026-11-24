<?php
/**
 * Controlador de Errores (404, 500)
 */
class ErrorController extends Controller {

    public function notFound() {
        http_response_code(404);
        $this->view('error/404', [
            'pageTitle' => 'Página no encontrada - Requesty',
            'user'      => $this->getUser()
        ]);
    }
}
