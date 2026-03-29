<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class CreateRemitenteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorización manejada por middleware 'admin' en api.php
    }

    public function rules(): array
    {
        return [
            'name'       => 'required|string|max:50|unique:users,name',
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'cellphone'  => 'required|string|max:15',
            'email'      => 'required|email|unique:users,email',
            'password'   => ['required', 'confirmed', $this->passwordRules()],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'       => 'El nombre de usuario es obligatorio.',
            'name.unique'         => 'Este nombre de usuario ya está en uso.',
            'first_name.required' => 'El nombre es obligatorio.',
            'last_name.required'  => 'El apellido es obligatorio.',
            'cellphone.required'  => 'El celular es obligatorio.',
            'email.required'      => 'El correo electrónico es obligatorio.',
            'email.unique'        => 'Este correo ya está registrado.',
            'password.required'   => 'La contraseña es obligatoria.',
            'password.confirmed'  => 'Las contraseñas no coinciden.',
        ];
    }

    /**
     * Regla de contraseña compartida — usada también en UpdateUserRequest.
     * Centralizada aquí para no duplicar en cada request.
     *
     * Requisitos:
     *   - Mínimo 8 caracteres
     *   - Máximo 64 caracteres (límite bcrypt seguro — varchar(255) en DB es suficiente)
     *   - Al menos una mayúscula
     *   - Al menos una minúscula
     *   - Al menos un número
     *   - Al menos un carácter especial
     */
    public static function passwordRules(): Password
    {
        return Password::min(8)
            ->max(64)
            ->mixedCase()
            ->numbers()
            ->symbols();
    }
}