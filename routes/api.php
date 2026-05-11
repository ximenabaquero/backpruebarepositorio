<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\API\ClinicalImageController;
use App\Http\Controllers\API\ClinicalRecordController;
use App\Http\Controllers\API\InventoryController;
use App\Http\Controllers\API\DistributorController;
use App\Http\Controllers\API\ExamOrderController;
use App\Http\Controllers\API\MedicalEvaluationController;
use App\Http\Controllers\API\PatientController;
use App\Http\Controllers\API\PatientPhotoController;
use App\Http\Controllers\API\ProcedureController;
use App\Http\Controllers\API\StatsController;
use App\Http\Controllers\API\UserController;

use App\Http\Controllers\API\AppointmentController;

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
        Route::get('/me',                    [AuthController::class, 'me']);
        Route::post('/logout',               [AuthController::class, 'logout']);
        Route::post('/confirm-password',     [AuthController::class, 'confirmPassword']);

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
        // ── Acciones sobre valoraciones ───────────────────────────────────────
        Route::prefix('medical-evaluations/{medicalEvaluation}')->group(function () {
            Route::put('/',            [MedicalEvaluationController::class, 'update']);
            Route::patch('/confirmar', [MedicalEvaluationController::class, 'confirmar']);
            Route::patch('/cancelar',  [MedicalEvaluationController::class, 'cancelar']);
            // Orden de exámenes
            Route::get('/exam-order',  [ExamOrderController::class, 'show']);
            Route::post('/exam-order', [ExamOrderController::class, 'store']);
            // Fotos del registro
            Route::get('/photos',           [PatientPhotoController::class, 'index']);
            Route::post('/photos',          [PatientPhotoController::class, 'store']);
            Route::delete('/photos/{photo}', [PatientPhotoController::class, 'destroy']);
            // Cita agendada
            Route::get('/appointment',  [AppointmentController::class, 'show']);
            Route::post('/appointment', [AppointmentController::class, 'store']);
        });

        // ── Acciones sobre órdenes de exámenes ───────────────────────────────
        Route::patch('/exam-orders/{examOrder}',         [ExamOrderController::class, 'update']);
        Route::post('/exam-orders/{examOrder}/result',   [ExamOrderController::class, 'uploadResult']);

        // ── Citas (agendamiento) ──────────────────────────────────────────────
        Route::patch('/appointments/{appointment}',  [AppointmentController::class, 'update']);
        Route::delete('/appointments/{appointment}', [AppointmentController::class, 'cancel']);

        // ── Acciones sobre procedimientos (desde Vista 2) ─────────────────────
        //
        //   Lectura eliminada (index, show) — los procedimientos siempre se
        //   consumen dentro de Vista 2. Solo se mantiene update.
        //
        Route::put('/procedures/{procedure}', [ProcedureController::class, 'update']);

        // ── Inventario ────────────────────────────────────────────────────────
        Route::prefix('inventory')->group(function () {

            // Lectura libre — ambos roles
            Route::get('/categories',             [InventoryController::class, 'categoriesIndex']);
            Route::get('/products',               [InventoryController::class, 'productsIndex']);
            Route::get('/products/notifications', [InventoryController::class, 'productsNotifications']); 
            // Ver distribuidores
            Route::get('/distributors', [DistributorController::class, 'index']);

            // Compras — ambos roles
            Route::get('/purchases',                       [InventoryController::class, 'purchasesIndex']);
            Route::post('/purchases',                      [InventoryController::class, 'purchasesStore']);
            Route::get('/purchases/last/{productId}',      [InventoryController::class, 'lastPurchase']);

            // Consumos — ambos roles
            Route::get('/usages',  [InventoryController::class, 'usagesIndex']);
            Route::post('/usages', [InventoryController::class, 'usagesStore']);

            // Solo admin
            Route::middleware('admin')->group(function () {

                // Reportes — solo ADMIN
                Route::prefix('reports')->group(function () {
                    Route::get('/spend-by-category',         [InventoryController::class, 'spendByCategory']);
                    Route::get('/spend-by-distributor',      [InventoryController::class, 'spendByDistributor']);
                    Route::get('/price-history/{productId}', [InventoryController::class, 'priceHistory']);
                });
                Route::post('/distributors',        [DistributorController::class, 'store']);
                Route::put('/distributors/{id}',    [DistributorController::class, 'update']);
                // Route::delete('/distributors/{id}', [DistributorController::class, 'destroy']);

                Route::post('/categories',     [InventoryController::class, 'categoriesStore']);
                Route::put('/categories/{id}', [InventoryController::class, 'categoriesUpdate']);

                // Route::post('/products', [InventoryController::class, 'productsStore']);

                // Summary financiero — ingresos, gastos y utilidad
                Route::get('/summary', [InventoryController::class, 'summary']);
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
                Route::get('/revenue-forecast',      [StatsController::class, 'revenueForecast']);
                Route::get('/revenue-trend',         [StatsController::class, 'revenueTrend']);
                Route::get('/patients-monthly',      [StatsController::class, 'patientsMonthly']);
                Route::get('/income-by-procedure',   [StatsController::class, 'incomeByProcedure']);
                Route::get('/income-monthly',        [StatsController::class, 'incomeMonthly']);
                Route::get('/income-weekly',         [StatsController::class, 'incomeWeekly']);
            });
        });
    });
});
