<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryUsage extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'quantity',
        'usage_date',
        'notes',
    ];

    protected $casts = [
        'quantity'   => 'integer',
        'usage_date' => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(InventoryProduct::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
