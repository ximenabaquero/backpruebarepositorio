<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClinicalRecordRequest;
use App\Http\Requests\StorePatientRecordRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Patient;
use App\Services\ClinicalRecordService;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * ClinicalRecordController
 *
 * Dos endpoints, dos pantallas, dos flujos:
 *
 *   store()           → POST /clinical-records
 *                       Pantalla "Registrar paciente"
 *                       Crea paciente + evaluación + procedimiento
 *
 *   storeForPatient() → POST /patients/{patient}/clinical-records
 *                       Pantalla "Historial del paciente" → "Nuevo registro"
 *                       Crea evaluación + procedimiento (paciente ya existe)
 *
 * Middlewares aplicados en api.php:
 *   - auth:sanctum + active
 */
class ClinicalRecordController extends Controller
{
    public function __construct(
        private readonly ClinicalRecordService $service
    ) {}

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
}