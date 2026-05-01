<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isExistingProduct     = filled($this->input('product_id'));
        $isExistingDistributor = filled($this->input('distributor_id'));
        $isNewInsumo       = ! $isExistingProduct && $this->input('type') === InventoryProduct::TYPE_INSUMO;

        return [
            // ── Producto ──────────────────────────────────────────────────────
            // Si viene product_id → se reutiliza el existente.
            // Si no → se crea con los campos siguientes.
            'product_id'   => ['nullable', 'integer', 'exists:inventory_products,id'],

            'name'         => [Rule::requiredIf(! $isExistingProduct), 'nullable', 'string', 'max:100'],
            'category_id'  => [Rule::requiredIf(! $isExistingProduct), 'nullable', 'integer', 'exists:inventory_categories,id'],
            'type'         => [Rule::requiredIf(! $isExistingProduct), 'nullable', Rule::in([
                InventoryProduct::TYPE_INSUMO,
                InventoryProduct::TYPE_EQUIPO,
            ])],
            'description'  => ['nullable', 'string', 'max:255'],   // siempre opcional
            'stock_minimo' => [Rule::requiredIf($isNewInsumo), 'nullable', 'integer', 'min:0'],

            // ── Distribuidor ──────────────────────────────────────────────────
            // Caso 1: distributor_id  → existente
            // Caso 2: distributor_name → nuevo (se crea en el service)
            // Caso 3: ambos null       → compra sin distribuidor (válido)
            // Prohibido: mandar id y name a la vez
            'distributor_id'       => ['nullable', 'integer', 'exists:distributors,id'],
            'distributor_name'     => [
                'nullable',
                'string',
                'max:150',
                Rule::prohibitedIf($isExistingDistributor),
            ],
            'distributor_cellphone' => ['nullable', 'string', 'max:20'],
            'distributor_email'     => ['nullable', 'email', 'max:100'],

            // ── Compra ────────────────────────────────────────────────────────
            // purchase_date → now() en el service
            // total_price   → quantity * unit_price en el service
            'quantity'   => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'notes'      => ['nullable', 'string', 'max:500'],      // siempre opcional
        ];
    }

    public function messages(): array
    {
        return [
            'name.required_if'              => 'El nombre del producto es obligatorio si no seleccionas uno existente.',
            'category_id.required_if'       => 'La categoría es obligatoria si no seleccionas un producto existente.',
            'type.required_if'              => 'El tipo (insumo/equipo) es obligatorio si no seleccioas un producto existente.',
            'stock_minimo.required_if'      => 'El stock mínimo es obligatorio al crear un producto nuevo.',
            'distributor_name.prohibited'   => 'No puedes ingresar nombre de distribuidor si ya seleccionaste uno existente.',
        ];
    }
}