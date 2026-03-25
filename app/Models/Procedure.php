<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Procedure extends Model
{
    use HasFactory;

    protected $fillable = [
        'medical_evaluation_id',
        'brand_slug',
        'procedure_date',
        'total_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'procedure_date' => 'date',
            'total_amount'   => 'float',
        ];
    }

    // ─────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────

    /**
     * Filtra procedimientos con evaluación confirmada.
     * Acepta referrerName opcional para filtrar por remitente.
     *
     * Usa join en vez de whereHas para evitar subquery EXISTS
     * que genera full scan sin índice compuesto.
     */
    public function scopeConEvaluacionConfirmada(
        Builder $query,
        ?string $referrerName = null
    ): Builder {
        return $query
            ->join(
                'medical_evaluations',
                'procedures.medical_evaluation_id',
                '=',
                'medical_evaluations.id'
            )
            ->where('medical_evaluations.status', 'CONFIRMADO')
            ->when($referrerName, fn($q) => $q->where('medical_evaluations.referrer_name', $referrerName))
            ->select('procedures.*'); // evita que el join sobreescriba columnas del modelo
    }

    // ─────────────────────────────────────────────
    // Relaciones
    // ─────────────────────────────────────────────

    public function medicalEvaluation()
    {
        return $this->belongsTo(MedicalEvaluation::class);
    }

    public function items()
    {
        return $this->hasMany(ProcedureItem::class);
    }
}