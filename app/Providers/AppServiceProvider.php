<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

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
    }
}
