<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMedicalEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id'         => ['required', 'integer', 'exists:patients,id'],
            'weight'             => ['required', 'numeric', 'min:2', 'max:400'],
            'height'             => ['required', 'numeric', 'between:1.2,2.5'],
            'medical_background' => ['required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'patient_id.required' => 'El paciente es obligatorio.',
            'patient_id.exists'   => 'El paciente seleccionado no existe.',
            'weight.required'     => 'El peso es obligatorio.',
            'weight.min'          => 'El peso debe ser mayor a 2 kg.',
            'weight.max'          => 'El peso no puede superar los 400 kg.',
            'height.required'     => 'La altura es obligatoria.',
            'height.between'      => 'La altura debe estar entre 1.20 m y 2.50 m.',
            'medical_background.required' => 'Los antecedentes médicos son obligatorios.',
        ];
    }
}