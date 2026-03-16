<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcedureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medical_evaluation_id' => [
                'required',
                'integer',
                'exists:medical_evaluations,id',
                // REMOVIDO: 'unique:procedures,medical_evaluation_id'
                // Una evaluación puede tener múltiples procedimientos
                // (el frontend itera procedures.map() — es una relación hasMany)
            ],
            'notes'                 => ['required', 'string'],
            'items'                 => ['required', 'array', 'min:1'],
            'items.*.item_name'     => ['required', 'string', 'max:100'],
            'items.*.price'         => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'medical_evaluation_id.required' => 'La valoración médica es obligatoria.',
            'medical_evaluation_id.exists'   => 'La valoración médica no existe.',
            'notes.required'                 => 'Las notas clínicas son obligatorias.',
            'items.required'                 => 'Debe agregar al menos un procedimiento.',
            'items.min'                      => 'Debe agregar al menos un procedimiento.',
            'items.*.item_name.required'     => 'El nombre del procedimiento es obligatorio.',
            'items.*.item_name.max'          => 'El nombre del procedimiento no puede superar 100 caracteres.',
            'items.*.price.required'         => 'El precio del procedimiento es obligatorio.',
            'items.*.price.min'              => 'El precio no puede ser negativo.',
        ];
    }
}