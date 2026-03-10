<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryProduct extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'description',
        'unit_price',
        'stock',
        'active',
    ];

    protected $casts = [
        'unit_price' => 'float',
        'stock'      => 'integer',
        'active'     => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(InventoryCategory::class);
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(InventoryPurchase::class, 'product_id');
    }

    public function usages(): HasMany
    {
        return $this->hasMany(InventoryUsage::class, 'product_id');
    }

    public function isLowStock(): bool
    {
        return $this->stock < 5;
    }
}
