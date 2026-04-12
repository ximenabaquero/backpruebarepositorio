<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryPurchase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'distributor_id',
        'quantity',
        'unit_price',
        'total_price',
        'purchase_date',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date:Y-m-d',
        'unit_price'    => 'decimal:2',
        'total_price'   => 'decimal:2',
    ];

    // ─────────────────────────────────────────────
    // Relaciones
    // ─────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id');
    }

    public function distributor()
    {
        return $this->belongsTo(Distributor::class);
    }
}