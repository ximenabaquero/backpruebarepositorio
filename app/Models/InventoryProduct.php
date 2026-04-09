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
        'stock',
        'active',
    ];

    protected $casts = [
        'active'     => 'boolean',
        'stock'      => 'integer',
    ];

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