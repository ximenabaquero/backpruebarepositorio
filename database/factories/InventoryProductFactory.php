<?php

namespace Database\Factories;

use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryProductFactory extends Factory
{
    protected $model = InventoryProduct::class;

    // Productos realistas para una clínica estética
    private const PRODUCTS = [
        'Crema hidratante corporal',
        'Gel conductor ultrasonido',
        'Aceite de masaje relajante',
        'Vendas elásticas',
        'Guantes de nitrilo',
        'Alcohol antiséptico 96°',
        'Gasas estériles',
        'Electrodos adhesivos',
        'Suero fisiológico',
        'Crema post-procedimiento',
        'Mascarilla facial',
        'Sérum vitamina C',
        'Protector solar SPF50',
        'Colágeno hidrolizado',
        'Ácido hialurónico',
    ];

    public function definition(): array
    {
        return [
            'category_id' => InventoryCategory::factory(),
            'name'        => fake()->randomElement(self::PRODUCTS) . ' ' . fake()->unique()->numberBetween(1, 99),
            'description' => fake()->optional(0.7)->sentence(),
            // Precios en COP realistas
            'unit_price'  => fake()->randomElement([
                15000, 25000, 35000, 45000, 60000,
                80000, 100000, 120000, 150000, 200000,
            ]),
            'stock'  => fake()->numberBetween(0, 50),
            'active' => true,
        ];
    }

    // ─────────────────────────────────────────────
    // Estados
    // ─────────────────────────────────────────────

    public function sinStock(): static
    {
        return $this->state(fn() => ['stock' => 0]);
    }

    public function inactivo(): static
    {
        return $this->state(fn() => ['active' => false]);
    }

    public function conStock(int $cantidad): static
    {
        return $this->state(fn() => ['stock' => $cantidad]);
    }
}