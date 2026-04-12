<?php

namespace Database\Factories;

use App\Models\InventoryProduct;
use App\Models\InventoryUsage;
use App\Models\MedicalEvaluation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryUsageFactory extends Factory
{
    protected $model = InventoryUsage::class;

    private const REASONS = [
        'Merma / daño',
        'Prueba de protocolo',
        'Uso general de clínica',
        'Mantenimiento de equipo',
        'Capacitación interna',
    ];

    public function definition(): array
    {
        return [
            'user_id'               => User::factory()->remitente(),
            'product_id'            => InventoryProduct::factory()->insumo()->conStock(50),
            'medical_evaluation_id' => null,
            'quantity'              => fake()->numberBetween(1, 5),
            'status'                => InventoryUsage::STATUS_SIN_PACIENTE,
            'reason'                => fake()->randomElement(self::REASONS),
            'usage_date'            => fake()->dateTimeBetween(
                Carbon::now()->startOfYear(),
                Carbon::now()
            )->format('Y-m-d'),
        ];
    }

    public function conPaciente(MedicalEvaluation $evaluation = null): static
    {
        return $this->state(function () use ($evaluation) {
            $eval = $evaluation ?? MedicalEvaluation::factory()->confirmado()->create();

            return [
                'medical_evaluation_id' => $eval->id,
                'status'                => InventoryUsage::STATUS_CON_PACIENTE,
                'reason'                => null,
            ];
        });
    }

    public function sinPaciente(string $reason = null): static
    {
        return $this->state(fn() => [
            'medical_evaluation_id' => null,
            'status'                => InventoryUsage::STATUS_SIN_PACIENTE,
            'reason'                => $reason ?? fake()->randomElement(self::REASONS),
        ]);
    }
}