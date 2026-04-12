<?php

namespace Database\Factories;

use App\Models\InventoryCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryCategoryFactory extends Factory
{
    protected $model = InventoryCategory::class;

    // Categorías realistas para una clínica estética
    private const CATEGORIES = [
        ['name' => 'Insumos médicos'],
        ['name' => 'Productos cosméticos'],
        ['name' => 'Equipos y herramientas'],
        ['name' => 'Medicamentos'],
        ['name' => 'Materiales de oficina'],
        ['name' => 'Aseo y limpieza'],
        ['name' => 'Ropa y accesorios'],
    ];

    public function definition(): array
    {
        $category = fake()->randomElement(self::CATEGORIES);

        return [
            'user_id' => User::factory()->admin(),
            'name'    => $category['name'] . ' ' . fake()->unique()->numberBetween(1, 99),
        ];
    }

    // ─────────────────────────────────────────────
    // Estados
    // ─────────────────────────────────────────────

    /**
     * Categoría con nombre fijo — útil para tests que buscan por nombre.
     */
    public function withName(string $name): static
    {
        return $this->state(fn() => ['name' => $name]);
    }
}