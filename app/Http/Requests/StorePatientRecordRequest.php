<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pantalla: "Historial del paciente" → botón "Nuevo registro"
 * El paciente ya existe — solo se crea evaluación + procedimiento.
 * El patient_id viene de la URL: /patients/{patient}/clinical-records
 */
class StorePatientRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ── Evaluación médica ─────────────────────
            'evaluation'                        => ['required', 'array'],
            'evaluation.weight'                 => ['required', 'numeric', 'min:2', 'max:400'],
            'evaluation.height'                 => ['required', 'numeric', 'between:1.2,2.5'],
            'evaluation.medical_background'     => ['required', 'string'],

            // ── Procedimiento ─────────────────────────
            'procedure'                         => ['required', 'array'],
            'procedure.notes'                   => ['required', 'string'],
            'procedure.items'                   => ['required', 'array', 'min:1'],
            'procedure.items.*.item_name'       => ['required', 'string', 'max:100'],
            'procedure.items.*.price'           => ['required', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'evaluation.weight.required'             => 'El peso es obligatorio.',
            'evaluation.weight.min'                  => 'El peso debe ser mayor a 2 kg.',
            'evaluation.weight.max'                  => 'El peso no puede superar los 400 kg.',
            'evaluation.height.required'             => 'La altura es obligatoria.',
            'evaluation.height.between'              => 'La altura debe estar entre 1.20 m y 2.50 m.',
            'evaluation.medical_background.required' => 'Los antecedentes médicos son obligatorios.',
            'procedure.notes.required'               => 'Las notas clínicas son obligatorias.',
            'procedure.items.required'               => 'Debe agregar al menos un procedimiento.',
            'procedure.items.min'                    => 'Debe agregar al menos un procedimiento.',
            'procedure.items.*.item_name.required'   => 'El nombre del procedimiento es obligatorio.',
            'procedure.items.*.price.required'       => 'El precio del procedimiento es obligatorio.',
            'procedure.items.*.price.min'            => 'El precio no puede ser negativo.',
        ];
    }
}