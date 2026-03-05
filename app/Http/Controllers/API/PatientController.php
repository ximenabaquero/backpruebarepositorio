<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientRequest;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    // LISTAR PACIENTES
    public function index(Request $request)
    {
        $user = auth()->user();
        $query = Patient::query();

        // REMITENTE solo ve sus propios pacientes
        if ($user->isRemitente()) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('cellphone', 'like', "%{$search}%")
                ->orWhere('cedula', 'like', "%{$search}%");
            });
        }

        return response()->json(
            $query->orderByDesc('id')->get()
        );
    }

    // VER PACIENTE
    public function show(Patient $patient)
    {
        try {
            $user = auth()->user();
            if ($user->isRemitente() && $patient->user_id !== $user->id) {
                return response()->json(['message' => 'No autorizado'], 403);
            }

            $patient->load([
                'medicalEvaluations.procedures.items',
                'user',
            ]);

            return response()->json($patient);
        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

   // CREAR PACIENTE
    public function store(StorePatientRequest $request)
    {
        try {
            $data = $request->validated();

            $user = auth()->user(); // obtenemos el usuario autenticado
            if (!$user) {
                return response()->json([
                    'message' => 'No autenticado'
                ], 401);
            }

            // Bloquear si está inactivo o despedido
            if ($user->status !== User::STATUS_ACTIVE) {
                return response()->json([
                    'message' => 'Tu cuenta no está activa. No puedes registrar pacientes.'
                ], 403);
            }

            // Verificar si ya existe un paciente con la misma cédula
            $existingPatient = Patient::where('cedula', $data['cedula'])->first();
            if ($existingPatient) {
                return response()->json([
                    'message' => 'El paciente ya está registrado en el sistema.',
                    'data'    => $existingPatient,
                ], 200);
            }

            // Crear nuevo paciente
            $patient = Patient::create([
                'user_id'        => $user->id,
                'first_name'     => $data['first_name'],
                'last_name'      => $data['last_name'],
                'cellphone'      => $data['cellphone'],
                'date_of_birth'  => $data['date_of_birth'], 
                'biological_sex' => $data['biological_sex'],
                'cedula'         => $data['cedula'],
            ]);

            return response()->json([
                'message' => 'Paciente creado correctamente',
                'data'    => $patient,
            ], 201);

        } catch (\Throwable $th) {
            return response()->json([
                'error'   => 'Error interno del servidor',
                'details' => $th->getMessage(),
            ], 500);
        }
    }

   // ACTUALIZAR PACIENTE
    public function update(Request $request, Patient $patient)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['message' => 'No autenticado'], 401);
            }

            if ($user->status !== User::STATUS_ACTIVE) {
                return response()->json([
                    'message' => 'Tu cuenta no está activa. No puedes modificar pacientes.'
                ], 403);
            }

            if ($user->isRemitente() && $patient->user_id !== $user->id) {
                return response()->json(['message' => 'No autorizado'], 403);
            }

            $request->validate([
                'first_name'     => 'sometimes|string|max:100',
                'last_name'      => 'sometimes|string|max:100',
                'cellphone'      => 'sometimes|string|max:15',
                'date_of_birth'  => 'sometimes|date',
                'biological_sex' => 'sometimes|string|in:Masculino,Femenino,Otro',
                'cedula'         => 'sometimes|string|max:20|unique:patients,cedula,' . $patient->id,
            ]);

            $patient->update($request->only([
                'first_name',
                'last_name',
                'cellphone',
                'date_of_birth',
                'biological_sex',
                'cedula',
            ]));

            return response()->json([
                'message' => 'Paciente actualizado correctamente',
                'data'    => $patient,
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'error'   => 'Error interno del servidor',
                'details' => $th->getMessage(),
            ], 500);
        }
    }

}
