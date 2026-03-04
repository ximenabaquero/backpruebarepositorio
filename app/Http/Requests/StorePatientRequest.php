<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $fullName = trim((string) $this->input('full_name', ''));

        if ($fullName !== '' && (!$this->has('first_name') || !$this->has('last_name'))) {
            $parts = preg_split('/\s+/', $fullName) ?: [];
            $first = $parts[0] ?? '';
            $last = implode(' ', array_slice($parts, 1));

            $this->merge([
                'first_name' => $this->input('first_name', $first),
                'last_name' => $this->input('last_name', $last !== '' ? $last : $first),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            // Datos del paciente
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'cellphone' => ['required', 'string', 'max:15'],
            'biological_sex' => ['required', 'string', 'in:Femenino,Masculino,Otro'],
            'cedula' => [ 'required', 'string', 'max:15', 'unique:patients,cedula'],
            'date_of_birth' => ['required', 'date', 'before:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required'   => 'El nombre es obligatorio.',
            'last_name.required'    => 'El apellido es obligatorio.',
            'cellphone.required'    => 'El celular es obligatorio.',
            'biological_sex.required' => 'El sexo biológico es obligatorio.',
            'biological_sex.in'     => 'El sexo biológico debe ser Femenino, Masculino u Otro.',
            'cedula.required'       => 'La cédula es obligatoria.',
            'cedula.unique'         => 'Ya existe un paciente registrado con esa cédula.',
            'date_of_birth.required' => 'La fecha de nacimiento es obligatoria.',
            'date_of_birth.before'  => 'La fecha de nacimiento debe ser anterior a hoy.',
        ];
    }
}
