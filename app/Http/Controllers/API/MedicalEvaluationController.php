<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMedicalEvaluationRequest;
use App\Http\Requests\UpdateMedicalEvaluationRequest;
use App\Http\Responses\ApiResponse;
use App\Models\MedicalEvaluation;
use App\Models\Patient;
use App\Services\MedicalEvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * MedicalEvaluationController
 *
 * Middlewares aplicados en api.php:
 *   - auth:sanctum  → usuario autenticado
 *   - active        → cuenta activa (EnsureUserIsActive)
 */
class MedicalEvaluationController extends Controller
{
    public function __construct(
        private readonly MedicalEvaluationService $service
    ) {}

    // ─────────────────────────────────────────────
    // LECTURA
    // ─────────────────────────────────────────────

    /**
     * Listado de valoraciones de un paciente.
     *
     * Solo manda lo que PatientRecordsList consume:
     *   id, status, referrer_name
     *   procedures[0].procedure_date
     *
     * Campos pesados (medical_background, patient_signature,
     * datos clínicos, auditoría) se reservan para showById.
     */
    public function showByPatient(int $patientId): JsonResponse
    {
        try {
            $user = auth()->user();

            if ($user->isRemitente()) {
                $patient = Patient::findOrFail($patientId);
                if ($patient->user_id !== $user->id) {
                    return ApiResponse::forbidden();
                }
            }

            $evaluations = MedicalEvaluation::with([
                // Solo procedure_date — el listado muestra la fecha del primer procedimiento
                'procedures:id,medical_evaluation_id,procedure_date',
            ])
            ->select([
                'id',
                'patient_id',   // requerido para que el eager load funcione correctamente
                'status',
                'referrer_name',
            ])
            ->where('patient_id', $patientId)
            ->orderByDesc(
                \App\Models\Procedure::select('procedure_date')
                    ->whereColumn('medical_evaluation_id', 'medical_evaluations.id')
                    ->latest('procedure_date')
                    ->limit(1)
            )
            ->get();

            if ($evaluations->isEmpty()) {
                return ApiResponse::error('Este paciente no tiene valoraciones médicas', 404);
            }

            return ApiResponse::success($evaluations);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al obtener las valoraciones', debug: $e->getMessage());
        }
    }

    /**
     * Detalle completo de una valoración.
     *
     * Carga TODO lo que PatientRecordDetail consume:
     *   - Datos clínicos completos (weight, height, bmi, medical_background)
     *   - Procedimientos con items y precios
     *   - patient_signature y auditoría de confirmación/cancelación
     *   - patient_age_at_evaluation (snapshot de edad en la valoración)
     *   - brand_name del remitente para el encabezado del PDF
     */
    public function showById(int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            $evaluation = MedicalEvaluation::with([
                // Todos los campos del paciente para la ficha clínica y factura
                'patient:id,user_id,first_name,last_name,cedula,cellphone,date_of_birth,biological_sex',
                // Procedimientos completos con items
                'procedures:id,medical_evaluation_id,procedure_date,total_amount,notes',
                'procedures.items:id,procedure_id,item_name,price',
                // brand_name lo usa el header del PDF
                'user:id,name,first_name,last_name,brand_name',
                // Auditoría visible en la firma
                'confirmedBy:id,first_name,last_name',
                'canceledBy:id,first_name,last_name',
            ])
            ->findOrFail($id);

            if ($user->isRemitente() && $evaluation->patient->user_id !== $user->id) {
                return ApiResponse::forbidden();
            }

            return ApiResponse::success($evaluation);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al obtener la valoración', debug: $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────
    // ESCRITURA
    // ─────────────────────────────────────────────

    public function store(StoreMedicalEvaluationRequest $request): JsonResponse
    {
        try {
            $evaluation = $this->service->create(
                $request->validated(),
                auth()->user()
            );

            return ApiResponse::success([
                'message' => 'Valoración médica creada correctamente',
                'data'    => $evaluation->load(['patient', 'user']),
            ], 201);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al crear la valoración', debug: $e->getMessage());
        }
    }

    public function update(
        UpdateMedicalEvaluationRequest $request,
        MedicalEvaluation $medicalEvaluation
    ): JsonResponse {
        try {
            $user = auth()->user();

            if ($user->isRemitente() && $medicalEvaluation->patient->user_id !== $user->id) {
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

    // ─────────────────────────────────────────────
    // CAMBIOS DE ESTADO
    // ─────────────────────────────────────────────

    public function confirmar(Request $request, MedicalEvaluation $medicalEvaluation): JsonResponse
    {
        $user = auth()->user();

        if ($user->isRemitente() && $medicalEvaluation->patient->user_id !== $user->id) {
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

    public function cancelar(MedicalEvaluation $medicalEvaluation): JsonResponse
    {
        $user = auth()->user();

        if ($user->isRemitente() && $medicalEvaluation->patient->user_id !== $user->id) {
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