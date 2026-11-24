<?php

namespace App\Http\Middleware;

use App\Exceptions\AuthException;
use App\Exceptions\ForbiddenException;
use App\Services\FileRateLimiter;
use App\Services\JwtService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestyApiAuth
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $method = strtoupper($request->getMethod());
        $user = null;

        $token = JwtService::extractBearerToken($request);
        if ($token !== null) {
            $claims = JwtService::verify($token);
            if ($claims === null) {
                throw new AuthException('Token JWT invalido, expirado o revocado.');
            }
            $user = [
                'id' => (int) $claims['sub'],
                'nombre' => (string) $claims['nombre'],
                'correo' => (string) $claims['correo'],
                'rol' => strtoupper((string) $claims['rol']),
                'jti' => (string) $claims['jti'],
                'exp' => (int) $claims['exp'],
            ];
            if ($method === 'GET' || $method === 'HEAD') {
                FileRateLimiter::check($request, 'api_read:' . (int) $user['id'], 300, 60);
            }
        } elseif ($request->hasSession() && is_array($request->session()->get('usuario'))) {
            $user = $request->session()->get('usuario');
            if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                $submitted = $request->header('X-CSRF-Token')
                    ?? $request->input('_token')
                    ?? $request->input('_csrf');
                $expected = (string) $request->session()->token();
                if (!is_string($submitted) || $submitted === '' || $expected === '' || !hash_equals($expected, $submitted)) {
                    throw new ForbiddenException('Token CSRF invalido o ausente para sesion web.');
                }
            } elseif ($method === 'GET' || $method === 'HEAD') {
                FileRateLimiter::check($request, 'api_read:' . (int) ($user['id'] ?? 0), 300, 60);
            }
        } else {
            throw new AuthException('Autenticacion requerida (JWT Bearer o sesion activa).');
        }

        if (!empty($roles)) {
            $userRole = strtoupper(trim((string) ($user['rol'] ?? '')));
            $normalized = array_map(static fn (string $r) => strtoupper(trim($r)), $roles);
            if (!in_array($userRole, $normalized, true)) {
                throw new ForbiddenException('Acceso denegado: privilegios insuficientes.');
            }
        }

        $request->attributes->set('requesty_user', $user);

        return $next($request);
    }
}
