<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMedicalEvaluationRequest;
use App\Http\Responses\ApiResponse;
use App\Models\MedicalEvaluation;
use App\Services\MedicalEvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * MedicalEvaluationController
 *
 * Responsabilidad: acciones sobre valoraciones existentes desde Vista 2.
 * La lectura fue movida a ClinicalRecordController (Vista 1 y Vista 2).
 *
 * Rutas en api.php:
 *   PUT    /medical-evaluations/{medicalEvaluation}            → update()
 *   PATCH  /medical-evaluations/{medicalEvaluation}/confirmar  → confirmar()
 *   PATCH  /medical-evaluations/{medicalEvaluation}/cancelar   → cancelar()
 *
 * Autorización por rol:
 *   REMITENTE → solo puede operar sobre sus propias evaluaciones (user_id === auth)
 *   ADMIN     → puede operar sobre cualquier evaluación
 */
class MedicalEvaluationController extends Controller
{
    public function __construct(
        private readonly MedicalEvaluationService $service
    ) {}

    /**
     * Editar datos clínicos de una valoración en estado EN_ESPERA.
     * REMITENTE: solo puede editar sus propias evaluaciones.
     */
    public function update(
        UpdateMedicalEvaluationRequest $request,
        MedicalEvaluation $medicalEvaluation
    ): JsonResponse {
        try {
            $user = auth()->user();

            if ($user->isRemitente() && $medicalEvaluation->user_id !== $user->id) {
                return ApiResponse::forbidden();
            }

            if ($medicalEvaluation->isConfirmado()) {
                return ApiResponse::error(
                    'No se pueden editar datos clínicos de una valoración confirmada. Debe cancelarla primero.',
                    403
                );
            }

            $evaluation = $this->service->update(
                $medicalEvaluation,
                $request->validated()
            );

            return ApiResponse::success([
                'message' => 'Valoración médica actualizada correctamente',
                'data'    => $evaluation->load(['patient', 'user', 'procedures.items']),
            ]);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al actualizar la valoración', debug: $e->getMessage());
        }
    }

    /**
     * Confirmar una valoración con firma del paciente.
     * REMITENTE: solo puede confirmar sus propias evaluaciones.
     * Es idempotente — confirmar una ya confirmada devuelve 200.
     */
    public function confirmar(Request $request, MedicalEvaluation $medicalEvaluation): JsonResponse
    {
        $user = auth()->user();

        if ($user->isRemitente() && $medicalEvaluation->user_id !== $user->id) {
            return ApiResponse::forbidden();
        }

        $request->validate([
            'terms_accepted'    => ['required', 'accepted'],
            'patient_signature' => ['required', 'string'],
        ]);

        try {
            if ($medicalEvaluation->isConfirmado()) {
                return ApiResponse::success([
                    'message' => 'La valoración ya está confirmada',
                    'data'    => $medicalEvaluation->load(['patient', 'user', 'confirmedBy']),
                ]);
            }

            $evaluation = $this->service->confirmar(
                $medicalEvaluation,
                $request->patient_signature,
                auth()->id()
            );

            return ApiResponse::success([
                'message' => 'Valoración confirmada correctamente',
                'data'    => $evaluation->load(['patient', 'user', 'confirmedBy']),
            ]);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al confirmar la valoración', debug: $e->getMessage());
        }
    }

    /**
     * Cancelar una valoración.
     * REMITENTE: solo puede cancelar sus propias evaluaciones.
     * Es idempotente — cancelar una ya cancelada devuelve 200.
     */
    public function cancelar(MedicalEvaluation $medicalEvaluation): JsonResponse
    {
        $user = auth()->user();

        if ($user->isRemitente() && $medicalEvaluation->user_id !== $user->id) {
            return ApiResponse::forbidden();
        }

        try {
            if ($medicalEvaluation->isCancelado()) {
                return ApiResponse::success([
                    'message' => 'La valoración ya está cancelada',
                    'data'    => $medicalEvaluation->load(['patient', 'user', 'canceledBy']),
                ]);
            }

            $evaluation = $this->service->cancelar($medicalEvaluation, auth()->id());

            return ApiResponse::success([
                'message' => 'Valoración cancelada correctamente',
                'data'    => $evaluation->load(['patient', 'user', 'canceledBy']),
            ]);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al cancelar la valoración', debug: $e->getMessage());
        }
    }
}