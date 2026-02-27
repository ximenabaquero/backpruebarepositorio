<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Procedure extends Model
{
    use HasFactory;

    // Atributos
    protected $fillable = [
        'medical_evaluation_id',
        'brand_slug',
        'procedure_date',
        'total_amount',
        'notes',
    ];

    public function scopeConEvaluacionConfirmada($query)
    {
        return $query->whereHas('medicalEvaluation', function ($q) {
            $q->confirmado();
        });
    }

    /* Relaciones */
    public function medicalEvaluation()
    {
        return $this->belongsTo(MedicalEvaluation::class);
    }

    public function items()
    {
        return $this->hasMany(ProcedureItem::class);
    }
}
