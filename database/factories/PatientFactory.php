<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        $user = User::inRandomOrder()->first();

        return [
            'user_id'        => $user?->id,
            'first_name'     => $this->faker->firstName(),
            'last_name'      => $this->faker->lastName(),
            'cellphone'      => $this->randomPhone(),
            'date_of_birth'  => $this->faker->dateTimeBetween('-65 years', '-18 years')->format('Y-m-d'),
            'biological_sex' => $this->faker->randomElement(['Femenino', 'Masculino', 'Otro']),
            'document_type'  => $this->faker->randomElement(Patient::DOCUMENT_TYPES),
            'cedula'         => $this->faker->unique()->numerify('##########'),
        ];
    }

    private function randomPhone(): string
    {
        $formats = [
            // Colombia — operadores móviles (3XX XXX XXXX)
            '+57 3' . $this->faker->numerify('##') . ' ' . $this->faker->numerify('###') . ' ' . $this->faker->numerify('####'),
            '+57 3' . $this->faker->numerify('##') . $this->faker->numerify('#######'),
            '3' . $this->faker->numerify('##') . $this->faker->numerify('#######'),   // sin prefijo
            '3' . $this->faker->numerify('##') . ' ' . $this->faker->numerify('###') . ' ' . $this->faker->numerify('####'),

            // Venezuela
            '+58 4' . $this->faker->numerify('##') . '-' . $this->faker->numerify('#######'),

            // Ecuador
            '+593 9' . $this->faker->numerify('########'),

            // México
            '+52 1 ' . $this->faker->numerify('##') . ' ' . $this->faker->numerify('####') . ' ' . $this->faker->numerify('####'),

            // Perú
            '+51 9' . $this->faker->numerify('#########'),

            // España
            '+34 6' . $this->faker->numerify('## ### ###'),
        ];

        return $this->faker->randomElement($formats);
    }
}