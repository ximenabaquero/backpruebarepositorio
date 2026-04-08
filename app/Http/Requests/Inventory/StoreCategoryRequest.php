<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El middleware 'admin' ya controla el acceso
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', 'unique:inventory_categories,name'],
        ];
    }
}