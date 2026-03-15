<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Http\Responses\ApiResponse;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * PatientController
 *
 * Middlewares aplicados en api.php:
 *   - auth:sanctum     → usuario autenticado
 *   - active           → cuenta activa (EnsureUserIsActive)
 *
 * El check de cuenta activa ya no vive acá — está en el middleware.
 */
class PatientController extends Controller
{
    /**
     * Listar pacientes.
     * REMITENTE ve solo los suyos. ADMIN los ve todos.
     */
    public function index(Request $request): JsonResponse
    {
        $user  = auth()->user();
        $query = Patient::query();

        if ($user->isRemitente()) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('search')) {
            $search = trim($request->string('search')->toString());
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name',  'like', "%{$search}%")
                  ->orWhere('cellphone',  'like', "%{$search}%")
                  ->orWhere('cedula',     'like', "%{$search}%");
            });
        }

        return ApiResponse::success(
            $query->orderByDesc('id')->get()
        );
    }

    /**
     * Ver detalle de un paciente con historial completo.
     */
    public function show(Patient $patient): JsonResponse
    {
        try {
            $user = auth()->user();

            if ($user->isRemitente() && $patient->user_id !== $user->id) {
                return ApiResponse::forbidden();
            }

            $patient->load([
                'medicalEvaluations.procedures.items',
                'user',
            ]);

            return ApiResponse::success($patient);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al obtener el paciente', debug: $e->getMessage());
        }
    }

    /**
     * Crear paciente.
     * Si ya existe por cédula, devuelve el existente con 200.
     */
    public function store(StorePatientRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $user = auth()->user();

            // Si ya existe un paciente con esa cédula, devolverlo sin crear duplicado
            $existing = Patient::where('cedula', $data['cedula'])->first();
            if ($existing) {
                return ApiResponse::success([
                    'message' => 'El paciente ya está registrado en el sistema.',
                    'data'    => $existing,
                ]);
            }

            $patient = Patient::create([
                'user_id'        => $user->id,
                'first_name'     => $this->formatName($data['first_name']),
                'last_name'      => $this->formatName($data['last_name']),
                'cellphone'      => $data['cellphone'],
                'date_of_birth'  => $data['date_of_birth'],
                'biological_sex' => $data['biological_sex'],
                'document_type'  => $data['document_type'],
                'cedula'         => $data['cedula'],
            ]);

            return ApiResponse::success([
                'message' => 'Paciente creado correctamente',
                'data'    => $patient,
            ], 201);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al crear el paciente', debug: $e->getMessage());
        }
    }

    /**
     * Actualizar paciente.
     */
    public function update(UpdatePatientRequest $request, Patient $patient): JsonResponse
    {
        try {
            $user = auth()->user();

            if ($user->isRemitente() && $patient->user_id !== $user->id) {
                return ApiResponse::forbidden();
            }

            $validated = $request->validated();

            if (isset($validated['first_name'])) {
                $validated['first_name'] = $this->formatName($validated['first_name']);
            }

            if (isset($validated['last_name'])) {
                $validated['last_name'] = $this->formatName($validated['last_name']);
            }

            $patient->update($validated);

            return ApiResponse::success([
                'message' => 'Paciente actualizado correctamente',
                'data'    => $patient,
            ]);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al actualizar el paciente', debug: $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────
    // Privado
    // ─────────────────────────────────────────────

    /**
     * Capitaliza cada palabra del nombre.
     * "JUAN pablo" → "Juan Pablo"
     */
    private function formatName(string $name): string
    {
        return implode(' ', array_map(
            fn(string $word) => mb_strtoupper(mb_substr($word, 0, 1))
                              . mb_strtolower(mb_substr($word, 1)),
            explode(' ', trim($name))
        ));
    }
}