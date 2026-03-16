<?php

namespace Database\Factories;

use App\Models\InventoryProduct;
use App\Models\InventoryUsage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryUsageFactory extends Factory
{
    protected $model = InventoryUsage::class;

    public function definition(): array
    {
        return [
            'user_id'    => User::factory()->remitente(),
            // Factory lazy con stock suficiente — evita fallar por stock = 0
            'product_id' => InventoryProduct::factory()->conStock(50),
            'quantity'   => fake()->numberBetween(1, 5),
            'usage_date' => fake()->dateTimeBetween(
                Carbon::now()->startOfYear(),
                Carbon::now()
            )->format('Y-m-d'),
            'notes' => fake()->optional(0.5)->sentence(),
        ];
    }

    // ─────────────────────────────────────────────
    // Estados
    // ─────────────────────────────────────────────

    /**
     * Consumo vinculado a un producto específico.
     */
    public function deProducto(InventoryProduct $product): static
    {
        return $this->state(fn() => ['product_id' => $product->id]);
    }

    /**
     * Consumo en un mes específico — útil para tests de stats.
     */
    public function enMes(int $month, int $year = null): static
    {
        return $this->state(function () use ($month, $year) {
            $year  = $year ?? Carbon::now()->year;
            $start = Carbon::create($year, $month, 1)->startOfMonth();
            $end   = Carbon::create($year, $month, 1)->endOfMonth();

            return [
                'usage_date' => fake()->dateTimeBetween($start, $end)->format('Y-m-d'),
            ];
        });
    }
}