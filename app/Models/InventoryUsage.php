<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryUsage extends Model
{
    use HasFactory;

    const STATUS_CON_PACIENTE = 'con_paciente';
    const STATUS_SIN_PACIENTE = 'sin_paciente';

    protected $fillable = [
        'product_id',
        'user_id',
        'medical_evaluation_id',
        'quantity',
        'status',
        'reason',
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

    public function medicalEvaluation()
    {
        return $this->belongsTo(MedicalEvaluation::class, 'medical_evaluation_id');
    }
}