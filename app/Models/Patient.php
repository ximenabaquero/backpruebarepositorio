<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Patient extends Model
{
    use HasFactory;

    const DOCUMENT_TYPES = [
        'Cédula de Ciudadanía',
        'Cédula de Extranjería',
        'Pasaporte',
        'Tarjeta de Identidad',
    ];

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'cellphone',
        'date_of_birth',
        'biological_sex',
        'document_type',
        'cedula',
    ];

    /**
     * `age` se incluye automáticamente en toda respuesta JSON del modelo.
     * Evita tener que calcularlo en el frontend o pedirlo explícitamente.
     */
    protected $appends = ['age', 'full_name'];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
        ];
    }

    // ─────────────────────────────────────────────
    // Accessors
    // ─────────────────────────────────────────────

    /**
     * Edad calculada desde date_of_birth.
     * Con el cast 'date' ya es un Carbon — no necesita parse().
     */
    public function getAgeAttribute(): int
    {
        return $this->date_of_birth->age;
    }

    /**
     * Nombre completo formateado — útil para el frontend.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    // ─────────────────────────────────────────────
    // Relaciones
    // ─────────────────────────────────────────────

    public function user()
    {
        return $this->belongsTo(User::class)
            ->select(['id', 'first_name', 'last_name', 'name']);
    }

    public function medicalEvaluations()
    {
        return $this->hasMany(MedicalEvaluation::class);
    }
}