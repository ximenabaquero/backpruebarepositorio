<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * EnsureUserIsActive
 *
 * Verifica que el usuario autenticado tenga status 'active'.
 * Centraliza el chequeo que antes estaba copiado en cada método
 * de MedicalEvaluationController, PatientController y ProcedureController.
 *
 * Registro en bootstrap/app.php:
 *
 *   ->withMiddleware(function (Middleware $middleware) {
 *       $middleware->alias([
 *           'active'  => \App\Http\Middleware\EnsureUserIsActive::class,
 *           'admin'   => \App\Http\Middleware\AdminMiddleware::class, // el que ya tenés
 *       ]);
 *   })
 *
 * Uso en api.php — aplicarlo a las rutas que lo necesiten:
 *
 *   Route::middleware(['auth:sanctum', 'active'])->group(function () {
 *       // pacientes, valoraciones, procedimientos
 *   });
 */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->status !== User::STATUS_ACTIVE) {
            return ApiResponse::error(
                'Tu cuenta no está activa. Contactá al administrador.',
                403
            );
        }

        return $next($request);
    }
}