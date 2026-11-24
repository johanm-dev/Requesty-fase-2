<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\AuthException;
use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Services\ActivityLogger;
use App\Services\DtoValidator;
use App\Services\FileRateLimiter;
use App\Services\JwtService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthApiController extends Controller
{
    public function sesion(Request $request): JsonResponse
    {
        if (strtoupper($request->getMethod()) === 'POST') {
            FileRateLimiter::check($request, 'auth_login', 5, 60);
            $input = DtoValidator::parseJsonInput($request, ['correo', 'contrasena']);
            $email = DtoValidator::requireEmail($input, 'correo');
            $pass = DtoValidator::requireString($input, 'contrasena', 1, 128);

            $user = (new Usuario())->authenticate($email, $pass);
            if (!$user) {
                throw new AuthException('Credenciales invalidas o cuenta inactiva.');
            }

            $csrfToken = '';
            if ($request->hasSession()) {
                $request->session()->regenerate();
                $request->session()->regenerateToken();
                $request->session()->put('usuario', [
                    'id' => (int) $user['id_usuario'],
                    'nombre' => (string) $user['nombre'],
                    'correo' => (string) $user['correo'],
                    'rol' => strtoupper((string) $user['rol']),
                ]);
                $csrfToken = $request->session()->token();
            }

            ActivityLogger::record((int) $user['id_usuario'], 'Inicio de sesion API', 'AUTH', $request);

            return response()->json([
                'success' => true,
                'token' => JwtService::issue($user),
                'csrf_token' => $csrfToken,
                'usuario' => [
                    'id_usuario' => (int) $user['id_usuario'],
                    'nombre' => $user['nombre'],
                    'correo' => $user['correo'],
                    'rol' => strtoupper((string) $user['rol']),
                ],
            ]);
        }

        $user = $request->attributes->get('requesty_user');
        $rolKey = strtolower(trim((string) ($user['rol'] ?? '')));

        return response()->json([
            'autenticado' => true,
            'usuario' => $user,
            'permisos' => config('requesty.permisos_por_rol.' . $rolKey, []),
            'csrf_token' => $request->hasSession() ? $request->session()->token() : '',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = JwtService::extractBearerToken($request);
        if ($token !== null) {
            $claims = JwtService::verify($token);
            if ($claims !== null) {
                JwtService::revoke((string) $claims['jti'], (int) $claims['exp']);
            }
        }

        $sessionUser = $request->hasSession() ? $request->session()->get('usuario') : null;
        if (is_array($sessionUser) && !empty($sessionUser['id'])) {
            ActivityLogger::record((int) $sessionUser['id'], 'Cierre de sesion', 'AUTH', $request);
        }

        $cookieName = $request->hasSession() ? config('session.cookie') : null;
        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $response = response()->json([
            'success' => true,
            'message' => 'Sesion cerrada y token revocado.',
        ]);
        if ($cookieName) {
            $response->headers->clearCookie($cookieName, config('session.path', '/'), config('session.domain'));
        }

        return $response;
    }

    public function roles(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'roles' => (new Usuario())->getRoles(),
        ]);
    }
}
