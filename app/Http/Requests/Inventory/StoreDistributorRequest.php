<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class StoreDistributorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // El middleware 'admin' ya controla el acceso
    }

    public function rules(): array
    {
        $distributorId = $this->route('id');

        return [
            'name'      => ['required', 'string', 'max:100', 'unique:distributors,name,' . $distributorId],
            'cellphone' => ['nullable', 'string', 'max:25'],
            'email'     => ['nullable', 'email', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del distribuidor es obligatorio.',
            'name.unique'   => 'Ya existe un distribuidor con ese nombre.',
            'email.email'   => 'El correo electrónico no tiene un formato válido.',
        ];
    }
}
