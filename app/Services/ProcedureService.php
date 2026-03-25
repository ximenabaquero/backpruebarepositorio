<?php

namespace App\Services;

use App\Models\Procedure;
use Illuminate\Support\Facades\DB;

class ProcedureService
{
    /**
     * Crea un procedimiento con sus items en una transacción.
     * Calcula total_amount sumando los precios de los items.
     */
    public function create(array $data): Procedure
    {
        return DB::transaction(function () use ($data) {
            $totalAmount = $this->calculateTotal($data['items']);

            $procedure = Procedure::create([
                'medical_evaluation_id' => $data['medical_evaluation_id'],
                'brand_slug'            => config('app.brand_slug'),
                'procedure_date'        => \Carbon\Carbon::today(),
                'notes'                 => $data['notes'],
                'total_amount'          => $totalAmount,
            ]);

            $this->syncItems($procedure, $data['items']);

            return $procedure;
        });
    }

    /**
     * Actualiza un procedimiento y reconstruye sus items si se envían.
     * Recalcula total_amount solo cuando cambian los items.
     */
    public function update(Procedure $procedure, array $data): Procedure
    {
        return DB::transaction(function () use ($procedure, $data) {
            if (array_key_exists('notes', $data)) {
                $procedure->notes = $data['notes'];
            }

            if (isset($data['items'])) {
                $procedure->items()->delete();
                $this->syncItems($procedure, $data['items']);
                $procedure->total_amount = $this->calculateTotal($data['items']);
            }

            $procedure->save();

            return $procedure;
        });
    }

    // ─────────────────────────────────────────────
    // Privado
    // ─────────────────────────────────────────────

    /**
     * Suma los precios de todos los items.
     */
    private function calculateTotal(array $items): float
    {
        return (float) collect($items)->sum(fn(array $item) => $item['price']);
    }

    /**
     * Crea los items de un procedimiento en bulk.
     * Más eficiente que un insert por loop.
     */
    private function syncItems(Procedure $procedure, array $items): void
    {
        $procedure->items()->createMany(
            array_map(fn(array $item) => [
                'item_name' => $item['item_name'],
                'price'     => (float) $item['price'],
            ], $items)
        );
    }
}