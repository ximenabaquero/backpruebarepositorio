<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProcedureRequest;
use App\Http\Requests\UpdateProcedureRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Procedure;
use App\Services\ProcedureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * ProcedureController
 *
 * Middlewares aplicados en api.php:
 *   - auth:sanctum  → usuario autenticado
 *   - active        → cuenta activa (EnsureUserIsActive)
 */
class ProcedureController extends Controller
{
    public function __construct(
        private readonly ProcedureService $service
    ) {}

    // ─────────────────────────────────────────────
    // LECTURA
    // ─────────────────────────────────────────────

    /**
     * Listado de procedimientos.
     * REMITENTE ve solo los de sus propias evaluaciones.
     * ADMIN ve todos.
     */
    public function index(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $query = Procedure::with([
            'items:id,procedure_id,item_name,price',
            'medicalEvaluation.patient:id,user_id,first_name,last_name,cedula',
        ]);

        if ($user->isRemitente()) {
            $query->whereHas('medicalEvaluation.patient', fn($q) =>
                $q->where('user_id', $user->id)
            );
        }

        if ($request->filled('medical_evaluation_id')) {
            $query->where('medical_evaluation_id', (int) $request->query('medical_evaluation_id'));
        }

        return ApiResponse::success(
            $query->orderByDesc('procedure_date')->get()
        );
    }

    /**
     * Detalle de un procedimiento.
     */
    public function show(Procedure $procedure): JsonResponse
    {
        try {
            $user = auth()->user();

            $procedure->load([
                'items:id,procedure_id,item_name,price',
                'medicalEvaluation.patient:id,user_id,first_name,last_name,cedula',
            ]);

            if ($user->isRemitente() && $procedure->medicalEvaluation->patient->user_id !== $user->id) {
                return ApiResponse::forbidden();
            }

            return ApiResponse::success($procedure);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al obtener el procedimiento', debug: $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────
    // ESCRITURA
    // ─────────────────────────────────────────────

    /**
     * Crear un procedimiento con sus items.
     */
    public function store(StoreProcedureRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $user = auth()->user();

            // REMITENTE solo puede crear procedimientos en evaluaciones propias
            if ($user->isRemitente()) {
                $evalPatientUserId = \App\Models\MedicalEvaluation::findOrFail($data['medical_evaluation_id'])
                    ->patient
                    ->user_id;

                if ($evalPatientUserId !== $user->id) {
                    return ApiResponse::forbidden();
                }
            }

            $procedure = $this->service->create($data);

            $procedure->load([
                'items:id,procedure_id,item_name,price',
                'medicalEvaluation.patient:id,user_id,first_name,last_name,cedula',
            ]);

            return ApiResponse::success([
                'message' => 'Procedimiento creado correctamente',
                'data'    => $procedure,
            ], 201);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al crear el procedimiento', debug: $e->getMessage());
        }
    }

    /**
     * Actualizar un procedimiento y/o sus items.
     * Si se envían items, reemplaza todos los existentes.
     */
    public function update(UpdateProcedureRequest $request, Procedure $procedure): JsonResponse
    {
        try {
            $user = auth()->user();

            $procedure->load('medicalEvaluation.patient:id,user_id');

            if ($user->isRemitente() && $procedure->medicalEvaluation->patient->user_id !== $user->id) {
                return ApiResponse::forbidden();
            }

            $procedure = $this->service->update($procedure, $request->validated());

            $procedure->load([
                'items:id,procedure_id,item_name,price',
                'medicalEvaluation.patient:id,user_id,first_name,last_name,cedula',
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