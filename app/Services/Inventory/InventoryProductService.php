<?php

namespace App\Services\Inventory;

use App\Models\InventoryProduct;
use Illuminate\Database\Eloquent\Collection;

class InventoryProductService
{
    /**
     * Catálogo completo para el Dashboard.
     *
     * Insumos  → expone: id, name, description, category, type,
     *                     stock_actual, estado (Disponible/Crítico/Agotado)
     *                     label_stock = 'Stock'
     *
     * Equipos  → expone: id, name, description, category, type,
     *                     cantidad (alias de stock_actual)
     *                     label_stock = 'Cantidad'
     *                     estado = null (no aplica)
     *
     * stock_minimo nunca sale al front — es dato interno de alertas.
     */
    public function getCatalogForDashboard(array $filters = []): Collection
    {
        $query = InventoryProduct::with('category:id,name')
            ->select([
                'id',
                'name',
                'description',
                'type',
                'category_id',
                'stock_actual',
                'stock_minimo',
            ]);

        // Filtro por Nombre (Buscador)
        if (!empty($filters['search'])) {
            $query->where('name', 'LIKE', '%' . $filters['search'] . '%');
        }

        // Filtro por Categoría (Botones)
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        return $query->orderBy('name', 'asc')
            ->get()
            ->makeHidden(['stock_minimo', 'stock_actual']);
    }

    /**
     * Resumen de alertas para la campana de notificaciones.
     * Solo aplica a insumos — los equipos no tienen stock mínimo.
     *
     * Retorna:
     *   total_alertas → int    (número en el globo rojo)
     *   productos     → array  (lista para el dropdown)
     */
    public function getNotificationSummary(): array
    {
        $products = InventoryProduct::where('type', InventoryProduct::TYPE_INSUMO)
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->select(['id', 'name', 'stock_actual', 'stock_minimo', 'type'])
            ->get();

        return [
            'total_alertas' => $products->count(),
            'productos'     => $products->makeHidden(['stock_minimo'])->values(),
        ];
    }
}