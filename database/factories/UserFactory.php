<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        $faker = fake();
        return [
            'first_name' => $faker->firstName(),
            'last_name'  => $faker->lastName(),
            'cellphone'  => $faker->numerify('##########'),
            'brand_name' => config('app.brand_name'),
            'brand_slug' => config('app.brand_slug'),
            'name'       => $faker->unique()->userName(),
            'email'      => $faker->unique()->safeEmail(),
            'password'   => Hash::make('password'),
            // role y status se asignan con forceFill porque no están
            // en $fillable — son campos sensibles protegidos contra
            // mass assignment en producción
            'role'   => User::ROLE_REMITENTE,
            'status' => User::STATUS_ACTIVE,
        ];
    }

    // ─────────────────────────────────────────────
    // Estados
    // ─────────────────────────────────────────────

    public function admin(): static
    {
        return $this->state(fn() => [
            'role'   => User::ROLE_ADMIN,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    public function remitente(): static
    {
        return $this->state(fn() => [
            'role'   => User::ROLE_REMITENTE,
            'status' => User::STATUS_ACTIVE,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn() => [
            'status' => User::STATUS_INACTIVE,
        ]);
    }

    public function despedido(): static
    {
        return $this->state(fn() => [
            'status' => User::STATUS_FIRED,
        ]);
    }

    // ─────────────────────────────────────────────
    // Hook — forceFill para campos fuera de $fillable
    // ─────────────────────────────────────────────

    /**
     * forceFill permite asignar role y status en tests
     * aunque no estén en $fillable del modelo.
     * Esto es seguro porque las factories solo se usan en
     * entornos de test/seed, nunca desde input del usuario.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (User $user) {
            $user->forceFill([
                'role'   => $user->role   ?? User::ROLE_REMITENTE,
                'status' => $user->status ?? User::STATUS_ACTIVE,
            ]);
        });
    }
}