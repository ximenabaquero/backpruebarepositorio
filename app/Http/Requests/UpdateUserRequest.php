<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Usado tanto para updateAdmin() como para updateRemitente()
 * cuando se incluye el campo 'password' en el request.
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // autorización manejada por middleware en api.php
    }

    public function rules(): array
    {
        $userId = $this->route('id'); // viene del parámetro {id} en la ruta

        return [
            'name'       => "sometimes|string|max:50|unique:users,name,{$userId}",
            'first_name' => 'sometimes|string|max:100',
            'last_name'  => 'sometimes|string|max:100',
            'cellphone'  => 'sometimes|string|max:15',
            'email'      => "sometimes|email|unique:users,email,{$userId}",

            // 'sometimes' — solo valida si el campo está presente
            // Si no se envía password, no se toca — no se puede forzar
            // un cambio de contraseña accidental al editar nombre o email
            'password'   => ['sometimes', 'confirmed', CreateRemitenteRequest::passwordRules()],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique'        => 'Este nombre de usuario ya está en uso.',
            'email.unique'       => 'Este correo ya está registrado.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ];
    }
}