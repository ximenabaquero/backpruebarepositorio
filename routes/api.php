<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes — OLGA Healthtech v1
|--------------------------------------------------------------------------
| Solo autenticación. El frontend usa mock data para el demo.
*/

Route::prefix('v1')->group(function () {

    // Health check
    Route::get('/test', fn() => response()->json(['status' => 'ok', 'app' => 'OLGA Healthtech']));

    // Login con rate limiting
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:login');

    // Rutas autenticadas
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::get('/me',                [AuthController::class, 'me']);
        Route::post('/logout',           [AuthController::class, 'logout']);
        Route::post('/confirm-password', [AuthController::class, 'confirmPassword']);
    });
});
