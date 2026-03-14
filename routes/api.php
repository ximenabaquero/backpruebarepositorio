<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
// TODO: Descomentar cuando se retome el módulo de citas
// use App\Http\Controllers\Auth\GoogleCalendarAuthController;
// use App\Http\Controllers\API\AppointmentController;
use App\Http\Controllers\API\ClinicalImageController;
use App\Http\Controllers\API\PatientController;
use App\Http\Controllers\API\ProcedureController;
use App\Http\Controllers\API\MedicalEvaluationController;
use App\Http\Controllers\API\StatsController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\InventoryController;
use Illuminate\Http\Request;


/*|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

    Route::prefix('v1')->group(function () {

        // ======================
        // Rutas públicas
        // ======================
        Route::get('/test', function () {
            return response()->json([
                'message' => 'API Cold Esthetic funcionando',
                'version' => 'v1'
            ]);
        });

        Route::get('/clinical-images', [ClinicalImageController::class, 'index']);

        // ======================
        // Auth
        // ======================
        Route::post('/login', [AuthController::class, 'login']);

        // ======================
        // Rutas protegidas
        // ======================
        Route::middleware(['auth:sanctum', 'admin'])->group(function () {

            // Admin
            Route::get('/remitentes', [UserController::class, 'listRemitentes']);
            Route::post('/remitentes', [UserController::class, 'createRemitente']);
            Route::put('/admin/{id}', [UserController::class, 'updateAdmin']);
            Route::put('/remitentes/{id}', [UserController::class, 'updateRemitente']);
            Route::patch('/remitentes/{id}/activar', [UserController::class, 'activarRemitente']);
            Route::patch('/remitentes/{id}/inactivar', [UserController::class, 'inactivarRemitente']);
            Route::patch('/remitentes/{id}/despedir', [UserController::class, 'despedirRemitente']);

            // Estadisticas (solo ADMIN)
            Route::prefix('stats')->group(function () {
                Route::get('/summary', [StatsController::class, 'summary']);
                Route::get('/referrer-stats', [StatsController::class, 'referrerStats']);
                Route::get('/procedures/top-demand', [StatsController::class, 'topByDemand']);
                Route::get('/procedures/top-income', [StatsController::class, 'topByIncome']);
                Route::get('/conversion-rate', [StatsController::class, 'conversionRate']);
                Route::get('/annual-comparison', [StatsController::class, 'annualComparison']);
                Route::get('/month-comparison', [StatsController::class, 'monthComparison']);

                Route::get('/income-by-procedure', [StatsController::class, 'incomeByProcedureType']);
                Route::get('/patients-monthly', [StatsController::class, 'patientsMonthly']); 
                Route::get('/income-monthly', [StatsController::class, 'incomeMonthly']);
                Route::get('/income-weekly', [StatsController::class, 'incomeWeekly']);
            });
        });

        Route::middleware('auth:sanctum')->group(function () {

            Route::get('/me', [AuthController::class, 'me']);

            // ── Inventario ────────────────────────────────────────────────
            Route::prefix('inventory')->group(function () {

                // Categorías y productos (solo ADMIN gestiona, todos leen)
                Route::middleware('admin')->group(function () {
                    Route::post('/categories',        [InventoryController::class, 'categoriesStore']);
                    Route::put('/categories/{id}',    [InventoryController::class, 'categoriesUpdate']);
                    Route::delete('/categories/{id}', [InventoryController::class, 'categoriesDestroy']);

                    Route::post('/products',        [InventoryController::class, 'productsStore']);
                    Route::put('/products/{id}',    [InventoryController::class, 'productsUpdate']);
                    Route::delete('/products/{id}', [InventoryController::class, 'productsDestroy']);
                });

                // Lectura libre para ambos roles
                Route::get('/categories',    [InventoryController::class, 'categoriesIndex']);
                Route::get('/products',      [InventoryController::class, 'productsIndex']);

                // Compras (ambos roles crean/ven; REMITENTE ve solo las suyas)
                Route::get('/purchases',        [InventoryController::class, 'purchasesIndex']);
                Route::post('/purchases',       [InventoryController::class, 'purchasesStore']);
                Route::put('/purchases/{id}',   [InventoryController::class, 'purchasesUpdate']);
                Route::delete('/purchases/{id}',[InventoryController::class, 'purchasesDestroy']);

                // Consumos (ambos roles; REMITENTE ve solo los suyos)
                Route::get('/usages',        [InventoryController::class, 'usagesIndex']);
                Route::post('/usages',       [InventoryController::class, 'usagesStore']);
                Route::delete('/usages/{id}',[InventoryController::class, 'usagesDestroy']);

                // Resumen gastos vs ingresos
                Route::get('/summary', [InventoryController::class, 'summary']);
            });
            Route::post('/logout', [AuthController::class, 'logout']);

            // Imágenes clínicas
            Route::post('/clinical-images', [ClinicalImageController::class, 'store']);
            Route::put('/clinical-images/{id}', [ClinicalImageController::class, 'update']);
            Route::delete('/clinical-images/{id}', [ClinicalImageController::class, 'destroy']);

            // Pacientes
            Route::get('/patients', [PatientController::class, 'index']);
            Route::get('/patients/{patient}', [PatientController::class, 'show']);
            Route::post('/patients', [PatientController::class, 'store']);
            Route::put('/patients/{patient}', [PatientController::class, 'update']);

            // Valoraciones
            Route::get('/medical-evaluation/patient/{patient}',[MedicalEvaluationController::class, 'showByPatient']);
            Route::get('/medical-evaluations/{id}', [MedicalEvaluationController::class, 'showById']);
            Route::post('/medical-evaluations', [MedicalEvaluationController::class, 'store']);
            Route::put('/medical-evaluations/{medicalEvaluation}', [MedicalEvaluationController::class, 'update']);
            //estados de valoración (EN_ESPERA, CONFIRMADO, CANCELADO)
            Route::patch('/medical-evaluations/{medicalEvaluation}/confirmar', [MedicalEvaluationController::class, 'confirmar']);
            Route::patch('/medical-evaluations/{medicalEvaluation}/cancelar', [MedicalEvaluationController::class, 'cancelar']);

            // Procedimientos
            Route::get('/procedures', [ProcedureController::class, 'index']);
            Route::get('/procedures/{procedure}', [ProcedureController::class, 'show']);
            Route::post('/procedures', [ProcedureController::class, 'store']);
            Route::put('/procedures/{procedure}', [ProcedureController::class, 'update']);

            // TODO: Descomentar cuando se retome el módulo de citas y Google Calendar
            // // Google Calendar Authentication
            // Route::prefix('google')->group(function () {
            //     Route::get('/auth', [GoogleCalendarAuthController::class, 'redirectToGoogle']);
            //     Route::get('/callback', [GoogleCalendarAuthController::class, 'handleCallback']);
            //     Route::get('/status', [GoogleCalendarAuthController::class, 'getStatus']);
            //     Route::post('/disconnect', [GoogleCalendarAuthController::class, 'disconnect']);
            // });

            // // Appointments (Citas)
            // Route::prefix('appointments')->group(function () {
            //     Route::get('/', [AppointmentController::class, 'index']);
            //     Route::get('/upcoming', [AppointmentController::class, 'upcoming']);
            //     Route::get('/{appointment}', [AppointmentController::class, 'show']);
            //     Route::post('/', [AppointmentController::class, 'store']);
            //     Route::put('/{appointment}', [AppointmentController::class, 'update']);
            //     Route::delete('/{appointment}', [AppointmentController::class, 'destroy']);
            //     Route::post('/{appointment}/complete', [AppointmentController::class, 'completeAppointment']);
            // });

        });
});

