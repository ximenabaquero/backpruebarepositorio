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
 * Responsabilidad: listado, creación y edición de pacientes.
 * El detalle del paciente fue eliminado — reemplazado por
 * GET /patients/{patient}/clinical-records (Vista 1) que devuelve
 * los datos del paciente junto a sus evaluaciones en una sola llamada.
 *
 * Rutas en api.php:
 *   GET  /patients           → index()
 *   POST /patients           → store()
 *   PUT  /patients/{patient} → update()
 *
 * Autorización por rol:
 *   REMITENTE → solo ve y opera sobre sus propios pacientes
 *   ADMIN     → ve y opera sobre todos los pacientes
 */
class PatientController extends Controller
{
    /**
     * Listar pacientes con búsqueda opcional por nombre, apellido, cédula o celular.
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
     * Crear paciente.
     * Si ya existe un paciente con esa cédula, devuelve el existente con 200
     * para que el frontend redirija al historial sin crear duplicado.
     */
    public function store(StorePatientRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $user = auth()->user();

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
     * Actualizar datos de un paciente.
     * REMITENTE: solo puede editar sus propios pacientes.
     */
    public function update(UpdatePatientRequest $request, Patient $patient): JsonResponse
    {
        try {
            $user = auth()->user();

            if ($user->isRemitente() && $patient->user_id !== $user->id) {
                return ApiResponse::forbidden();
            }

            $data = $request->validated();

            if (isset($data['first_name'])) {
                $data['first_name'] = $this->formatName($data['first_name']);
            }

            if (isset($data['last_name'])) {
                $data['last_name'] = $this->formatName($data['last_name']);
            }

            $patient->update($data);

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