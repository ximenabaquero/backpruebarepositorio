<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Distributor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'cellphone',
        'email',
    ];

    /**
     * Relación: Un distribuidor puede estar en muchas compras asociadas.
     */
    public function purchases()
    {
        return $this->hasMany(InventoryPurchase::class);
    }
}