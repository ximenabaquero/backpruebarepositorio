<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProcedureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes'             => ['sometimes', 'nullable', 'string'],
            // Si se envían items, reemplaza todos los existentes
            'items'             => ['sometimes', 'array', 'min:1'],
            'items.*.item_name' => ['required_with:items', 'string', 'max:100'],
            'items.*.price'     => ['required_with:items', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.min'                       => 'Debe agregar al menos un procedimiento.',
            'items.*.item_name.required_with'  => 'El nombre del procedimiento es obligatorio.',
            'items.*.item_name.max'            => 'El nombre del procedimiento no puede superar 100 caracteres.',
            'items.*.price.required_with'      => 'El precio del procedimiento es obligatorio.',
            'items.*.price.min'                => 'El precio no puede ser negativo.',
        ];
    }
}