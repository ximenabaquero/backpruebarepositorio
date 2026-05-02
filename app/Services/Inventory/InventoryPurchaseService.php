<?php

namespace App\Services\Inventory;

use App\Models\Distributor;
use App\Models\InventoryProduct;
use App\Models\InventoryPurchase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class InventoryPurchaseService
{
    // =========================================================================
    // Listado
    // =========================================================================

    /**
     * Listado de compras con búsqueda y filtro de categoría.
     *
     * Filtros opcionales (combinables):
     *   search      → busca en nombre de producto, comprador y distribuidor (OR)
     *   category_id → filtra por categoría exacta del producto
     */
    public function listAll(array $filters = []): Collection
    {
        return InventoryPurchase::with([
                'product.category',
                'user:id,name',
                'distributor:id,name',
            ])
            ->when(
                isset($filters['search']),
                function ($q) use ($filters) {
                    $term = "%{$filters['search']}%";
                    $q->where(function ($q) use ($term) {
                        $q->whereHas('product',      fn($p) => $p->where('name', 'like', $term))
                          ->orWhereHas('user',        fn($u) => $u->where('name', 'like', $term))
                          ->orWhereHas('distributor', fn($d) => $d->where('name', 'like', $term));
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

    // =========================================================================
    // Registro
    // =========================================================================

    /**
     * Registra una compra dentro de una transacción atómica.
     * Crea el producto y/o el distribuidor si no existen.
     *
     * $data esperado (validado por StorePurchaseRequest):
     *
     *   — Producto —
     *   product_id          int|null   reutiliza existente
     *   name                string     requerido si no hay product_id
     *   category_id         int        requerido si no hay product_id
     *   type                string     requerido si no hay product_id
     *   description         string|null
     *   stock_minimo        int        requerido si no hay product_id
     *
     *   — Distribuidor (todos opcionales) —
     *   distributor_id      int|null   reutiliza existente
     *   distributor_name    string|null crea uno nuevo
     *   distributor_cellphone string|null
     *   distributor_email   string|null
     *
     *   — Compra —
     *   user_id             int        inyectado desde auth() en el controller
     *   quantity            int
     *   unit_price          float
     *   notes               string|null
     */
    public function register(array $data): InventoryPurchase
    {
        return DB::transaction(function () use ($data) {
            // Paso 1 — Producto
            $product = $this->resolveProduct($data);

            // Paso 2 — Distribuidor (null si la compra es independiente)
            $distributorId = $this->resolveDistributorId($data);

            // Paso 3 — Compra: siempre un registro nuevo (historial)
            $purchase = InventoryPurchase::create([
                'user_id'        => $data['user_id'],
                'product_id'     => $product->id,
                'distributor_id' => $distributorId,
                'quantity'       => $data['quantity'],
                'unit_price'     => $data['unit_price'],
                'total_price'    => $data['quantity'] * $data['unit_price'],
                'purchase_date'  => now()->toDateString(),
                'notes'          => $data['notes'] ?? null,
            ]);

            // Post-compra: stock_actual funciona como cantidad en equipos
            $product->increment('stock_actual', $data['quantity']);

            return $purchase->load(['product.category', 'user:id,name', 'distributor:id,name']);
        });
    }

    // =========================================================================
    // Stats
    // =========================================================================

    public function getTotalExpenses(): float
    {
        return (float) InventoryPurchase::sum('total_price');
    }

    public function getNetProfit(float $totalIncome): float
    {
        return $totalIncome - $this->getTotalExpenses();
    }

    // =========================================================================
    // Privados
    // =========================================================================

    /**
     * Devuelve el producto existente (con lock para evitar race conditions)
     * o crea uno nuevo si no se proporcionó product_id.
     */
    private function resolveProduct(array $data): InventoryProduct
    {
        if (isset($data['product_id'])) {
            return InventoryProduct::lockForUpdate()->findOrFail($data['product_id']);
        }

        $isInsumo = $data['type'] === InventoryProduct::TYPE_INSUMO;

        return InventoryProduct::create([
            'category_id'  => $data['category_id'],
            'name'         => $data['name'],
            'type'         => $data['type'],
            'description'  => $data['description']              ?? null,
            'stock_actual' => 0,
            'stock_minimo' => $isInsumo ? ($data['stock_minimo'] ?? 0) : null,
        ]);
    }

    /**
     * Resuelve el distribuidor_id según el caso:
     *   - distributor_name → firstOrCreate (nunca duplica por nombre)
     *   - distributor_id   → devuelve el id casteado
     *   - ninguno          → null (compra sin distribuidor)
     */
    private function resolveDistributorId(array $data): ?int
    {
        if (!empty($data['distributor_name'])) {
            return Distributor::firstOrCreate(
                ['name' => $data['distributor_name']],
                [
                    'cellphone' => $data['distributor_cellphone'] ?? null,
                    'email'     => $data['distributor_email']     ?? null,
                ]
            )->id;
        }

        return isset($data['distributor_id']) ? (int) $data['distributor_id'] : null;
    }
}