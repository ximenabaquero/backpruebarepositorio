<?php

namespace Database\Factories;

use App\Models\Procedure;
use App\Models\MedicalEvaluation;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProcedureFactory extends Factory
{
    protected $model = Procedure::class;

    public function definition(): array
    {
        return [
            'medical_evaluation_id' => MedicalEvaluation::factory(),
            'procedure_date'        => now()->toDateString(),
            'total_amount'          => 0, 
            'brand_slug'            => config('app.brand_slug'),
            'notes'                 => $this->faker->sentence(),
        ];
    }
}