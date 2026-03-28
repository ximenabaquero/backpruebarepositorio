<?php

namespace App\Http\Requests;

use App\Models\Patient;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pantalla: "Registrar paciente"
 * Crea paciente + evaluación + procedimiento en una sola operación.
 */
class StoreClinicalRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $fullName = trim((string) $this->input('patient.full_name', ''));

        if ($fullName !== '' && (!$this->has('patient.first_name') || !$this->has('patient.last_name'))) {
            $parts = preg_split('/\s+/', $fullName) ?: [];
            $first = $parts[0] ?? '';
            $last  = implode(' ', array_slice($parts, 1));

            $this->merge([
                'patient' => array_merge($this->input('patient', []), [
                    'first_name' => $this->input('patient.first_name', $first),
                    'last_name'  => $this->input('patient.last_name', $last !== '' ? $last : $first),
                ]),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            // ── Paciente ──────────────────────────────
            'patient'                      => ['required', 'array'],
            'patient.first_name'           => ['required', 'string', 'max:100'],
            'patient.last_name'            => ['required', 'string', 'max:100'],
            'patient.cellphone'            => ['required', 'string', 'max:25'],
            'patient.biological_sex'       => ['required', 'string', 'in:Femenino,Masculino,Otro'],
            'patient.cedula'               => ['required', 'string', 'max:15'],
            'patient.document_type'        => ['required', 'string', 'in:' . implode(',', Patient::DOCUMENT_TYPES)],
            'patient.date_of_birth'        => ['required', 'date', 'before:today'],

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
            'patient.first_name.required'          => 'El nombre del paciente es obligatorio.',
            'patient.last_name.required'           => 'El apellido del paciente es obligatorio.',
            'patient.cellphone.required'           => 'El celular es obligatorio.',
            'patient.biological_sex.required'      => 'El sexo biológico es obligatorio.',
            'patient.biological_sex.in'            => 'El sexo biológico debe ser Femenino, Masculino u Otro.',
            'patient.cedula.required'              => 'La cédula es obligatoria.',
            'patient.document_type.required'       => 'El tipo de documento es obligatorio.',
            'patient.document_type.in'             => 'El tipo de documento no es válido.',
            'patient.date_of_birth.required'       => 'La fecha de nacimiento es obligatoria.',
            'patient.date_of_birth.before'         => 'La fecha de nacimiento debe ser anterior a hoy.',
            'evaluation.weight.required'           => 'El peso es obligatorio.',
            'evaluation.weight.min'                => 'El peso debe ser mayor a 2 kg.',
            'evaluation.weight.max'                => 'El peso no puede superar los 400 kg.',
            'evaluation.height.required'           => 'La altura es obligatoria.',
            'evaluation.height.between'            => 'La altura debe estar entre 1.20 m y 2.50 m.',
            'evaluation.medical_background.required' => 'Los antecedentes médicos son obligatorios.',
            'procedure.notes.required'             => 'Las notas clínicas son obligatorias.',
            'procedure.items.required'             => 'Debe agregar al menos un procedimiento.',
            'procedure.items.min'                  => 'Debe agregar al menos un procedimiento.',
            'procedure.items.*.item_name.required' => 'El nombre del procedimiento es obligatorio.',
            'procedure.items.*.price.required'     => 'El precio del procedimiento es obligatorio.',
            'procedure.items.*.price.min'          => 'El precio no puede ser negativo.',
        ];
    }
}