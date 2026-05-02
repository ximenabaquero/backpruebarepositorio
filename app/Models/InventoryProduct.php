<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryProduct extends Model
{
    use HasFactory;

    const TYPE_INSUMO = 'insumo';
    const TYPE_EQUIPO = 'equipo';

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'type',
        'stock_actual',
        'stock_minimo',
    ];

    protected $casts = [
        'stock_actual'      => 'integer',
        'stock_minimo'      => 'integer',
    ];

    protected $appends = ['estado', 'label_stock', 'cantidad'];

    /**
     * Lógica de Estado: Solo para Insumos.
     * Si es Equipo, devuelve null o vacío.
     */
    public function getEstadoAttribute(): ?string
    {
        if ($this->type === self::TYPE_EQUIPO) {
            return null; 
        }

        if ($this->stock_actual <= 0) {
            return 'Agotado';
        }

        if ($this->stock_actual <= $this->stock_minimo) {
            return 'Crítico';
        }

        return 'Disponible';
    }

    /**
     * Accessor para mostrar 'Stock' o 'Cantidad' dinámicamente.
     */
    public function getLabelStockAttribute(): string
    {
        return $this->type === self::TYPE_EQUIPO ? 'Cantidad' : 'Stock';
    }

    /**
     * Alias de stock_actual para mantener la consistencia en el front.
     */
    public function getCantidadAttribute(): int
    {
        return $this->stock_actual;
    }

    // ─────────────────────────────────────────────
    // Relaciones
    // ─────────────────────────────────────────────

    public function category()
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function usages()
    {
        return $this->hasMany(InventoryUsage::class, 'product_id');
    }

    public function purchases()
    {
        return $this->hasMany(InventoryPurchase::class, 'product_id');
    }
}