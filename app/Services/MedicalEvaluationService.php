<?php

namespace App\Services;

use App\Models\MedicalEvaluation;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MedicalEvaluationService
{
    // ─────────────────────────────────────────────
    // CREAR
    // ─────────────────────────────────────────────

    /**
     * Crea una nueva valoración médica calculando BMI y edad del paciente.
     */
    public function create(array $data, User $user): MedicalEvaluation
    {
        $patient = Patient::findOrFail($data['patient_id']);
        $bmi     = $this->calculateBmi($data['weight'], $data['height']);

        return MedicalEvaluation::create([
            'user_id'                   => $user->id,
            'patient_id'                => $data['patient_id'],
            'medical_background'        => $data['medical_background'],
            'weight'                    => $data['weight'],
            'height'                    => $data['height'],
            'bmi'                       => $bmi,
            'bmi_status'                => $this->getBmiStatus($bmi),
            'referrer_name'             => $user->name,
            'patient_age_at_evaluation' => $patient->age,
            'status'                    => MedicalEvaluation::STATUS_EN_ESPERA,
        ]);
    }

    // ─────────────────────────────────────────────
    // ACTUALIZAR
    // ─────────────────────────────────────────────

    /**
     * Actualiza datos clínicos de una valoración en estado EN_ESPERA.
     * Recalcula BMI si cambia peso o altura.
     */
    public function update(MedicalEvaluation $evaluation, array $data): MedicalEvaluation
    {
        DB::transaction(function () use ($evaluation, $data) {
            if (isset($data['weight'])) {
                $evaluation->weight = $data['weight'];
            }

            if (isset($data['height'])) {
                $evaluation->height = $data['height'];
            }

            if (isset($data['medical_background'])) {
                $evaluation->medical_background = $data['medical_background'];
            }

            // Recalcular BMI solo si cambió peso o altura
            if (isset($data['weight']) || isset($data['height'])) {
                $bmi = $this->calculateBmi($evaluation->weight, $evaluation->height);
                $evaluation->bmi        = $bmi;
                $evaluation->bmi_status = $this->getBmiStatus($bmi);
            }

            // Snapshot de la edad actual del paciente
            $evaluation->patient_age_at_evaluation = $evaluation->patient->age;

            $evaluation->save();
        });

        return $evaluation;
    }

    // ─────────────────────────────────────────────
    // CAMBIOS DE ESTADO
    // ─────────────────────────────────────────────

    /**
     * Confirma una valoración registrando auditoría completa.
     */
    public function confirmar(
        MedicalEvaluation $evaluation,
        string $signature,
        int $userId
    ): MedicalEvaluation {
        DB::transaction(function () use ($evaluation, $signature, $userId) {
            $evaluation->status               = MedicalEvaluation::STATUS_CONFIRMADO;
            $evaluation->confirmed_at         = now();
            $evaluation->confirmed_by_user_id = $userId;
            $evaluation->patient_signature    = $signature;
            $evaluation->terms_accepted_at    = now();
            // Limpiar datos de cancelación previa
            $evaluation->canceled_at          = null;
            $evaluation->canceled_by_user_id  = null;
            $evaluation->save();
        });

        return $evaluation;
    }

    /**
     * Cancela una valoración registrando auditoría completa.
     */
    public function cancelar(MedicalEvaluation $evaluation, int $userId): MedicalEvaluation
    {
        DB::transaction(function () use ($evaluation, $userId) {
            $evaluation->status               = MedicalEvaluation::STATUS_CANCELADO;
            $evaluation->canceled_at          = now();
            $evaluation->canceled_by_user_id  = $userId;
            // Limpiar datos de confirmación previa
            $evaluation->confirmed_at         = null;
            $evaluation->confirmed_by_user_id = null;
            $evaluation->save();
        });

        return $evaluation;
    }

    // ─────────────────────────────────────────────
    // BMI — lógica de dominio pura
    // ─────────────────────────────────────────────

    /**
     * Calcula el índice de masa corporal.
     * Fórmula: peso (kg) / altura (m)²
     */
    public function calculateBmi(float $weight, float $height): float
    {
        return round($weight / ($height ** 2), 2);
    }

    /**
     * Clasifica el BMI según estándares OMS.
     */
    public function getBmiStatus(float $bmi): string
    {
        return match (true) {
            $bmi < 16.0 => 'Delgadez severa (< 16.0)',
            $bmi < 17.0 => 'Delgadez moderada (16.0–16.9)',
            $bmi < 18.5 => 'Delgadez leve (17.0–18.4)',
            $bmi < 25.0 => 'Peso normal (18.5–24.9)',
            $bmi < 30.0 => 'Sobrepeso (25.0–29.9)',
            $bmi < 35.0 => 'Obesidad grado I (30.0–34.9)',
            $bmi < 40.0 => 'Obesidad grado II (35.0–39.9)',
            default     => 'Obesidad grado III (≥ 40)',
        };
    }
}