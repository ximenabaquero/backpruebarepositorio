<?php

namespace App\Http\Requests;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorización por middleware y controller
    }

    public function rules(): array
    {
        // El route model binding resuelve {patient} como instancia de Patient
        $patientId = $this->route('patient')?->id;

        return [
            'first_name'     => ['sometimes', 'string', 'max:100'],
            'last_name'      => ['sometimes', 'string', 'max:100'],
            'cellphone'      => ['sometimes', 'string', 'max:25'],
            'date_of_birth'  => ['sometimes', 'date', 'before:today'],
            'biological_sex' => ['sometimes', 'string', 'in:Femenino,Masculino,Otro'],
            'document_type'  => ['sometimes', 'string', 'in:' . implode(',', Patient::DOCUMENT_TYPES)],
            'cedula'         => ['sometimes', 'string', 'max:20', 'unique:patients,cedula,' . $patientId],
        ];
    }

    public function messages(): array
    {
        return [
            'date_of_birth.before'  => 'La fecha de nacimiento debe ser anterior a hoy.',
            'biological_sex.in'     => 'El sexo biológico debe ser Femenino, Masculino u Otro.',
            'cedula.unique'         => 'Ya existe un paciente registrado con esa cédula.',
            'document_type.in'      => 'El tipo de documento no es válido.',
        ];
    }
}