<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMedicalEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'weight'             => ['sometimes', 'numeric', 'min:2', 'max:400'],
            'height'             => ['sometimes', 'numeric', 'between:1.2,2.5'],
            'medical_background' => ['sometimes', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'weight.min'     => 'El peso debe ser mayor a 2 kg.',
            'weight.max'     => 'El peso no puede superar los 400 kg.',
            'height.between' => 'La altura debe estar entre 1.20 m y 2.50 m.',
        ];
    }
}