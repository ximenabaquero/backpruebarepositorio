<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\EnsureUserIsActive;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        // Trust all proxies — necesario en Railway/Heroku/Render donde
        // la IP del proxy cambia con cada deploy y no puede especificarse.
        // Railway filtra X-Forwarded-For maliciosos antes de llegar acá.
        $middleware->trustProxies(at: '*');

        // Sanctum (cookies + SPA)
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);

        // Alias de middleware
        $middleware->alias([
            'auth'   => Authenticate::class,
            'admin'  => AdminMiddleware::class,
            'active' => EnsureUserIsActive::class,
        ]);

        // ── Rate limiting ─────────────────────────────────────────────────

        RateLimiter::for('login', function (Request $request) {
            // Doble límite: por IP y por email+IP
            // Previene fuerza bruta tanto de contraseña como de enumeración
            // de cuentas desde una misma IP
            return [
                Limit::perMinute(5)->by($request->ip()),
                Limit::perMinute(5)->by(
                    $request->input('email', '') . '|' . $request->ip()
                ),
            ];
        });

        RateLimiter::for('api', function (Request $request) {
            // 120 requests/minuto por usuario autenticado
            // Fallback a IP para rutas públicas sin auth
            return Limit::perMinute(120)->by(
                $request->user()?->id ?? $request->ip()
            );
        });

        // Aplicar throttle:api globalmente a todas las rutas del grupo api
        // Evita tener que recordar aplicarlo ruta por ruta
        $middleware->api(append: [
            'throttle:api',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Respuesta 401 consistente con ApiResponse usado en todo el sistema
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'data'    => null,
                    'error'   => 'No autenticado.',
                    'message' => 'error',
                ], 401);
            }
        });
    })
    ->create();