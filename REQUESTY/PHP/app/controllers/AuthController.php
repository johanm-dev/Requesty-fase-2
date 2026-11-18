<?php
/**
 * Controlador de Autenticación (Login, Registro, Logout)
 */
class AuthController extends Controller {

    public function index() {
        if ($this->isLoggedIn()) {
            $user = $this->getUser();
            $rolKey = strtolower(trim($user['rol'] ?? ''));
            $destination = PAGINA_INICIAL_POR_ROL[$rolKey] ?? 'mesas';
            $this->redirect($destination);
        } else {
            $this->login();
        }
    }

    /**
     * Inicio de sesión
     */
    public function login() {
        $error = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $email = $_POST['correo'] ?? $_POST['usuario'] ?? '';
            $password = $_POST['contrasena'] ?? $_POST['contraseña'] ?? '';

            // Si es petición JSON o AJAX
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') 
                      || (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);

            if (empty($email) || empty($password)) {
                $error = 'Por favor ingresa tu correo y contraseña.';
                if ($isAjax) $this->json(['success' => false, 'message' => $error], 400);
            } else {
                $userModel = $this->model('Usuario');
                $authResult = $userModel->authenticate($email, $password);

                if (is_array($authResult) && isset($authResult['error']) && $authResult['error'] === 'inactive') {
                    $error = 'La cuenta está inactiva. Contacta al administrador.';
                    if ($isAjax) $this->json(['success' => false, 'message' => $error], 403);
                } elseif ($authResult) {
                    $_SESSION['usuario'] = [
                        'id'     => $authResult['id_usuario'] ?? $authResult['id'] ?? 1,
                        'nombre' => $authResult['nombre'],
                        'correo' => $authResult['correo'],
                        'rol'    => $authResult['rol'] ?? 'Administrador',
                    ];

                    $rolKey = strtolower(trim($_SESSION['usuario']['rol']));
                    $redirectUrl = PAGINA_INICIAL_POR_ROL[$rolKey] ?? 'mesas';

                    if ($isAjax) {
                        $this->json([
                            'success'     => true,
                            'redirectUrl' => url($redirectUrl),
                            'usuario'     => $_SESSION['usuario']
                        ]);
                    } else {
                        $this->redirect($redirectUrl);
                    }
                } else {
                    $error = 'Correo o contraseña incorrectos.';
                    if ($isAjax) $this->json(['success' => false, 'message' => $error], 401);
                }
            }
        }

        $this->view('auth/login', [
            'pageTitle' => 'Requesty - Inicio de Sesión',
            'error'     => $error
        ]);
    }

    /**
     * Registro de nuevo usuario
     */
    public function registro() {
        $error = null;
        $success = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                      || (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);

            $nombre = trim($_POST['nombre'] ?? '');
            $correo = trim($_POST['correo'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $rol = trim($_POST['rol'] ?? 'Cliente');
            $contrasena = $_POST['contrasena'] ?? '';
            $confirmar = $_POST['confirmar_contrasena'] ?? '';

            if (empty($nombre) || empty($correo) || empty($contrasena)) {
                $error = 'Por favor completa todos los campos obligatorios.';
            } elseif ($contrasena !== $confirmar) {
                $error = 'Las contraseñas no coinciden.';
            } else {
                $userModel = $this->model('Usuario');
                $existente = $userModel->findByEmail($correo);

                if ($existente) {
                    $error = 'Ya existe una cuenta con este correo electrónico.';
                } else {
                    $creado = $userModel->create([
                        'nombre'     => $nombre,
                        'correo'     => $correo,
                        'telefono'   => $telefono,
                        'rol'        => $rol,
                        'contrasena' => $contrasena
                    ]);

                    if ($creado) {
                        if ($isAjax) {
                            $this->json([
                                'success'     => true,
                                'message'     => 'Cuenta creada exitosamente.',
                                'redirectUrl' => url('login')
                            ]);
                        } else {
                            $this->redirect('login');
                        }
                    } else {
                        $error = 'No fue posible registrar la cuenta. Inténtalo de nuevo.';
                    }
                }
            }

            if ($isAjax && $error) {
                $this->json(['success' => false, 'message' => $error], 400);
            }
        }

        $this->view('auth/registro', [
            'pageTitle' => 'Requesty - Registro de Cuenta',
            'error'     => $error,
            'success'   => $success
        ]);
    }

    /**
     * Cerrar sesión
     */
    public function logout() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            unset($_SESSION['usuario']);
            session_destroy();
        }
        $this->redirect('login');
    }
}
