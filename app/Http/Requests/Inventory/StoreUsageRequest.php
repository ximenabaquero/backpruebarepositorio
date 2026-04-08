<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryUsage;
use App\Models\MedicalEvaluation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUsageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isClinical = $this->input('status') === InventoryUsage::STATUS_CON_PACIENTE;

        return [
            'status' => ['required', Rule::in([
                InventoryUsage::STATUS_CON_PACIENTE,
                InventoryUsage::STATUS_SIN_PACIENTE,
            ])],

            // Obligatorio solo si es consumo clínico
            'medical_evaluation_id' => [
                Rule::requiredIf($isClinical),
                'nullable',
                'integer',
                'exists:medical_evaluations,id',
            ],

            // Obligatorio solo si es consumo general (sin paciente)
            'reason' => [
                Rule::requiredIf(! $isClinical),
                'nullable',
                'string',
                'max:300',
            ],

            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:inventory_products,id'],
            'items.*.quantity'   => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Validación adicional: si el consumo es clínico, el registro médico
     * debe estar en estado CONFIRMADO (el paciente ya pagó).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($this->input('status') !== InventoryUsage::STATUS_CON_PACIENTE) {
                return;
            }

            $evaluationId = $this->input('medical_evaluation_id');

            if (! $evaluationId) {
                return; // ya falla la regla required_if arriba
            }

            $evaluation = MedicalEvaluation::find($evaluationId);

            if (! $evaluation || $evaluation->status !== MedicalEvaluation::STATUS_CONFIRMADO) {
                $v->errors()->add(
                    'medical_evaluation_id',
                    'Solo se pueden registrar consumos en registros clínicos confirmados (paciente con pago).'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'medical_evaluation_id.required_if' => 'El registro clínico es obligatorio para consumos con paciente.',
            'reason.required_if'                => 'El motivo es obligatorio para consumos sin paciente.',
            'items.min'                         => 'Debés incluir al menos un producto.',
            'items.*.product_id.required'       => 'Cada ítem debe tener un producto válido.',
            'items.*.quantity.min'              => 'La cantidad mínima por producto es 1.',
        ];
    }
}