<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'quantity',
        'usage_date',
        'notes',
    ];

    protected $casts = [
        'usage_date' => 'date:Y-m-d',
    ];

    public function product()
    {
        return $this->belongsTo(InventoryProduct::class, 'product_id')
                    ->with('category');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}