<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    const STATUS_PENDING   = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    const TYPE_CONCEJACION = 'concejacion';
    const TYPE_SINCECION   = 'sincecion';

    protected $fillable = [
        'user_id',
        'patient_id',
        'medical_evaluation_id',
        'referrer_name',
        'appointment_datetime',
        'duration_minutes',
        'planned_procedures',
        'notes',
        'status',
        'google_calendar_event_id',
        'procedure_id',
        'procedure_type',
        'doctor_name',
        'fasting_required',
    ];

    protected function casts(): array
    {
        return [
            'appointment_datetime' => 'datetime',
            'planned_procedures'   => 'array',
            'fasting_required'     => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function medicalEvaluation()
    {
        return $this->belongsTo(MedicalEvaluation::class);
    }

    public function procedure()
    {
        return $this->belongsTo(Procedure::class);
    }
}
