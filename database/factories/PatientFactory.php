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
            'cellphone'      => $this->faker->numerify('##########'), // 10 dígitos
            'date_of_birth'  => $this->faker->dateTimeBetween('-65 years', '-18 years')->format('Y-m-d'),
            'biological_sex' => $this->faker->randomElement(['Femenino', 'Masculino', 'Otro']),
            'cedula'         => $this->faker->unique()->numerify('##########'),
        ];
    }
}
