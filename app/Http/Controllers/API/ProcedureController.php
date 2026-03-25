<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProcedureRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Procedure;
use App\Services\ProcedureService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * ProcedureController
 *
 * Responsabilidad: editar procedimientos desde Vista 2.
 * La lectura fue eliminada — los procedimientos siempre se
 * consumen dentro del registro clínico completo (Vista 2).
 *
 * Rutas en api.php:
 *   PUT /procedures/{procedure} → update()
 *
 * Autorización por rol:
 *   REMITENTE → solo puede editar procedimientos de sus propias evaluaciones
 *   ADMIN     → puede editar cualquier procedimiento
 */
class ProcedureController extends Controller
{
    public function __construct(
        private readonly ProcedureService $service
    ) {}

    /**
     * Actualizar un procedimiento y/o sus items.
     * Si se envían items, reemplaza todos los existentes y recalcula total_amount.
     * REMITENTE: solo puede editar procedimientos de sus propias evaluaciones.
     */
    public function update(UpdateProcedureRequest $request, Procedure $procedure): JsonResponse
    {
        try {
            $user = auth()->user();

            // Cargar solo los campos necesarios para la verificación
            $procedure->load('medicalEvaluation:id,user_id');

            if ($user->isRemitente() && $procedure->medicalEvaluation->user_id !== $user->id) {
                return ApiResponse::forbidden();
            }

            $procedure = $this->service->update($procedure, $request->validated());

            $procedure->load([
                'items:id,procedure_id,item_name,price',
                'medicalEvaluation:id,user_id,patient_id',
            ]);

            return ApiResponse::success([
                'message' => 'Procedimiento actualizado correctamente',
                'data'    => $procedure,
            ]);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al actualizar el procedimiento', debug: $e->getMessage());
        }
    }
}