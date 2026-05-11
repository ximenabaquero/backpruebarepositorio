<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExamOrder extends Model
{
    const STATUS_PENDIENTE = 'pendiente';
    const STATUS_APTO      = 'apto';
    const STATUS_NO_APTO   = 'no_apto';

    protected $fillable = [
        'medical_evaluation_id',
        'exams',
        'status',
        'notes',
        'received_at',
        'result_file_path',
    ];

    protected function casts(): array
    {
        return [
            'exams'       => 'array',
            'received_at' => 'datetime',
        ];
    }

    public function isPendiente(): bool
    {
        return $this->status === self::STATUS_PENDIENTE;
    }

    public function medicalEvaluation()
    {
        return $this->belongsTo(MedicalEvaluation::class);
    }
}
