<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientPhoto extends Model
{
    const STAGES = ['antes', 'despues', 'mes1', 'mes2', 'mes3'];

    protected $fillable = [
        'patient_id',
        'medical_evaluation_id',
        'uploaded_by_user_id',
        'stage',
        'image_path',
        'notes',
        'taken_at',
    ];

    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
        ];
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by_user_id');
    }
}
