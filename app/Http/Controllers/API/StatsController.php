<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * StatsController
 *
 * Las rutas de admin ya están protegidas por el middleware 'admin' en api.php,
 * por lo que este controller no necesita re-verificar el rol para esos endpoints.
 *
 * Los endpoints de remitente viven bajo auth:sanctum sin middleware admin,
 * por eso sí se verifica que el usuario sea remitente antes de responder.
 */
class StatsController extends Controller
{
    public function __construct(private readonly StatsService $stats) {}

    // ─────────────────────────────────────────────
    // ADMIN — protegidos por middleware 'admin' en api.php
    // No hace falta re-chequear el rol acá
    // ─────────────────────────────────────────────

    public function summary(): JsonResponse
    {
        try {
            return ApiResponse::success($this->stats->getSummary());
        } catch (Throwable $e) {
            return ApiResponse::error('Error al obtener el resumen', debug: $e->getMessage());
        }
    }

    public function referrerStats(): JsonResponse
    {
        try {
            return ApiResponse::success($this->stats->getAllReferrerStats());
        } catch (Throwable $e) {
            return ApiResponse::error('Error al obtener stats de remitentes', debug: $e->getMessage());
        }
    }

    public function topByDemand(): JsonResponse
    {
        try {
            return ApiResponse::success($this->stats->getTopByDemand());
        } catch (Throwable $e) {
            return ApiResponse::error('Error al obtener top por demanda', debug: $e->getMessage());
        }
    }

    public function topByIncome(): JsonResponse
    {
        try {
            return ApiResponse::success($this->stats->getTopByIncome());
        } catch (Throwable $e) {
            return ApiResponse::error('Error al obtener top por ingresos', debug: $e->getMessage());
        }
    }

    public function conversionRate(): JsonResponse
    {
        try {
            return ApiResponse::success($this->stats->getConversionRate());
        } catch (Throwable $e) {
            return ApiResponse::error('Error al calcular tasa de conversión', debug: $e->getMessage());
        }
    }

    public function annualComparison(): JsonResponse
    {
        try {
            return ApiResponse::success($this->stats->getAnnualComparison());
        } catch (Throwable $e) {
            return ApiResponse::error('Error en comparativa anual', debug: $e->getMessage());
        }
    }

    public function monthComparison(): JsonResponse
    {
        try {
            return ApiResponse::success($this->stats->getMonthComparison());
        } catch (Throwable $e) {
            return ApiResponse::error('Error en comparativa mensual', debug: $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────
    // REMITENTE — bajo auth:sanctum sin middleware admin
    // Verificamos el rol acá porque la ruta no tiene middleware de rol
    // ─────────────────────────────────────────────

    public function referrerSummary(): JsonResponse
    {
        if (! Auth::user()->isRemitente()) {
            return ApiResponse::forbidden();
        }

        try {
            return ApiResponse::success(
                $this->stats->getReferrerSummary(Auth::user()->name)
            );
        } catch (Throwable $e) {
            return ApiResponse::error('Error al obtener tus estadísticas', debug: $e->getMessage());
        }
    }

    public function referrerAnnualComparison(): JsonResponse
    {
        if (! Auth::user()->isRemitente()) {
            return ApiResponse::forbidden();
        }

        try {
            return ApiResponse::success(
                $this->stats->getAnnualComparison(Auth::user()->name)
            );
        } catch (Throwable $e) {
            return ApiResponse::error('Error en comparativa anual', debug: $e->getMessage());
        }
    }

    public function referrerMonthComparison(): JsonResponse
    {
        if (! Auth::user()->isRemitente()) {
            return ApiResponse::forbidden();
        }

        try {
            return ApiResponse::success(
                $this->stats->getMonthComparison(Auth::user()->name)
            );
        } catch (Throwable $e) {
            return ApiResponse::error('Error en comparativa mensual', debug: $e->getMessage());
        }
    }
}