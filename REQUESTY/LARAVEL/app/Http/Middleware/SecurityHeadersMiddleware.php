<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = (string) $request->headers->get('Origin', '');
        $allowed = (array) config('requesty.cors_allowed_origins', []);
        $corsAllowed = $origin !== '' && in_array($origin, $allowed, true);

        if ($origin !== '' && !$corsAllowed && strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->withSecurityHeaders($request, response()->json([
                'success' => false,
                'message' => 'Origen CORS no permitido',
            ], 403), null);
        }

        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return $this->withSecurityHeaders($request, response('', 204), $corsAllowed ? $origin : null);
        }

        $response = $next($request);

        return $this->withSecurityHeaders($request, $response, $corsAllowed ? $origin : null);
    }

    private function withSecurityHeaders(Request $request, Response $response, ?string $allowedOrigin): Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');

        $isHttps = $request->isSecure()
            || strtolower((string) $request->headers->get('X-Forwarded-Proto', '')) === 'https';
        if ($isHttps) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; script-src 'self' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; font-src 'self' https://cdnjs.cloudflare.com; img-src 'self' data: https:; frame-ancestors 'none'; base-uri 'self'"
        );
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');

        if ($allowedOrigin !== null) {
            $response->headers->set('Access-Control-Allow-Origin', $allowedOrigin);
            $response->headers->set('Vary', 'Origin');
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Requested-With, X-CSRF-Token');
        }

        return $response;
    }
}
