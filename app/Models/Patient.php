<?php

namespace App\Models;

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

    // Atributos
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

    public function getAgeAttribute()
    {
        return \Carbon\Carbon::parse($this->date_of_birth)->age;
    }

    /* Relaciones */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function medicalEvaluations()
    {
        return $this->hasMany(MedicalEvaluation::class);
    }
}
