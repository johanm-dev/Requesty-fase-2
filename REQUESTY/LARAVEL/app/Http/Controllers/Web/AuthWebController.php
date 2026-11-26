<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Services\ActivityLogger;
use App\Services\DtoValidator;
use App\Services\FileRateLimiter;
use App\Services\JwtService;
use Illuminate\Http\Request;

class AuthWebController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->session()->get('usuario');
        if (is_array($user) && !empty($user['id'])) {
            $rolKey = strtolower(trim((string) ($user['rol'] ?? '')));
            $dest = config('requesty.pagina_inicial_por_rol.' . $rolKey, 'mesas');

            return redirect('/' . ltrim($dest, '/'));
        }

        return $this->inicio();
    }

    public function inicio()
    {
        return response()->view('inicio.index', [
            'pageTitle' => 'Requesty - Gestión inteligente para restaurantes',
            'currentPage' => 'inicio',
        ]);
    }

    public function login(Request $request)
    {
        $error = null;

        if ($request->isMethod('post')) {
            FileRateLimiter::check($request, 'auth_login', 5, 60);
            $isAjax = $request->ajax() || $request->expectsJson();

            $email = trim((string) ($request->input('correo') ?? $request->input('usuario') ?? ''));
            $password = (string) ($request->input('contrasena') ?? $request->input('contraseña') ?? '');

            if ($email === '' || $password === '') {
                $error = 'Por favor ingresa tu correo y contrasena.';
                if ($isAjax) {
                    return response()->json(['success' => false, 'message' => $error], 400);
                }
            } else {
                $authResult = (new Usuario())->authenticate($email, $password);

                if ($authResult) {
                    $request->session()->regenerate();
                    $request->session()->regenerateToken();

                    $sessionUser = [
                        'id' => (int) $authResult['id_usuario'],
                        'nombre' => (string) $authResult['nombre'],
                        'correo' => (string) $authResult['correo'],
                        'rol' => strtoupper((string) $authResult['rol']),
                    ];
                    $request->session()->put('usuario', $sessionUser);
                    ActivityLogger::record((int) $authResult['id_usuario'], 'Inicio de sesion web', 'AUTH', $request);

                    $rolKey = strtolower(trim($sessionUser['rol']));
                    $redirectRoute = config('requesty.pagina_inicial_por_rol.' . $rolKey, 'mesas');
                    $redirectUrl = url('/' . ltrim($redirectRoute, '/'));

                    if ($isAjax) {
                        return response()->json([
                            'success' => true,
                            'token' => JwtService::issue($authResult),
                            'csrf_token' => $request->session()->token(),
                            'redirectUrl' => $redirectUrl,
                            'usuario' => $sessionUser,
                        ]);
                    }

                    return redirect($redirectUrl);
                }

                $error = 'Correo o contrasena incorrectos.';
                if ($isAjax) {
                    return response()->json(['success' => false, 'message' => $error], 401);
                }
            }
        }

        return response()->view('auth.login', [
            'pageTitle' => 'Requesty - Inicio de Sesion',
            'error' => $error,
        ]);
    }

    public function registro(Request $request)
    {
        $error = null;

        if ($request->isMethod('post')) {
            FileRateLimiter::check($request, 'auth_register', 5, 60);
            $isAjax = $request->ajax() || $request->expectsJson();

            $nombre = trim((string) $request->input('nombre', ''));
            $correo = strtolower(trim((string) $request->input('correo', '')));
            $telefono = trim((string) $request->input('telefono', ''));
            $documento = trim((string) $request->input('documento', ''));
            $contrasena = (string) $request->input('contrasena', '');
            $confirmar = (string) $request->input('confirmar_contrasena', '');

            $pwdError = $contrasena !== '' ? DtoValidator::validatePasswordStrength($contrasena) : null;
            if ($nombre === '' || $correo === '' || $documento === '' || $contrasena === '') {
                $error = 'Por favor completa todos los campos obligatorios.';
            } elseif ($pwdError !== null) {
                $error = $pwdError;
            } elseif ($contrasena !== $confirmar) {
                $error = 'Las contrasenas no coinciden.';
            } else {
                $reg = (new Usuario())->registrarClienteConUsuario([
                    'tipo_documento' => 'CC',
                    'documento' => $documento,
                    'nombre' => $nombre,
                    'telefono' => $telefono,
                    'correo' => $correo,
                    'contrasena' => $contrasena,
                ]);
                if (!empty($reg['id_usuario'])) {
                    ActivityLogger::record((int) $reg['id_usuario'], 'Registro publico de cliente', 'AUTH', $request);
                    if ($isAjax) {
                        return response()->json([
                            'success' => true,
                            'message' => 'Registro completado exitosamente.',
                            'redirectUrl' => url('/login'),
                        ], 201);
                    }

                    return redirect('/login');
                }
                $error = 'No fue posible registrar la cuenta.';
            }

            if ($isAjax && $error) {
                return response()->json(['success' => false, 'message' => $error], 422);
            }
        }

        return response()->view('auth.registro', [
            'pageTitle' => 'Requesty - Registro',
            'error' => $error,
            'success' => null,
        ]);
    }

    public function logout(Request $request)
    {
        $sessionUser = $request->session()->get('usuario');
        if (is_array($sessionUser) && !empty($sessionUser['id'])) {
            ActivityLogger::record((int) $sessionUser['id'], 'Cierre de sesion', 'AUTH', $request);
        }

        $cookieName = $request->hasSession() ? config('session.cookie') : null;
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $response = redirect('/login');
        if ($cookieName) {
            $response->headers->clearCookie($cookieName, config('session.path', '/'), config('session.domain'));
        }

        return $response;
    }
}
