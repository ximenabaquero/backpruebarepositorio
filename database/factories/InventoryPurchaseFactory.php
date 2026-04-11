<?php

namespace Database\Factories;

use App\Models\Distributor;
use App\Models\InventoryProduct;
use App\Models\InventoryPurchase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

class InventoryPurchaseFactory extends Factory
{
    protected $model = InventoryPurchase::class;

    public function definition(): array
    {
        $quantity  = fake()->numberBetween(1, 20);
        $unitPrice = fake()->randomElement([
            15000, 25000, 35000, 45000, 60000,
            80000, 100000, 120000, 150000, 200000,
        ]);

        return [
            'user_id'        => User::factory()->remitente(),
            'product_id'     => InventoryProduct::factory()->insumo(),
            // 70% con distribuidor, 30% compra independiente sin distribuidor
            'distributor_id' => fake()->boolean(70)
                ? Distributor::factory()
                : null,
            'quantity'       => $quantity,
            'unit_price'     => $unitPrice,
            'total_price'    => $quantity * $unitPrice,
            'purchase_date'  => fake()->dateTimeBetween(
                Carbon::now()->startOfYear(),
                Carbon::now()
            )->format('Y-m-d'),
            'notes' => fake()->optional(0.4)->sentence(),
        ];
    }

    // ─────────────────────────────────────────────
    // Estados
    // ─────────────────────────────────────────────

    public function paraProducto(InventoryProduct $product): static
    {
        return $this->state(fn() => ['product_id' => $product->id]);
    }

    public function conDistribuidor(Distributor $distributor = null): static
    {
        return $this->state(fn() => [
            'distributor_id' => $distributor?->id ?? Distributor::factory(),
        ]);
    }

    public function sinDistribuidor(): static
    {
        return $this->state(fn() => ['distributor_id' => null]);
    }

    public function enMes(int $month, int $year = null): static
    {
        return $this->state(function () use ($month, $year) {
            $year  = $year ?? Carbon::now()->year;
            $start = Carbon::create($year, $month)->startOfMonth();
            $end   = Carbon::create($year, $month)->endOfMonth();

            return [
                'purchase_date' => fake()->dateTimeBetween($start, $end)->format('Y-m-d'),
            ];
        });
    }
}