<?php

namespace Database\Factories;

use App\Models\MedicalEvaluation;
use App\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

class MedicalEvaluationFactory extends Factory
{
    protected $model = MedicalEvaluation::class;

    public function definition(): array
    {
        $weight = $this->faker->numberBetween(50, 90);
        $height = $this->faker->randomFloat(2, 1.50, 1.90);
        $bmi    = round($weight / ($height * $height), 2);

        $bmiStatus = match (true) {
            $bmi < 16.0 => 'Delgadez severa (< 16.0)',
            $bmi < 17.0 => 'Delgadez moderada (16.0–16.9)',
            $bmi < 18.5 => 'Delgadez leve (17.0–18.4)',
            $bmi < 25.0 => 'Peso normal (18.5–24.9)',
            $bmi < 30.0 => 'Sobrepeso (25.0–29.9)',
            $bmi < 35.0 => 'Obesidad grado I (30.0–34.9)',
            $bmi < 40.0 => 'Obesidad grado II (35.0–39.9)',
            default     => 'Obesidad grado III (≥ 40)',
        };

        // ✅ Ya NO crea paciente aquí — se recibe desde el seeder via create([...])
        return [
            'user_id'                   => null, // sobreescrito desde el seeder
            'patient_id'                => null, // sobreescrito desde el seeder
            'medical_background'        => $this->faker->sentence(),
            'weight'                    => $weight,
            'height'                    => $height,
            'bmi'                       => $bmi,
            'bmi_status'                => $bmiStatus,
            'referrer_name'             => $this->faker->name(),
            'patient_age_at_evaluation' => $this->faker->numberBetween(18, 65),
            'status'                    => MedicalEvaluation::STATUS_EN_ESPERA,
        ];
    }

    public function confirmado(): static
    {
        return $this->state(fn() => [
            'status'       => MedicalEvaluation::STATUS_CONFIRMADO,
            'confirmed_at' => now()->subDays(rand(0, 30)),
            'canceled_at'  => null,
        ]);
    }

    public function cancelado(): static
    {
        return $this->state(fn() => [
            'status'       => MedicalEvaluation::STATUS_CANCELADO,
            'canceled_at'  => now()->subDays(rand(0, 30)),
            'confirmed_at' => null,
        ]);
    }

    public function enEspera(): static
    {
        return $this->state(fn() => [
            'status'       => MedicalEvaluation::STATUS_EN_ESPERA,
            'confirmed_at' => null,
            'canceled_at'  => null,
        ]);
    }
}