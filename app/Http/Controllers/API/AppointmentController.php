<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Appointment;
use App\Models\MedicalEvaluation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * AppointmentController
 *
 * Gestiona la cita agendada para una valoración médica.
 * Cada valoración tiene como máximo una cita activa.
 *
 * Rutas en api.php:
 *   GET   /medical-evaluations/{medicalEvaluation}/appointment  → show()
 *   POST  /medical-evaluations/{medicalEvaluation}/appointment  → store()
 *   PATCH /appointments/{appointment}                           → update()
 *   DELETE /appointments/{appointment}                          → cancel()
 */
class AppointmentController extends Controller
{
    /**
     * Obtener la cita de una valoración.
     * Devuelve null si aún no existe ninguna activa.
     */
    public function show(MedicalEvaluation $medicalEvaluation): JsonResponse
    {
        $user = auth()->user();

        if ($user->isRemitente() && $medicalEvaluation->user_id !== $user->id) {
            return ApiResponse::forbidden();
        }

        $appointment = $medicalEvaluation->appointment;

        return ApiResponse::success($appointment);
    }

    /**
     * Crear o reemplazar la cita de una valoración.
     * Si ya existe una cita activa, la reemplaza.
     */
    public function store(Request $request, MedicalEvaluation $medicalEvaluation): JsonResponse
    {
        $user = auth()->user();

        if ($user->isRemitente() && $medicalEvaluation->user_id !== $user->id) {
            return ApiResponse::forbidden();
        }

        $request->validate([
            'appointment_datetime' => ['required', 'date', 'after:now'],
            'procedure_type'       => ['required', 'in:concejacion,sincecion'],
            'doctor_name'          => ['nullable', 'string', 'max:100'],
            'notes'                => ['nullable', 'string', 'max:500'],
            'duration_minutes'     => ['nullable', 'integer', 'min:15', 'max:480'],
        ]);

        try {
            $fastingRequired = $request->procedure_type === Appointment::TYPE_CONCEJACION;

            // Cancelar citas previas activas para esta valoración
            Appointment::where('medical_evaluation_id', $medicalEvaluation->id)
                ->whereIn('status', [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED])
                ->update(['status' => Appointment::STATUS_CANCELLED]);

            $appointment = Appointment::create([
                'user_id'               => auth()->id(),
                'patient_id'            => $medicalEvaluation->patient_id,
                'medical_evaluation_id' => $medicalEvaluation->id,
                'referrer_name'         => $medicalEvaluation->referrer_name ?? '',
                'appointment_datetime'  => $request->appointment_datetime,
                'duration_minutes'      => $request->duration_minutes ?? 60,
                'planned_procedures'    => [],
                'notes'                 => $request->notes,
                'status'                => Appointment::STATUS_CONFIRMED,
                'procedure_type'        => $request->procedure_type,
                'doctor_name'           => $request->doctor_name,
                'fasting_required'      => $fastingRequired,
            ]);

            return ApiResponse::success($appointment->load('patient'), 201);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al crear la cita', debug: $e->getMessage());
        }
    }

    /**
     * Actualizar una cita existente.
     */
    public function update(Request $request, Appointment $appointment): JsonResponse
    {
        $user = auth()->user();

        if ($user->isRemitente() && $appointment->medicalEvaluation?->user_id !== $user->id) {
            return ApiResponse::forbidden();
        }

        $request->validate([
            'appointment_datetime' => ['sometimes', 'date', 'after:now'],
            'procedure_type'       => ['sometimes', 'in:concejacion,sincecion'],
            'doctor_name'          => ['nullable', 'string', 'max:100'],
            'notes'                => ['nullable', 'string', 'max:500'],
            'duration_minutes'     => ['nullable', 'integer', 'min:15', 'max:480'],
        ]);

        try {
            $data = $request->only(['appointment_datetime', 'procedure_type', 'doctor_name', 'notes', 'duration_minutes']);

            if (isset($data['procedure_type'])) {
                $data['fasting_required'] = $data['procedure_type'] === Appointment::TYPE_CONCEJACION;
            }

            $appointment->update($data);

            return ApiResponse::success($appointment->fresh()->load('patient'));
        } catch (Throwable $e) {
            return ApiResponse::error('Error al actualizar la cita', debug: $e->getMessage());
        }
    }

    /**
     * Cancelar una cita.
     */
    public function cancel(Appointment $appointment): JsonResponse
    {
        $user = auth()->user();

        if ($user->isRemitente() && $appointment->medicalEvaluation?->user_id !== $user->id) {
            return ApiResponse::forbidden();
        }

        try {
            $appointment->update(['status' => Appointment::STATUS_CANCELLED]);

            return ApiResponse::success($appointment->fresh());
        } catch (Throwable $e) {
            return ApiResponse::error('Error al cancelar la cita', debug: $e->getMessage());
        }
    }
}
