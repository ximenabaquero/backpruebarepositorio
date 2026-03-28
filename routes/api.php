<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\API\ClinicalImageController;
use App\Http\Controllers\API\ClinicalRecordController;
use App\Http\Controllers\API\InventoryController;
use App\Http\Controllers\API\MedicalEvaluationController;
use App\Http\Controllers\API\PatientController;
use App\Http\Controllers\API\ProcedureController;
use App\Http\Controllers\API\StatsController;
use App\Http\Controllers\API\UserController;

// TODO: Descomentar cuando se retome el módulo de citas
// use App\Http\Controllers\Auth\GoogleCalendarAuthController;
// use App\Http\Controllers\API\AppointmentController;

/*
|--------------------------------------------------------------------------
| API Routes — Cold Esthetic v1
|--------------------------------------------------------------------------
|
| Estructura de acceso:
|   Público          → sin auth
|   auth:sanctum     → admin + remitente activo
|   admin            → solo admin (anidado dentro de auth:sanctum)
|
*/

Route::prefix('v1')->group(function () {

    // =========================================================================
    // Público — sin autenticación
    // =========================================================================

    Route::get('/test', fn() => response()->json([
        'message' => 'API Cold Esthetic funcionando',
        'version' => 'v1',
    ]));

    // Carrusel público de la landing page
    Route::get('/clinical-images', [ClinicalImageController::class, 'index']);

    // Login con rate limiting doble (por IP y por email+IP)
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    // =========================================================================
    // Autenticado — admin + remitente activo
    // =========================================================================

    Route::middleware(['auth:sanctum', 'active'])->group(function () {

        // ── Auth ──────────────────────────────────────────────────────────────
        Route::get('/me',      [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // ── Pacientes ─────────────────────────────────────────────────────────
        // show() eliminado — Vista 1 devuelve paciente + evaluaciones juntos
        Route::apiResource('patients', PatientController::class)
            ->only(['index', 'store', 'update']);

        // ── Registros clínicos ────────────────────────────────────────────────
        //
        //   Vista 1 — Perfil del paciente + tarjetas de evaluaciones
        //   Vista 2 — Registro clínico completo (evaluación + procedimientos)
        //   Flujo 1 — Crear registro para paciente nuevo
        //   Flujo 2 — Crear registro para paciente existente
        //
        Route::post('/clinical-records',
            [ClinicalRecordController::class, 'store']);                        // Flujo 1

        Route::prefix('patients/{patient}/clinical-records')->group(function () {
            Route::get('/',             [ClinicalRecordController::class, 'patientProfile']);  // Vista 1
            Route::get('/{evaluation}', [ClinicalRecordController::class, 'show']);            // Vista 2
            Route::post('/',            [ClinicalRecordController::class, 'storeForPatient']); // Flujo 2
        });

        // ── Acciones sobre valoraciones (desde Vista 2) ───────────────────────
        //
        //   Lectura eliminada (showByPatient, showById) — reemplazada por Vista 2.
        //   Solo se exponen las acciones que el frontend dispara puntualmente.
        //
        Route::prefix('medical-evaluations/{medicalEvaluation}')->group(function () {
            Route::put('/',            [MedicalEvaluationController::class, 'update']);
            Route::patch('/confirmar', [MedicalEvaluationController::class, 'confirmar']);
            Route::patch('/cancelar',  [MedicalEvaluationController::class, 'cancelar']);
        });

        // ── Acciones sobre procedimientos (desde Vista 2) ─────────────────────
        //
        //   Lectura eliminada (index, show) — los procedimientos siempre se
        //   consumen dentro de Vista 2. Solo se mantiene update.
        //
        Route::put('/procedures/{procedure}', [ProcedureController::class, 'update']);

        // ── Inventario ────────────────────────────────────────────────────────
        Route::prefix('inventory')->group(function () {

            // Lectura libre para ambos roles
            Route::get('/categories', [InventoryController::class, 'categoriesIndex']);
            Route::get('/products',   [InventoryController::class, 'productsIndex']);
            Route::get('/summary',    [InventoryController::class, 'summary']);

            // Compras — ambos roles (filtrado por rol en el controller)
            Route::get('/purchases',         [InventoryController::class, 'purchasesIndex']);
            Route::post('/purchases',        [InventoryController::class, 'purchasesStore']);
            Route::put('/purchases/{id}',    [InventoryController::class, 'purchasesUpdate']);
            Route::delete('/purchases/{id}', [InventoryController::class, 'purchasesDestroy']);

            // Consumos — ambos roles (filtrado por rol en el controller)
            Route::get('/usages',         [InventoryController::class, 'usagesIndex']);
            Route::post('/usages',        [InventoryController::class, 'usagesStore']);
            Route::delete('/usages/{id}', [InventoryController::class, 'usagesDestroy']);

            // Solo ADMIN gestiona categorías y productos
            Route::middleware('admin')->group(function () {
                Route::post('/categories',        [InventoryController::class, 'categoriesStore']);
                Route::put('/categories/{id}',    [InventoryController::class, 'categoriesUpdate']);
                Route::delete('/categories/{id}', [InventoryController::class, 'categoriesDestroy']);

                Route::post('/products',          [InventoryController::class, 'productsStore']);
                Route::put('/products/{id}',      [InventoryController::class, 'productsUpdate']);
                Route::delete('/products/{id}',   [InventoryController::class, 'productsDestroy']);
            });
        });

        // ── Estadísticas propias del remitente ────────────────────────────────
        Route::prefix('stats/me')->group(function () {
            Route::get('/summary',           [StatsController::class, 'referrerSummary']);
            Route::get('/annual-comparison', [StatsController::class, 'referrerAnnualComparison']);
            Route::get('/month-comparison',  [StatsController::class, 'referrerMonthComparison']);
        });

        // ── Solo ADMIN ────────────────────────────────────────────────────────
        Route::middleware('admin')->group(function () {

            // Gestión de usuarios
            Route::get('/remitentes',                  [UserController::class, 'listRemitentes']);
            Route::post('/remitentes',                 [UserController::class, 'createRemitente']);
            Route::put('/admin/{id}',                  [UserController::class, 'updateAdmin']);
            Route::put('/remitentes/{id}',             [UserController::class, 'updateRemitente']);
            Route::patch('/remitentes/{id}/activar',   [UserController::class, 'activarRemitente']);
            Route::patch('/remitentes/{id}/inactivar', [UserController::class, 'inactivarRemitente']);
            Route::patch('/remitentes/{id}/despedir',  [UserController::class, 'despedirRemitente']);

            // Imágenes clínicas del carrusel — máximo 10
            Route::post('/clinical-images',        [ClinicalImageController::class, 'store']);
            Route::put('/clinical-images/{id}',    [ClinicalImageController::class, 'update']);
            Route::delete('/clinical-images/{id}', [ClinicalImageController::class, 'destroy']);

            // Estadísticas globales
            Route::prefix('stats')->group(function () {
                Route::get('/summary',               [StatsController::class, 'summary']);
                Route::get('/referrer-stats',        [StatsController::class, 'referrerStats']);
                Route::get('/procedures/top-demand', [StatsController::class, 'topByDemand']);
                Route::get('/procedures/top-income', [StatsController::class, 'topByIncome']);
                Route::get('/conversion-rate',       [StatsController::class, 'conversionRate']);
                Route::get('/annual-comparison',     [StatsController::class, 'annualComparison']);
                Route::get('/month-comparison',      [StatsController::class, 'monthComparison']);
            });
        });
    });
});
