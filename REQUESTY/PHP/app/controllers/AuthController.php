<?php
/**
 * Controlador de Autenticacion Web (Login, Registro endurecido con CSRF y AES-256 PII, Logout).
 */
class AuthController extends Controller {

    public function index() {
        if ($this->isLoggedIn()) {
            $user = $this->getUser();
            $rolKey = strtolower(trim((string)($user['rol'] ?? '')));
            $destination = PAGINA_INICIAL_POR_ROL[$rolKey] ?? 'mesas';
            $this->redirect($destination);
        } else {
            $this->login();
        }
    }

    public function login() {
        $error = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            RateLimiter::check('auth_login', 5, 60);

            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');

            if (!$isAjax) {
                $this->requireCsrfForWebPost();
            }

            $email = trim((string)($_POST['correo'] ?? $_POST['usuario'] ?? ''));
            $password = (string)($_POST['contrasena'] ?? $_POST['contraseña'] ?? '');

            if ($email === '' || $password === '') {
                $error = 'Por favor ingresa tu correo y contrasena.';
                if ($isAjax) {
                    $this->json(['success' => false, 'message' => $error], 400);
                }
            } else {
                /** @var Usuario $userModel */
                $userModel = $this->model('Usuario');
                $authResult = $userModel->authenticate($email, $password);

                if ($authResult) {
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_regenerate_id(true);
                        CsrfService::rotate();
                    }
                    $_SESSION['usuario'] = [
                        'id'     => (int)$authResult['id_usuario'],
                        'nombre' => (string)$authResult['nombre'],
                        'correo' => (string)$authResult['correo'],
                        'rol'    => self::normalizeRole((string)$authResult['rol']),
                    ];

                    $rolKey = strtolower(trim($_SESSION['usuario']['rol']));
                    $redirectUrl = PAGINA_INICIAL_POR_ROL[$rolKey] ?? 'mesas';

                    if ($isAjax) {
                        $this->json([
                            'success'     => true,
                            'token'       => JwtService::issue($authResult),
                            'redirectUrl' => url($redirectUrl),
                            'usuario'     => $_SESSION['usuario'],
                        ]);
                    }
                    $this->redirect($redirectUrl);
                } else {
                    $error = 'Correo o contrasena incorrectos.';
                    if ($isAjax) {
                        $this->json(['success' => false, 'message' => $error], 401);
                    }
                }
            }
        }

        $this->view('auth/login', [
            'pageTitle' => 'Requesty - Inicio de Sesion',
            'error'     => $error,
        ]);
    }

    public function registro() {
        $error = null;

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            RateLimiter::check('auth_register', 5, 60);

            $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
                || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');

            if (!$isAjax) {
                $this->requireCsrfForWebPost();
            }

            $nombre = trim((string)($_POST['nombre'] ?? ''));
            $correo = strtolower(trim((string)($_POST['correo'] ?? '')));
            $telefono = trim((string)($_POST['telefono'] ?? ''));
            $documento = trim((string)($_POST['documento'] ?? ''));
            $contrasena = (string)($_POST['contrasena'] ?? '');
            $confirmar = (string)($_POST['confirmar_contrasena'] ?? '');

            if ($nombre === '' || $correo === '' || $contrasena === '') {
                $error = 'Por favor completa todos los campos obligatorios.';
            } elseif ($contrasena !== $confirmar) {
                $error = 'Las contrasenas no coinciden.';
            } else {
                if ($documento === '') {
                    $documento = (string)time();
                }
                /** @var Cliente $clienteModel */
                $clienteModel = $this->model('Cliente');
                $id = $clienteModel->create([
                    'tipo_documento' => 'CC',
                    'documento'      => $documento,
                    'nombre'         => $nombre,
                    'telefono'       => $telefono,
                    'correo'         => $correo,
                ]);
                if ($id > 0) {
                    if ($isAjax) {
                        $this->json([
                            'success'     => true,
                            'message'     => 'Registro completado exitosamente.',
                            'redirectUrl' => url('login'),
                        ], 201);
                    }
                    $this->redirect('login');
                }
                $error = 'No fue posible registrar la cuenta.';
            }

            if ($isAjax && $error) {
                $this->json(['success' => false, 'message' => $error], 422);
            }
        }

        $this->view('auth/registro', [
            'pageTitle' => 'Requesty - Registro',
            'error'     => $error,
            'success'   => null,
        ]);
    }

    public function logout() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }
        $this->redirect('login');
    }
}
