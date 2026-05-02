<?php

namespace App\Services\Inventory;

use App\Exceptions\Inventory\InsufficientStockException;
use App\Models\InventoryProduct;
use App\Models\InventoryUsage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InventoryUsageService
{
    /**
     * Listado de consumos con búsqueda y filtro de categoría.
     *
     * $filters:
     *   search      → string — busca en nombre de producto y nombre de quien registró (OR)
     *   category_id → int    — filtra por categoría exacta del producto
     */
    public function listAll(array $filters = []): Collection
    {
        return InventoryUsage::with([
                'product.category',
                'user:id,name',
                'medicalEvaluation:id',
            ])
            ->when(
                isset($filters['search']),
                function ($q) use ($filters) {
                    $term = "%{$filters['search']}%";
                    $q->where(function ($q) use ($term) {
                        $q->whereHas('product', fn($p) => $p->where('name', 'like', $term))
                          ->orWhereHas('user',   fn($u) => $u->where('name', 'like', $term));
                    });
                }
            )
            ->when(
                isset($filters['category_id']),
                fn($q) => $q->whereHas('product', fn($p) =>
                    $p->where('category_id', $filters['category_id'])
                )
            )
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Consumo desde un expediente clínico (con paciente).
     * El request ya garantiza que el medical_evaluation está CONFIRMADO.
     *
     * Insumos  → valida stock y descuenta.
     * Equipos  → registra el uso sin tocar stock_actual (no se "gasta").
     *
     * $items = [['product_id' => int, 'quantity' => int], ...]
     */
    public function registerClinical(
        int $userId,
        int $medicalEvaluationId,
        array $items,
        ?string $reason = null
    ): Collection {
        return DB::transaction(function () use ($userId, $medicalEvaluationId, $items, $reason) {
            $usages = [];

            foreach ($items as $item) {
                if ($item['quantity'] <= 0) {
                    continue;
                }

                $product = InventoryProduct::lockForUpdate()->findOrFail($item['product_id']);

                // Solo insumos descuentan stock
                if ($product->type === InventoryProduct::TYPE_INSUMO) {
                    $this->guardStock($product, $item['quantity']);
                    $product->decrement('stock_actual', $item['quantity']);
                }

                $usages[] = InventoryUsage::create([
                    'product_id'            => $product->id,
                    'user_id'               => $userId,
                    'medical_evaluation_id' => $medicalEvaluationId,
                    'quantity'              => $item['quantity'],
                    'status'                => InventoryUsage::STATUS_CON_PACIENTE,
                    'reason'                => $reason,
                    'usage_date'            => now()->toDateString(),
                ])->load('product.category', 'user:id,name');
            }

            return InventoryUsage::whereIn('id', collect($usages)->pluck('id'))->get();
        });
    }

    /**
     * Consumo general sin paciente (merma, prueba, mantenimiento, etc).
     *
     * Insumos  → valida stock y descuenta.
     * Equipos  → registra el uso sin tocar stock_actual (no se "gasta").
     *
     * $items = [['product_id' => int, 'quantity' => int], ...]
     */
    public function registerGeneral(int $userId, string $reason, array $items): Collection
    {
        return DB::transaction(function () use ($userId, $reason, $items) {
            $usages = [];

            foreach ($items as $item) {
                if ($item['quantity'] <= 0) {
                    continue;
                }

                $product = InventoryProduct::lockForUpdate()->findOrFail($item['product_id']);

                // Solo insumos descuentan stock
                if ($product->type === InventoryProduct::TYPE_INSUMO) {
                    $this->guardStock($product, $item['quantity']);
                    $product->decrement('stock_actual', $item['quantity']);
                }

                $usages[] = InventoryUsage::create([
                    'product_id'            => $product->id,
                    'user_id'               => $userId,
                    'medical_evaluation_id' => null,
                    'quantity'              => $item['quantity'],
                    'status'                => InventoryUsage::STATUS_SIN_PACIENTE,
                    'reason'                => $reason,
                    'usage_date'            => now()->toDateString(),
                ]);
            }

            return InventoryUsage::with('product.category', 'user:id,name')
                ->whereIn('id', collect($usages)->pluck('id'))
                ->get();
        });
    }

    // ─────────────────────────────────────────────
    // Guards
    // ─────────────────────────────────────────────

    /**
     * Valida que haya stock suficiente.
     * Solo se llama para insumos — los equipos no pasan por aquí.
     *
     * @throws InsufficientStockException
     */
    private function guardStock(InventoryProduct $product, int $quantity): void
    {
        if ($product->stock_actual < $quantity) {
            throw new InsufficientStockException(
                $product->name,
                $product->stock_actual,
                $quantity
            );
        }
    }
}