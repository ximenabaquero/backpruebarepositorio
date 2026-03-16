<?php

namespace Database\Factories;

use App\Models\MedicalEvaluation;
use App\Models\Procedure;
use App\Models\ProcedureItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProcedureFactory extends Factory
{
    protected $model = Procedure::class;

    public function definition(): array
    {
        return [
            // Factory lazy — crea MedicalEvaluation si no se pasa una
            'medical_evaluation_id' => MedicalEvaluation::factory(),
            'procedure_date'        => now()->toDateString(),
            'brand_slug'            => config('app.brand_slug'),
            'notes'                 => $this->faker->sentence(),
            // total_amount se calcula después de crear los items
            // en afterCreating — no se hardcodea en 0
            'total_amount'          => 0,
        ];
    }

    // ─────────────────────────────────────────────
    // Hook — crea items y actualiza total_amount
    // ─────────────────────────────────────────────

    public function configure(): static
    {
        return $this->afterCreating(function (Procedure $procedure) {
            // Crear entre 1 y 3 items por procedimiento
            $items = ProcedureItem::factory()
                ->count(rand(1, 3))
                ->create(['procedure_id' => $procedure->id]);

            // Actualizar total_amount con la suma real de los items
            $procedure->update([
                'total_amount' => $items->sum('price'),
            ]);
        });
    }

    // ─────────────────────────────────────────────
    // Estados
    // ─────────────────────────────────────────────

    /**
     * Procedimiento sin items — útil para tests que agregan items manualmente.
     */
    public function sinItems(): static
    {
        return $this->afterCreating(function (Procedure $procedure) {
            // No hace nada — sobreescribe el configure() base
        });
    }
}