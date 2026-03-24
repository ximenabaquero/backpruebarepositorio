<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use App\Models\InventoryPurchase;
use App\Models\InventoryUsage;
use App\Models\ProcedureItem;
use App\Models\MedicalEvaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InventoryController extends Controller
{
    // ══════════════════════════════════════════
    // CATEGORÍAS (solo ADMIN)
    // ══════════════════════════════════════════

    public function categoriesIndex(Request $request)
    {
        return response()->json(
            InventoryCategory::orderBy('name')->get()
        );
    }

    public function categoriesStore(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'color' => 'required|string|max:7',
        ]);

        $category = InventoryCategory::create([
            'user_id' => $request->user()->id,
            'name'    => $data['name'],
            'color'   => $data['color'],
        ]);

        return response()->json($category, 201);
    }

    public function categoriesUpdate(Request $request, int $id)
    {
        $category = InventoryCategory::findOrFail($id);

        $data = $request->validate([
            'name'  => 'sometimes|string|max:100',
            'color' => 'sometimes|string|max:7',
        ]);

        $category->update($data);

        return response()->json($category);
    }

    public function categoriesDestroy(int $id)
    {
        $category = InventoryCategory::findOrFail($id);

        if ($category->products()->exists() || $category->purchases()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar una categoría que tiene productos o compras asociadas.',
            ], 422);
        }

        $category->delete();

        return response()->json(null, 204);
    }

    // ══════════════════════════════════════════
    // PRODUCTOS (solo ADMIN)
    // ══════════════════════════════════════════

    public function productsIndex()
    {
        return response()->json(
            InventoryProduct::with('category')->orderBy('name')->get()
        );
    }

    public function productsStore(Request $request)
    {
        $data = $request->validate([
            'category_id' => 'required|exists:inventory_categories,id',
            'name'        => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
            'unit_price'  => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
        ]);

        $product = InventoryProduct::create($data);
        $product->load('category');

        return response()->json($product, 201);
    }

    public function productsUpdate(Request $request, int $id)
    {
        $product = InventoryProduct::findOrFail($id);

        $data = $request->validate([
            'category_id' => 'sometimes|exists:inventory_categories,id',
            'name'        => 'sometimes|string|max:200',
            'description' => 'nullable|string|max:500',
            'unit_price'  => 'sometimes|numeric|min:0',
            'stock'       => 'sometimes|integer|min:0',
            'active'      => 'sometimes|boolean',
        ]);

        $product->update($data);
        $product->load('category');

        return response()->json($product);
    }

    public function productsDestroy(int $id)
    {
        $product = InventoryProduct::findOrFail($id);

        if ($product->usages()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar un producto que tiene consumos registrados.',
            ], 422);
        }

        $product->delete();

        return response()->json(null, 204);
    }

    // ══════════════════════════════════════════
    // COMPRAS (ambos roles)
    // ADMIN ve todas, REMITENTE solo las suyas
    // ══════════════════════════════════════════

    public function purchasesIndex(Request $request)
    {
        $user  = $request->user();
        $query = InventoryPurchase::with(['category', 'product', 'user:id,first_name,last_name']);

        // REMITENTE solo ve sus propias compras
        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('month')) {
            $query->whereMonth('purchase_date', $request->month);
        }
        if ($request->filled('year')) {
            $query->whereYear('purchase_date', $request->year);
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        return response()->json(
            $query->orderByDesc('purchase_date')->get()
        );
    }

    public function purchasesStore(Request $request)
    {
        $data = $request->validate([
            'category_id'   => 'required|exists:inventory_categories,id',
            'product_id'    => 'nullable|exists:inventory_products,id',
            'item_name'     => 'required|string|max:200',
            'quantity'      => 'required|integer|min:1',
            'unit_price'    => 'required|numeric|min:0',
            'purchase_date' => 'required|date',
            'notes'         => 'nullable|string',
        ]);

        $data['user_id']     = $request->user()->id;
        $data['total_price'] = $data['quantity'] * $data['unit_price'];

        $purchase = DB::transaction(function () use ($data) {
            // Si la compra está vinculada a un producto, actualizar el stock
            if (! empty($data['product_id'])) {
                InventoryProduct::where('id', $data['product_id'])
                    ->increment('stock', $data['quantity']);
            }

            $purchase = InventoryPurchase::create($data);
            $purchase->load(['category', 'product', 'user:id,first_name,last_name']);

            return $purchase;
        });

        return response()->json($purchase, 201);
    }

    public function purchasesUpdate(Request $request, int $id)
    {
        $purchase = InventoryPurchase::findOrFail($id);
        $user     = $request->user();

        // REMITENTE solo puede editar sus propias compras
        if (! $user->isAdmin() && $purchase->user_id !== $user->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $data = $request->validate([
            'category_id'   => 'sometimes|exists:inventory_categories,id',
            'product_id'    => 'nullable|exists:inventory_products,id',
            'item_name'     => 'sometimes|string|max:200',
            'quantity'      => 'sometimes|integer|min:1',
            'unit_price'    => 'sometimes|numeric|min:0',
            'purchase_date' => 'sometimes|date',
            'notes'         => 'nullable|string',
        ]);

        if (isset($data['quantity']) || isset($data['unit_price'])) {
            $qty   = $data['quantity']   ?? $purchase->quantity;
            $price = $data['unit_price'] ?? $purchase->unit_price;
            $data['total_price'] = $qty * $price;
        }

        $purchase->update($data);
        $purchase->load(['category', 'product', 'user:id,first_name,last_name']);

        return response()->json($purchase);
    }

    public function purchasesDestroy(Request $request, int $id)
    {
        $purchase = InventoryPurchase::findOrFail($id);
        $user     = $request->user();

        if (! $user->isAdmin() && $purchase->user_id !== $user->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $purchase->delete();

        return response()->json(null, 204);
    }

    // ══════════════════════════════════════════
    // CONSUMOS (ambos roles)
    // ADMIN ve todos, REMITENTE solo los suyos
    // ══════════════════════════════════════════

    public function usagesIndex(Request $request)
    {
        $user  = $request->user();
        $query = InventoryUsage::with(['product.category', 'user:id,first_name,last_name']);

        if (! $user->isAdmin()) {
            $query->where('user_id', $user->id);
        }

        if ($request->filled('month')) {
            $query->whereMonth('usage_date', $request->month);
        }
        if ($request->filled('year')) {
            $query->whereYear('usage_date', $request->year);
        }
        if ($request->filled('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        return response()->json(
            $query->orderByDesc('usage_date')->get()
        );
    }

    public function usagesStore(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:inventory_products,id',
            'quantity'   => 'required|integer|min:1',
            'usage_date' => 'required|date',
            'notes'      => 'nullable|string',
        ]);

        $data['user_id'] = $request->user()->id;

        // Validar stock suficiente antes de registrar
        $product = InventoryProduct::findOrFail($data['product_id']);
        if ($product->stock < $data['quantity']) {
            return response()->json([
                'message' => "Stock insuficiente. Disponible: {$product->stock} unidades.",
            ], 422);
        }

        $usage = DB::transaction(function () use ($data, $product) {
            $product->decrement('stock', $data['quantity']);

            $usage = InventoryUsage::create($data);
            $usage->load(['product.category', 'user:id,first_name,last_name']);

            return $usage;
        });

        return response()->json($usage, 201);
    }

    public function usagesDestroy(Request $request, int $id)
    {
        $usage = InventoryUsage::findOrFail($id);
        $user  = $request->user();

        if (! $user->isAdmin() && $usage->user_id !== $user->id) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        // Devolver al stock al eliminar el consumo
        InventoryProduct::where('id', $usage->product_id)
            ->increment('stock', $usage->quantity);

        $usage->delete();

        return response()->json(null, 204);
    }

    // ══════════════════════════════════════════
    // RESUMEN (gastos vs ingresos)
    // ADMIN: ve gastos totales + ingresos + margen
    // REMITENTE: ve solo sus gastos
    // ══════════════════════════════════════════

    public function summary(Request $request)
    {
        $month = (int) ($request->query('month') ?? Carbon::now()->month);
        $year  = (int) ($request->query('year')  ?? Carbon::now()->year);
        $user  = $request->user();

        // Gastos del período
        $purchasesQuery = InventoryPurchase::whereMonth('purchase_date', $month)
            ->whereYear('purchase_date', $year);

        if (! $user->isAdmin()) {
            $purchasesQuery->where('inventory_purchases.user_id', $user->id);
        }

        $totalExpenses = (float) $purchasesQuery->sum('total_price');

        // Gastos agrupados por categoría
        $byCategory = $purchasesQuery->clone()
            ->join('inventory_categories', 'inventory_purchases.category_id', '=', 'inventory_categories.id')
            ->select(
                'inventory_categories.name as category',
                'inventory_categories.color',
                DB::raw('SUM(inventory_purchases.total_price) as total')
            )
            ->groupBy('inventory_categories.id', 'inventory_categories.name', 'inventory_categories.color')
            ->orderByDesc('total')
            ->get()
            ->map(fn($row) => [
                'category' => $row->category,
                'color'    => $row->color,
                'total'    => (float) $row->total,
            ]);

        $response = [
            'month'          => $month,
            'year'           => $year,
            'total_expenses' => $totalExpenses,
            'by_category'    => $byCategory,
        ];

        // Solo ADMIN ve ingresos y margen neto
        if ($user->isAdmin()) {
            $totalIncome = (float) ProcedureItem::whereHas('procedure.medicalEvaluation', function ($q) use ($month, $year) {
                $q->where('status', 'CONFIRMADO')
                  ->whereMonth('confirmed_at', $month)
                  ->whereYear('confirmed_at', $year);
            })->sum('price');

            $response['total_income'] = $totalIncome;
            $response['net_profit']   = $totalIncome - $totalExpenses;
        }

        return response()->json($response);
    }
}
