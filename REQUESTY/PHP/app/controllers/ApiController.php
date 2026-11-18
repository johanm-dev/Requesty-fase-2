<?php
/**
 * Controlador de API REST / AJAX para comunicación con Frontend
 */
class ApiController extends Controller {

    public function sesion() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            $email = $input['correo'] ?? $input['usuario'] ?? '';
            $pass  = $input['contrasena'] ?? $input['contraseña'] ?? '';

            $userModel = $this->model('Usuario');
            $user = $userModel->authenticate($email, $pass);
            if ($user && !isset($user['error'])) {
                $_SESSION['usuario'] = [
                    'id'     => $user['id_usuario'] ?? $user['id'] ?? 1,
                    'nombre' => $user['nombre'],
                    'correo' => $user['correo'],
                    'rol'    => $user['rol'] ?? 'Administrador'
                ];
                $this->json(['success' => true, 'usuario' => $_SESSION['usuario']]);
            } else {
                $this->json(['success' => false, 'message' => 'Credenciales inválidas'], 401);
            }
        } else {
            $this->json([
                'autenticado' => $this->isLoggedIn(),
                'usuario'     => $this->getUser()
            ]);
        }
    }

    public function usuarios() {
        $userModel = $this->model('Usuario');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            if (isset($input['actualizar_lista']) && is_array($input['usuarios'])) {
                $saved = $userModel->saveToJson($input['usuarios']);
                $this->json(['success' => $saved]);
            } else {
                $creado = $userModel->create($input);
                $this->json(['success' => $creado]);
            }
        } else {
            $this->json($userModel->getAll());
        }
    }

    public function pedidos() {
        $pedidoModel = $this->model('Pedido');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            if (isset($input['actualizar_lista']) && is_array($input['pedidos'])) {
                $saved = $pedidoModel->saveToJson($input['pedidos']);
                $this->json(['success' => $saved]);
            } elseif (isset($input['id']) && isset($input['estado'])) {
                $updated = $pedidoModel->updateStatus((int)$input['id'], $input['estado']);
                $this->json(['success' => $updated]);
            } else {
                $this->json(['success' => false, 'message' => 'Parámetros insuficientes'], 400);
            }
        } else {
            $this->json($pedidoModel->getAll());
        }
    }

    public function mesas() {
        $mesaModel = $this->model('Mesa');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            if (isset($input['actualizar_lista']) && is_array($input['mesas'])) {
                $saved = $mesaModel->saveToJson($input['mesas']);
                $this->json(['success' => $saved]);
            } elseif (isset($input['numero']) && isset($input['estado'])) {
                $updated = $mesaModel->updateStatus((int)$input['numero'], $input['estado']);
                $this->json(['success' => $updated]);
            } else {
                $this->json(['success' => false], 400);
            }
        } else {
            $this->json($mesaModel->getAll());
        }
    }

    public function productos() {
        $prodModel = $this->model('Producto');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            if (isset($input['actualizar_lista']) && is_array($input['productos'])) {
                $saved = $prodModel->saveToJson($input['productos']);
                $this->json(['success' => $saved]);
            }
        }
        $this->json($prodModel->getAll());
    }

    public function caja() {
        $cajaModel = $this->model('Caja');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            if (isset($input['actualizar_lista']) && is_array($input['facturas'])) {
                $saved = $cajaModel->saveToJson($input['facturas']);
                $this->json(['success' => $saved]);
            } else {
                $saved = $cajaModel->registrarFactura($input);
                $this->json(['success' => $saved]);
            }
        } else {
            $this->json($cajaModel->getFacturas());
        }
    }

    public function pqrs() {
        $pqrsModel = $this->model('Pqrs');
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
            if (isset($input['actualizar_lista']) && is_array($input['pqrs'])) {
                $saved = $pqrsModel->saveToJson($input['pqrs']);
                $this->json(['success' => $saved]);
            } else {
                $created = $pqrsModel->create($input);
                $this->json(['success' => $created]);
            }
        } else {
            $this->json($pqrsModel->getAll());
        }
    }
}
