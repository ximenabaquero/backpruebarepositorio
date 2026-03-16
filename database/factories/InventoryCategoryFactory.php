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
        ['name' => 'Insumos médicos',      'color' => '#3B82F6'],
        ['name' => 'Productos cosméticos', 'color' => '#EC4899'],
        ['name' => 'Equipos y herramientas','color' => '#8B5CF6'],
        ['name' => 'Medicamentos',         'color' => '#10B981'],
        ['name' => 'Materiales de oficina','color' => '#F59E0B'],
        ['name' => 'Aseo y limpieza',      'color' => '#6366F1'],
        ['name' => 'Ropa y accesorios',    'color' => '#EF4444'],
    ];

    public function definition(): array
    {
        $category = fake()->randomElement(self::CATEGORIES);

        return [
            'user_id' => User::factory()->admin(),
            'name'    => $category['name'] . ' ' . fake()->unique()->numberBetween(1, 99),
            'color'   => $category['color'],
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