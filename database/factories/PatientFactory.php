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
        return [
            // Factory lazy — crea un User remitente si no se pasa uno
            // Evita consulta DB en definition() que rompe si no hay usuarios
            'user_id'        => User::factory()->remitente(),
            'first_name'     => $this->faker->firstName(),
            'last_name'      => $this->faker->lastName(),
            'cellphone'      => $this->randomPhone(),
            'date_of_birth'  => $this->faker->dateTimeBetween('-65 years', '-18 years')
                                    ->format('Y-m-d'),
            'biological_sex' => $this->faker->randomElement(['Femenino', 'Masculino', 'Otro']),
            'document_type'  => $this->faker->randomElement(Patient::DOCUMENT_TYPES),
            'cedula'         => $this->faker->unique()->numerify('##########'),
        ];
    }

    // ─────────────────────────────────────────────
    // Estados
    // ─────────────────────────────────────────────

    /**
     * Paciente asociado a un remitente específico.
     * Útil en tests para verificar aislamiento por rol.
     *
     * Uso: Patient::factory()->forRemitente($user)->create()
     */
    public function forRemitente(User $user): static
    {
        return $this->state(fn() => ['user_id' => $user->id]);
    }

    // ─────────────────────────────────────────────
    // Helpers privados
    // ─────────────────────────────────────────────

    private function randomPhone(): string
    {
        $formats = [
            '+57 3' . $this->faker->numerify('##') . ' ' . $this->faker->numerify('###') . ' ' . $this->faker->numerify('####'),
            '+57 3' . $this->faker->numerify('##') . $this->faker->numerify('#######'),
            '3' . $this->faker->numerify('##') . $this->faker->numerify('#######'),
            '3' . $this->faker->numerify('##') . ' ' . $this->faker->numerify('###') . ' ' . $this->faker->numerify('####'),
            '+58 4' . $this->faker->numerify('##') . '-' . $this->faker->numerify('#######'),
            '+593 9' . $this->faker->numerify('########'),
            '+52 1 ' . $this->faker->numerify('##') . ' ' . $this->faker->numerify('####') . ' ' . $this->faker->numerify('####'),
            '+51 9' . $this->faker->numerify('#########'),
            '+34 6' . $this->faker->numerify('## ### ###'),
        ];

        return $this->faker->randomElement($formats);
    }
}