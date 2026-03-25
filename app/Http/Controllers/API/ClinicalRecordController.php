<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClinicalRecordRequest;
use App\Http\Requests\StorePatientRecordRequest;
use App\Http\Responses\ApiResponse;
use App\Models\MedicalEvaluation;
use App\Models\Patient;
use App\Models\User;
use App\Services\ClinicalRecordService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * ClinicalRecordController
 *
 * Responsabilidad: vistas compuestas del registro clínico y flujos de creación.
 * Las operaciones puntuales (confirmar, cancelar, editar) viven en sus controllers.
 *
 *   patientProfile()  → GET  /patients/{patient}/clinical-records
 *                        Vista 1: perfil del paciente + tarjetas de evaluaciones
 *
 *   show()            → GET  /patients/{patient}/clinical-records/{evaluation}
 *                        Vista 2: registro clínico completo
 *                        Precios ocultos si la evaluación no es del remitente
 *
 *   store()           → POST /clinical-records
 *                        Flujo 1: crea paciente + evaluación + procedimiento
 *
 *   storeForPatient() → POST /patients/{patient}/clinical-records
 *                        Flujo 2: crea evaluación + procedimiento (paciente existente)
 *
 * Middlewares aplicados en api.php:
 *   - auth:sanctum + active
 */
class ClinicalRecordController extends Controller
{
    public function __construct(
        private readonly ClinicalRecordService $service
    ) {}

    // ─────────────────────────────────────────────
    // LECTURA
    // ─────────────────────────────────────────────
    
    /**
     * Vista 1 — Perfil del paciente.
     *
     * REMITENTE: accede solo si el paciente es suyo.
     * Ve todas las evaluaciones del paciente incluyendo las de otros remitentes.
     * Las tarjetas son solo de navegación — sin acciones, sin is_own.
     */
    public function patientProfile(Patient $patient): JsonResponse
    {
        try {
            $user = auth()->user();
 
            if ($user->isRemitente() && $patient->user_id !== $user->id) {
                return ApiResponse::forbidden();
            }
 
            return ApiResponse::success(
                $this->service->getPatientProfile($patient)
            );
        } catch (Throwable $e) {
            return ApiResponse::error('Error al obtener el perfil del paciente', debug: $e->getMessage());
        }
    }

    /**
     * Vista 2 — Registro clínico completo.
     *
     * REMITENTE: accede solo si el paciente es suyo.
     * Si la evaluación es de otro remitente → ve todo EXCEPTO precios.
     */
    public function show(Patient $patient, MedicalEvaluation $evaluation): JsonResponse
    {
        try {
            $user = auth()->user();

            if ($user->isRemitente() && $patient->user_id !== $user->id) {
                return ApiResponse::forbidden();
            }

            // Evita combinaciones inválidas de IDs en la URL
            // ej: /patients/1/clinical-records/999 donde 999 no es de patient 1
            if ($evaluation->patient_id !== $patient->id) {
                return ApiResponse::error('La evaluación no corresponde a este paciente', 404);
            }

            $evaluation = $this->service->getFullRecord($evaluation);
            $evaluation = $this->hidePricesIfNeeded($evaluation, $user);
            $evaluation->setAttribute('is_own', $evaluation->user_id === $user->id);

            return ApiResponse::success($evaluation);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al obtener el registro clínico', debug: $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────
    // ESCRITURA
    // ─────────────────────────────────────────────

    /**
     * Flujo 1 — Pantalla "Registrar paciente"
     *
     * Si la cédula ya existe devuelve 409 con los datos del paciente
     * para que el frontend pueda redirigir al historial.
     */
    public function store(StoreClinicalRecordRequest $request): JsonResponse
    {
        try {
            $result = $this->service->createWithPatient(
                $request->validated(),
                auth()->user()
            );

            $evaluation = $result['evaluation']->load([
                'patient',
                'procedures.items',
                'user:id,name,first_name,last_name',
            ]);

            return ApiResponse::success([
                'message'    => 'Registro clínico creado correctamente',
                'patient'    => $result['patient'],
                'evaluation' => $evaluation,
            ], 201);
        } catch (\RuntimeException $e) {
            // Paciente ya existe — devolver sus datos para redirigir al historial
            $payload = json_decode($e->getMessage(), true);
            if (isset($payload['code']) && $payload['code'] === 'PATIENT_EXISTS') {
                return response()->json([
                    'data' => [
                        'message' => $payload['message'],
                        'patient' => $payload['patient'],
                    ],
                    'error'   => 'PATIENT_EXISTS',
                    'message' => 'error',
                ], 409);
            }
            return ApiResponse::error('Error al crear el registro clínico', debug: $e->getMessage());
        } catch (Throwable $e) {
            return ApiResponse::error('Error al crear el registro clínico', debug: $e->getMessage());
        }
    }

    /**
     * Flujo 2 — Pantalla "Historial del paciente" → "Nuevo registro"
     *
     * El paciente ya existe — {patient} viene resuelto por route model binding.
     * REMITENTE solo puede crear registros en pacientes propios.
     */
    public function storeForPatient(
        StorePatientRecordRequest $request,
        Patient $patient
    ): JsonResponse {
        try {
            $user = auth()->user();

            if ($user->isRemitente() && $patient->user_id !== $user->id) {
                return ApiResponse::forbidden();
            }

            $result = $this->service->createForExistingPatient(
                $patient,
                $request->validated(),
                $user
            );

            $evaluation = $result['evaluation']->load([
                'patient',
                'procedures.items',
                'user:id,name,first_name,last_name',
            ]);

            return ApiResponse::success([
                'message'    => 'Registro clínico creado correctamente',
                'evaluation' => $evaluation,
            ], 201);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al crear el registro clínico', debug: $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────
    // Privado
    // ─────────────────────────────────────────────

    /**
     * Oculta precios en evaluaciones ajenas al remitente autenticado.
     *
     * ADMIN          → siempre ve precios
     * REMITENTE      → ve precios si la evaluación es suya (user_id === auth)
     *                  Si es de otro remitente → oculta total_amount y price
     *
     * makeHidden() no modifica el modelo en DB — solo afecta la serialización
     * JSON de esta respuesta.
     */
    private function hidePricesIfNeeded(MedicalEvaluation $evaluation, User $user): MedicalEvaluation
    {
        if ($user->isAdmin()) {
            return $evaluation;
        }

        if ($evaluation->user_id === $user->id) {
            return $evaluation;
        }

        $evaluation->procedures->each(function ($procedure) {
            $procedure->makeHidden('total_amount');
            $procedure->items->each(fn($item) => $item->makeHidden('price'));
        });

        return $evaluation;
    }
}