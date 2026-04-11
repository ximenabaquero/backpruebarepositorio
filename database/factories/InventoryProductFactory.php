<?php

namespace Database\Factories;

use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryProductFactory extends Factory
{
    protected $model = InventoryProduct::class;

    private const PRODUCTS = [
        'Crema hidratante corporal', 'Gel conductor ultrasonido',
        'Aceite de masaje relajante', 'Vendas elásticas',
        'Guantes de nitrilo', 'Alcohol antiséptico 96°',
        'Gasas estériles', 'Electrodos adhesivos',
        'Suero fisiológico', 'Crema post-procedimiento',
        'Mascarilla facial', 'Sérum vitamina C',
        'Protector solar SPF50', 'Colágeno hidrolizado', 'Ácido hialurónico',
    ];

    private const EQUIPOS = [
        'Cavitador ultrasónico', 'Radiofrecuencia corporal',
        'Láser Nd:YAG', 'Camilla eléctrica',
        'Lámpara de Wood', 'Electro estimulador',
    ];

    public function definition(): array
    {
        return [
            'category_id' => InventoryCategory::factory(),
            'name'        => fake()->randomElement(self::PRODUCTS) . ' ' . fake()->unique()->numberBetween(1, 99),
            'description' => fake()->optional(0.7)->sentence(),
            'type'        => InventoryProduct::TYPE_INSUMO,
            'stock'       => fake()->numberBetween(5, 50),
            'active'      => true,
        ];
    }

    public function insumo(): static
    {
        return $this->state(fn() => [
            'type'  => InventoryProduct::TYPE_INSUMO,
            'stock' => fake()->numberBetween(5, 50),
        ]);
    }

    public function equipo(): static
    {
        return $this->state(fn() => [
            'type'  => InventoryProduct::TYPE_EQUIPO,
            'name'  => fake()->randomElement(self::EQUIPOS) . ' ' . fake()->unique()->numberBetween(1, 99),
            'stock' => 0,
        ]);
    }

    public function sinStock(): static
    {
        return $this->state(fn() => ['stock' => 0]);
    }

    public function conStock(int $cantidad): static
    {
        return $this->state(fn() => ['stock' => $cantidad]);
    }

    public function inactivo(): static
    {
        return $this->state(fn() => ['active' => false]);
    }
}