<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'name',
        'description',
        'unit_price',
        'stock',
        'active',
    ];

    protected $casts = [
        'active'     => 'boolean',
        'unit_price' => 'float',
        'stock'      => 'integer',
    ];

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