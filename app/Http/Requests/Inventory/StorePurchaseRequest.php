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
        $isExistingProduct = filled($this->input('product_id'));

        return [
            // ── Producto ──────────────────────────────────────────────────────
            // Si viene product_id → se reutiliza el producto (nombre y descripción
            // se heredan del producto existente, no se sobreescriben).
            // Si no viene → se crea el producto con los campos siguientes.
            'product_id'  => ['nullable', 'integer', 'exists:inventory_products,id'],

            'name'        => [Rule::requiredIf(! $isExistingProduct), 'nullable', 'string', 'max:100'],
            'category_id' => [Rule::requiredIf(! $isExistingProduct), 'nullable', 'integer', 'exists:inventory_categories,id'],
            'type'        => [Rule::requiredIf(! $isExistingProduct), 'nullable', Rule::in([
                InventoryProduct::TYPE_INSUMO,
                InventoryProduct::TYPE_EQUIPO,
            ])],
            'description' => ['nullable', 'string', 'max:255'],

            // ── Compra ────────────────────────────────────────────────────────
            // distributor_id es nullable → puede ser una compra independiente sin distribuidor registrado
            // purchase_date NO se recibe → se registra automáticamente con now()
            // total_price NO se recibe → se calcula: quantity * unit_price
            // stock NO se recibe → el service lo maneja sobre el producto
            'distributor_id' => ['nullable', 'integer', 'exists:distributors,id'],
            'quantity'       => ['required', 'integer', 'min:1'],
            'unit_price'     => ['required', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required_if'        => 'El nombre del producto es obligatorio si no seleccionás uno existente.',
            'category_id.required_if' => 'La categoría es obligatoria si no seleccionás un producto existente.',
            'type.required_if'        => 'El tipo (insumo/equipo) es obligatorio si no seleccionás un producto existente.',
        ];
    }
}