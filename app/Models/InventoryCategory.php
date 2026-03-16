<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryCategory extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'name', 'color'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function products()
    {
        return $this->hasMany(InventoryProduct::class, 'category_id');
    }

    public function purchases()
    {
        return $this->hasMany(InventoryPurchase::class, 'category_id');
    }
}