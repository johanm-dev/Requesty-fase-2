<?php

namespace App\Http\Middleware;

use App\Exceptions\ForbiddenException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequestyWebAuth
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->session()->get('usuario');
        if (!is_array($user) || empty($user['id'])) {
            return redirect('/login');
        }

        if (!empty($roles)) {
            $userRole = strtoupper(trim((string) ($user['rol'] ?? '')));
            $normalized = array_map(static fn (string $r) => strtoupper(trim($r)), $roles);
            if (!in_array($userRole, $normalized, true)) {
                throw new ForbiddenException('Tu rol no tiene autorizacion para este modulo.');
            }
        }

        $request->attributes->set('requesty_user', $user);

        return $next($request);
    }
}
