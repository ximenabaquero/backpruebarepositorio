<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryPurchase extends Model
{
    protected $fillable = [
        'user_id',
        'category_id',
        'product_id',
        'item_name',
        'quantity',
        'unit_price',
        'total_price',
        'purchase_date',
        'notes',
    ];

    protected $casts = [
        'unit_price' => 'float',
        'total_price' => 'float',
        'purchase_date' => 'date:Y-m-d',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(InventoryCategory::class, 'category_id');
    }

    public function product()
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }
}
