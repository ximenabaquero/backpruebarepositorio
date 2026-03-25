<?php

namespace Database\Factories;

use App\Models\InventoryCategory;
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
            'user_id'       => User::factory()->remitente(),
            'category_id'   => InventoryCategory::factory(),
            // product_id es nullable — compra puede no estar vinculada a producto
            'product_id'    => null,
            'item_name'     => fake()->randomElement([
                'Crema hidratante',
                'Gel conductor',
                'Guantes nitrilo caja x100',
                'Alcohol antiséptico',
                'Gasas estériles',
                'Vendas elásticas',
                'Electrodos adhesivos',
                'Suero fisiológico',
            ]),
            'quantity'      => $quantity,
            'unit_price'    => $unitPrice,
            'total_price'   => $quantity * $unitPrice,
            'purchase_date' => fake()->dateTimeBetween(
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
     * Compra vinculada a un producto del inventario.
     * Útil para tests que verifican actualización de stock.
     */
    public function conProducto(InventoryProduct $product = null): static
    {
        return $this->state(function () use ($product) {
            $prod = $product ?? InventoryProduct::factory()->create();
            return [
                'product_id' => $prod->id,
                'item_name'  => $prod->name,
            ];
        });
    }

    /**
     * Compra en un mes específico — útil para tests de stats.
     */
    public function enMes(int $month, int $year = null): static
    {
        return $this->state(function () use ($month, $year) {
            $year  = $year ?? Carbon::now()->year;
            $start = Carbon::create($year, $month, 1)->startOfMonth();
            $end   = Carbon::create($year, $month, 1)->endOfMonth();

            return [
                'purchase_date' => fake()->dateTimeBetween($start, $end)->format('Y-m-d'),
            ];
        });
    }
}