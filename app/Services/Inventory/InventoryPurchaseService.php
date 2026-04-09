<?php

namespace App\Services\Inventory;

use App\Models\InventoryProduct;
use App\Models\InventoryPurchase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InventoryPurchaseService
{
    /**
     * Listado de compras con filtros opcionales.
     *
     * Filtros (todos opcionales, se combinan):
     *   product_name     → LIKE sobre inventory_products.name
     *   category_id      → exacto sobre inventory_products.category_id
     *   buyer_name       → LIKE sobre users.name
     *   distributor_name → LIKE sobre distributors.name
     */
    public function listAll(array $filters = []): Collection
    {
        return InventoryPurchase::with([
                'product.category',
                'user:id,name',
                'distributor:id,name',
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
                isset($filters['buyer_name']),
                fn($q) => $q->whereHas('user', fn($u) =>
                    $u->where('name', 'like', "%{$filters['buyer_name']}%")
                )
            )
            ->when(
                isset($filters['distributor_name']),
                fn($q) => $q->whereHas('distributor', fn($d) =>
                    $d->where('name', 'like', "%{$filters['distributor_name']}%")
                )
            )
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Registra una compra. Crea el producto si no existe; reutiliza si ya existe.
     *
     * Calculados internamente (no vienen del request):
     *   purchase_date → now()
     *   total_price   → quantity * unit_price
     *   stock         → se incrementa sobre el producto si es insumo
     *
     * $data esperado:
     *   product_id     → int|null   si viene, reutiliza el producto existente
     *   name           → string     obligatorio solo si no hay product_id
     *   category_id    → int        obligatorio solo si no hay product_id
     *   type           → string     obligatorio solo si no hay product_id
     *   description    → string|null
     *   user_id        → int        inyectado en el controller con auth()->id()
     *   distributor_id → int|null   null = compra sin distribuidor registrado
     *   quantity       → int
     *   unit_price     → float
     *   notes          → string|null
     */
    public function register(array $data): InventoryPurchase
    {
        return DB::transaction(function () use ($data) {
            $product = isset($data['product_id'])
                ? InventoryProduct::lockForUpdate()->findOrFail($data['product_id'])
                : $this->createProduct($data);

            $purchase = InventoryPurchase::create([
                'user_id'        => $data['user_id'],
                'product_id'     => $product->id,
                'distributor_id' => $data['distributor_id'] ?? null,
                'quantity'       => $data['quantity'],
                'unit_price'     => $data['unit_price'],
                'total_price'    => $data['quantity'] * $data['unit_price'],
                'purchase_date'  => now()->toDateString(),
                'notes'          => $data['notes'] ?? null,
            ]);

            // Los equipos son gasto único, no afectan stock
            if ($product->type === InventoryProduct::TYPE_INSUMO) {
                $product->increment('stock', $data['quantity']);
            }

            return $purchase->load(['product.category', 'user:id,name', 'distributor:id,name']);
        });
    }

    // ─────────────────────────────────────────────
    // Stats — dashboard del admin
    // ─────────────────────────────────────────────

    public function getTotalExpenses(?string $from = null, ?string $to = null): float
    {
        return (float) InventoryPurchase::when($from, fn($q) => $q->whereDate('purchase_date', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('purchase_date', '<=', $to))
            ->sum('total_price');
    }

    /**
     * $totalIncome viene del StatsService — este método no lo calcula, solo resta.
     */
    public function getNetProfit(float $totalIncome, ?string $from = null, ?string $to = null): float
    {
        return $totalIncome - $this->getTotalExpenses($from, $to);
    }

    // ─────────────────────────────────────────────
    // Privados
    // ─────────────────────────────────────────────

    private function createProduct(array $data): InventoryProduct
    {
        return InventoryProduct::create([
            'category_id' => $data['category_id'],
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'type'        => $data['type'],
            'stock'       => $data['type'] === InventoryProduct::TYPE_INSUMO ? 0 : null,
            'active'      => true,
        ]);
    }
}