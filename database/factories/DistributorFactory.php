<?php

namespace Database\Factories;

use App\Models\Distributor;
use Illuminate\Database\Eloquent\Factories\Factory;

class DistributorFactory extends Factory
{
    protected $model = Distributor::class;

    private const DISTRIBUTORS = [
        'MediSupply Colombia',
        'Farmacol Distribuciones',
        'AesthEquip SAS',
        'BioMed Insumos',
        'Derma Proveedores',
        'CosméticaPro',
        'InsuMed Bogotá',
        'SalutDistrib',
        'ClínicaStock',
        'EstetiProv',
    ];

    public function definition(): array
    {
        return [
            'name'      => fake()->randomElement(self::DISTRIBUTORS) . ' ' . fake()->unique()->numberBetween(1, 99),
            'cellphone' => fake()->numerify('3#########'), // obligatorio
            'email'      => fake()->boolean(70)
                ? fake()->unique()->safeEmail()
                : null,
        ];
    }

    // ─────────────────────────────────────────────
    // Estados
    // ─────────────────────────────────────────────

    public function withName(string $name): static
    {
        return $this->state(fn() => ['name' => $name]);
    }
}