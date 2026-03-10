<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InventoryUsage;
use App\Models\InventoryProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryUsageController extends Controller
{
    // GET /inventory/usages
    public function index(Request $request)
    {
        $user  = auth()->user();
        $query = InventoryUsage::with(['product.category', 'user']);

        if ($user->isRemitente()) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('month') && $request->filled('year')) {
            $query->whereMonth('usage_date', $request->month)
                  ->whereYear('usage_date', $request->year);
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        return response()->json(
            $query->orderByDesc('usage_date')->get()
        );
    }

    // POST /inventory/usages
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:inventory_products,id',
            'quantity'   => 'required|integer|min:1',
            'usage_date' => 'required|date',
            'notes'      => 'nullable|string|max:1000',
        ]);

        $product = InventoryProduct::findOrFail($data['product_id']);

        if ($product->stock < $data['quantity']) {
            return response()->json([
                'message' => "Stock insuficiente. Disponible: {$product->stock} unidades.",
            ], 422);
        }

        $usage = DB::transaction(function () use ($data, $product) {
            $product->decrement('stock', $data['quantity']);
            $data['user_id'] = auth()->id();
            return InventoryUsage::create($data);
        });

        $usage->load(['product.category', 'user']);

        return response()->json($usage, 201);
    }

    // DELETE /inventory/usages/{usage}
    public function destroy(InventoryUsage $usage)
    {
        $user = auth()->user();

        if ($user->isRemitente() && $usage->user_id !== $user->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        DB::transaction(function () use ($usage) {
            $usage->product()->increment('stock', $usage->quantity);
            $usage->delete();
        });

        return response()->json(['message' => 'Consumo eliminado y stock revertido.']);
    }
}
