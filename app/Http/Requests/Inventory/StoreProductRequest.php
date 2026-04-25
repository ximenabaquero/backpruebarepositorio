<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El middleware 'admin' ya controla el acceso
    }

    public function rules(): array
    {
        return [
            'name'        => ['required', 'string', 'max:100'],
            'category_id' => ['required', 'integer', 'exists:inventory_categories,id'],
            'type'        => ['required', Rule::in(['insumo', 'equipo'])],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
