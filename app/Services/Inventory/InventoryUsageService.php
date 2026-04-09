<?php

namespace App\Services\Inventory;

use App\Exceptions\Inventory\EquipoHasNoStockException;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Models\InventoryProduct;
use App\Models\InventoryUsage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InventoryUsageService
{
    /**
     * Listado de consumos con filtros opcionales.
     *
     * Filtros (todos opcionales, se combinan):
     *   product_name → LIKE sobre inventory_products.name
     *   category_id  → exacto sobre inventory_products.category_id
     *   user_name    → LIKE sobre users.name
     *   status       → 'con_paciente' | 'sin_paciente'
     */
    public function listAll(array $filters = []): Collection
    {
        return InventoryUsage::with([
                'product.category',
                'user:id,name',
                'medicalEvaluation:id',
            ])
            ->when(
                isset($filters['product_name']),
                fn($q) => $q->whereHas('product', fn($p) =>
                    $p->where('name', 'like', "%{$filters['product_name']}%")
                )
            )
            ->when(
                isset($filters['category_id']),
                fn($q) => $q->whereHas('product', fn($p) =>
                    $p->where('category_id', $filters['category_id'])
                )
            )
            ->when(
                isset($filters['user_name']),
                fn($q) => $q->whereHas('user', fn($u) =>
                    $u->where('name', 'like', "%{$filters['user_name']}%")
                )
            )
            ->when(
                isset($filters['status']),
                fn($q) => $q->where('status', $filters['status'])
            )
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Consumo desde un expediente clínico (con paciente).
     * El request ya garantiza que el medical_evaluation está CONFIRMADO.
     *
     * usage_date → automático con now().
     * $items = [['product_id' => int, 'quantity' => int], ...]
     */
    public function registerClinical(
        int $userId,
        int $medicalEvaluationId,
        array $items,
        ?string $reason = null
    ): Collection {
        return DB::transaction(function () use ($userId, $medicalEvaluationId, $items, $reason) {
            return collect($items)
                ->filter(fn($item) => $item['quantity'] > 0)
                ->map(function ($item) use ($userId, $medicalEvaluationId, $reason) {
                    $product = InventoryProduct::lockForUpdate()->findOrFail($item['product_id']);

                    $this->guardEquipo($product);
                    $this->guardStock($product, $item['quantity']);

                    $product->decrement('stock', $item['quantity']);

                    return InventoryUsage::create([
                        'product_id'            => $product->id,
                        'user_id'               => $userId,
                        'medical_evaluation_id' => $medicalEvaluationId,
                        'quantity'              => $item['quantity'],
                        'status'                => InventoryUsage::STATUS_CON_PACIENTE,
                        'reason'                => $reason,
                        'usage_date'            => now()->toDateString(),
                    ])->load('product.category', 'user:id,name');
                });
        });
    }

    /**
     * Consumo general sin paciente (merma, prueba, mantenimiento, etc).
     *
     * usage_date → automático con now().
     * $items = [['product_id' => int, 'quantity' => int], ...]
     */
    public function registerGeneral(int $userId, string $reason, array $items): Collection
    {
        return DB::transaction(function () use ($userId, $reason, $items) {
            return collect($items)
                ->filter(fn($item) => $item['quantity'] > 0)
                ->map(function ($item) use ($userId, $reason) {
                    $product = InventoryProduct::lockForUpdate()->findOrFail($item['product_id']);

                    $this->guardEquipo($product);
                    $this->guardStock($product, $item['quantity']);

                    $product->decrement('stock', $item['quantity']);

                    return InventoryUsage::create([
                        'product_id'            => $product->id,
                        'user_id'               => $userId,
                        'medical_evaluation_id' => null,
                        'quantity'              => $item['quantity'],
                        'status'                => InventoryUsage::STATUS_SIN_PACIENTE,
                        'reason'                => $reason,
                        'usage_date'            => now()->toDateString(),
                    ])->load('product.category', 'user:id,name');
                });
        });
    }

    // ─────────────────────────────────────────────
    // Guards
    // ─────────────────────────────────────────────

    private function guardEquipo(InventoryProduct $product): void
    {
        if ($product->type === InventoryProduct::TYPE_EQUIPO) {
            throw new EquipoHasNoStockException($product->name);
        }
    }

    private function guardStock(InventoryProduct $product, int $quantity): void
    {
        if ($product->stock < $quantity) {
            throw new InsufficientStockException($product->name, $product->stock, $quantity);
        }
    }
}