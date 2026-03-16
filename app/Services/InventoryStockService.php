<?php

namespace App\Services;

use App\Models\InventoryProduct;
use App\Models\InventoryPurchase;
use App\Models\InventoryUsage;
use Illuminate\Support\Facades\DB;

class InventoryStockService
{
    /**
     * Registra una compra y actualiza el stock del producto si aplica.
     * Operación atómica — si falla cualquier paso, nada se persiste.
     */
    public function registerPurchase(array $data): InventoryPurchase
    {
        return DB::transaction(function () use ($data) {
            if (! empty($data['product_id'])) {
                InventoryProduct::where('id', $data['product_id'])
                    ->increment('stock', $data['quantity']);
            }

            $purchase = InventoryPurchase::create($data);
            $purchase->load(['category', 'product', 'user:id,first_name,last_name']);

            return $purchase;
        });
    }

    /**
     * Registra un consumo descontando el stock del producto.
     * Valida stock suficiente antes de operar.
     * Operación atómica.
     *
     * @throws \RuntimeException si el stock es insuficiente
     */
    public function registerUsage(array $data): InventoryUsage
    {
        $product = InventoryProduct::findOrFail($data['product_id']);

        if ($product->stock < $data['quantity']) {
            throw new \RuntimeException(
                "Stock insuficiente. Disponible: {$product->stock} unidades."
            );
        }

        return DB::transaction(function () use ($data, $product) {
            $product->decrement('stock', $data['quantity']);

            $usage = InventoryUsage::create($data);
            $usage->load(['product.category', 'user:id,first_name,last_name']);

            return $usage;
        });
    }

    /**
     * Actualiza una compra ajustando el stock correctamente.
     *
     * Casos manejados dentro de una transacción atómica:
     *   1. Cambió el producto → revertir stock anterior, sumar al nuevo
     *   2. Mismo producto, cambió cantidad → ajustar solo la diferencia
     *   3. Sin cambios de producto ni cantidad → solo actualizar datos
     */
    public function updatePurchase(InventoryPurchase $purchase, array $data): InventoryPurchase
    {
        return DB::transaction(function () use ($purchase, $data) {
            $oldProductId = $purchase->product_id;
            $newProductId = array_key_exists('product_id', $data)
                ? $data['product_id']
                : $oldProductId;

            $productChanged  = $newProductId !== $oldProductId;
            $quantityChanged = isset($data['quantity'])
                && (int) $data['quantity'] !== (int) $purchase->quantity;

            // ── Caso 1: cambió el producto ────────────────────────────────
            if ($productChanged) {
                if ($oldProductId) {
                    InventoryProduct::where('id', $oldProductId)
                        ->decrement('stock', $purchase->quantity);
                }
                if ($newProductId) {
                    InventoryProduct::where('id', $newProductId)
                        ->increment('stock', $data['quantity'] ?? $purchase->quantity);
                }

            // ── Caso 2: mismo producto, cambió cantidad ───────────────────
            } elseif ($quantityChanged && $oldProductId) {
                $diff = (int) $data['quantity'] - (int) $purchase->quantity;
                if ($diff > 0) {
                    InventoryProduct::where('id', $oldProductId)->increment('stock', $diff);
                } elseif ($diff < 0) {
                    InventoryProduct::where('id', $oldProductId)->decrement('stock', abs($diff));
                }
            }

            $purchase->update($data);

            return $purchase;
        });
    }
    public function deleteUsage(InventoryUsage $usage): void
    {
        DB::transaction(function () use ($usage) {
            InventoryProduct::where('id', $usage->product_id)
                ->increment('stock', $usage->quantity);

            $usage->delete();
        });
    }
}