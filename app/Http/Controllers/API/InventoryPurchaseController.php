<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InventoryProduct;
use App\Models\InventoryPurchase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryPurchaseController extends Controller
{
    // GET /inventory/purchases
    // Admin ve todos; remitente solo los suyos
    public function index(Request $request)
    {
        $user  = auth()->user();
        $query = InventoryPurchase::with(['category', 'user', 'product']);

        if ($user->isRemitente()) {
            $query->where('user_id', $user->id);
        }

        // Filtros opcionales
        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('purchase_date', $request->month)
                  ->whereYear('purchase_date', $request->year);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        return response()->json(
            $query->orderByDesc('purchase_date')->get()
        );
    }

    // POST /inventory/purchases
    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id'   => 'required|exists:inventory_categories,id',
            'product_id'    => 'nullable|exists:inventory_products,id',
            'item_name'     => 'required|string|max:200',
            'quantity'      => 'required|integer|min:1',
            'unit_price'    => 'required|numeric|min:0',
            'purchase_date' => 'required|date',
            'notes'         => 'nullable|string|max:1000',
        ]);

        $data['user_id']     = auth()->id();
        $data['total_price'] = $data['quantity'] * $data['unit_price'];

        $purchase = DB::transaction(function () use ($data) {
            if (!empty($data['product_id'])) {
                InventoryProduct::where('id', $data['product_id'])
                    ->increment('stock', $data['quantity']);
            }
            return InventoryPurchase::create($data);
        });

        $purchase->load(['category', 'user', 'product']);

        return response()->json($purchase, 201);
    }

    // PUT /inventory/purchases/{purchase}
    public function update(Request $request, InventoryPurchase $purchase)
    {
        $user = auth()->user();

        if ($user->isRemitente() && $purchase->user_id !== $user->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $data = $request->validate([
            'category_id'   => 'sometimes|exists:inventory_categories,id',
            'product_id'    => 'nullable|exists:inventory_products,id',
            'item_name'     => 'sometimes|required|string|max:200',
            'quantity'      => 'sometimes|required|integer|min:1',
            'unit_price'    => 'sometimes|required|numeric|min:0',
            'purchase_date' => 'sometimes|required|date',
            'notes'         => 'nullable|string|max:1000',
        ]);

        // Recalcular total si cambian cantidad o precio
        $newQty    = $data['quantity']    ?? $purchase->quantity;
        $newPrice  = $data['unit_price']  ?? $purchase->unit_price;
        $data['total_price'] = $newQty * $newPrice;

        DB::transaction(function () use ($data, $purchase, $newQty) {
            $oldProductId  = $purchase->product_id;
            $newProductId  = array_key_exists('product_id', $data) ? $data['product_id'] : $oldProductId;
            $oldQty        = $purchase->quantity;

            // Revert old product stock if product changed or quantity changed
            if ($oldProductId && ($oldProductId !== $newProductId || $oldQty !== $newQty)) {
                InventoryProduct::where('id', $oldProductId)->decrement('stock', $oldQty);
            }

            // Apply new product stock
            if ($newProductId && ($oldProductId !== $newProductId || $oldQty !== $newQty)) {
                InventoryProduct::where('id', $newProductId)->increment('stock', $newQty);
            }

            $purchase->update($data);
        });

        $purchase->load(['category', 'user', 'product']);

        return response()->json($purchase);
    }

    // DELETE /inventory/purchases/{purchase}
    public function destroy(InventoryPurchase $purchase)
    {
        $user = auth()->user();

        if ($user->isRemitente() && $purchase->user_id !== $user->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        DB::transaction(function () use ($purchase) {
            // Revert stock if purchase was linked to a product
            if ($purchase->product_id) {
                InventoryProduct::where('id', $purchase->product_id)
                    ->decrement('stock', $purchase->quantity);
            }
            $purchase->delete();
        });

        return response()->json(['message' => 'Compra eliminada.']);
    }

    // GET /inventory/summary
    // Admin: resumen global (ingresos vs gastos)
    // Remitente: solo sus gastos del mes
    public function summary(Request $request)
    {
        $user  = auth()->user();
        $month = $request->get('month', now()->month);
        $year  = $request->get('year', now()->year);

        // Gastos del periodo
        $expensesQuery = InventoryPurchase::whereMonth('purchase_date', $month)
                                          ->whereYear('purchase_date', $year);

        if ($user->isRemitente()) {
            $expensesQuery->where('user_id', $user->id);
        }

        $totalExpenses = (float) $expensesQuery->sum('total_price');

        // Gastos por categoria
        $byCategory = (clone $expensesQuery)
            ->join('inventory_categories', 'inventory_purchases.category_id', '=', 'inventory_categories.id')
            ->selectRaw('inventory_categories.name as category, inventory_categories.color, SUM(inventory_purchases.total_price) as total')
            ->groupBy('inventory_categories.id', 'inventory_categories.name', 'inventory_categories.color')
            ->orderByDesc('total')
            ->get();

        $result = [
            'month'          => (int) $month,
            'year'           => (int) $year,
            'total_expenses' => $totalExpenses,
            'by_category'    => $byCategory,
        ];

        // El admin también ve ingresos y ganancia neta
        if ($user->isAdmin()) {
            $totalIncome = (float) DB::table('procedures')
                ->whereMonth('procedure_date', $month)
                ->whereYear('procedure_date', $year)
                ->sum('total_amount');

            $result['total_income']  = $totalIncome;
            $result['net_profit']    = $totalIncome - $totalExpenses;
        }

        return response()->json($result);
    }
}
