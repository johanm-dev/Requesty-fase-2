<?php

use App\Exceptions\PgExceptionMapper;
use App\Http\Middleware\RequestyApiAuth;
use App\Http\Middleware\RequestyWebAuth;
use App\Http\Middleware\SecurityHeadersMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(SecurityHeadersMiddleware::class);
        $middleware->appendToGroup('api', [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            StartSession::class,
        ]);
        $middleware->alias([
            'web.auth' => RequestyWebAuth::class,
            'api.auth' => RequestyApiAuth::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'code' => 'NOT_FOUND',
                    'message' => 'Recurso no encontrado.',
                ], 404);
            }

            return response()->view('errors.404', [
                'pageTitle' => 'Pagina no encontrada - Requesty',
            ], 404);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'code' => 'VALIDATION_ERROR',
                    'message' => $e->validator->errors()->first() ?: 'Datos invalidos.',
                ], 422);
            }

            return null;
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($e instanceof HttpExceptionInterface) {
                if ($e->getStatusCode() === 419) {
                    if ($request->is('api/*') || $request->expectsJson()) {
                        return response()->json([
                            'success' => false,
                            'code' => 'FORBIDDEN',
                            'message' => 'Token CSRF invalido o ausente.',
                        ], 403);
                    }

                    return response('<h1>Error 403</h1><p>Token CSRF invalido o ausente.</p>', 403);
                }

                return null;
            }

            [$status, $code, $publicMessage] = PgExceptionMapper::map($e);
            error_log(PgExceptionMapper::sanitizeForLog(sprintf(
                '[Requesty][HTTP %d][%s] %s',
                $status,
                get_class($e),
                $e->getMessage()
            )));

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'code' => $code,
                    'message' => $publicMessage,
                ], $status);
            }

            return response(
                "<h1>Error {$status}</h1><p>" . htmlspecialchars($publicMessage, ENT_QUOTES, 'UTF-8') . '</p>',
                $status
            );
        });
    })->create();
