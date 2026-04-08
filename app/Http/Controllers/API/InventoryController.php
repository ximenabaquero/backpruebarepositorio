<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use App\Models\InventoryPurchase;
use App\Models\InventoryUsage;
use App\Models\ProcedureItem;
use App\Services\InventoryStockService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryStockService $stockService
    ) {}

    // ══════════════════════════════════════════
    // CATEGORÍAS (solo ADMIN — middleware en rutas)
    // ══════════════════════════════════════════

    public function categoriesIndex(): JsonResponse
    {
        try {
            return ApiResponse::success(
                InventoryCategory::orderBy('name')->get()
            );
        } catch (Throwable $e) {
            return ApiResponse::error('Error al obtener categorías', debug: $e->getMessage());
        }
    }

    public function categoriesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'color' => 'required|string|max:7',
        ]);

        try {
            $category = InventoryCategory::create([
                'user_id' => $request->user()->id,
                'name'    => $data['name'],
                'color'   => $data['color'],
            ]);

            return ApiResponse::success($category, 201);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al crear la categoría', debug: $e->getMessage());
        }
    }

    public function categoriesUpdate(Request $request, int $id): JsonResponse
    {
        $category = InventoryCategory::findOrFail($id);

        $data = $request->validate([
            'name'  => 'sometimes|string|max:100',
            'color' => 'sometimes|string|max:7',
        ]);

        try {
            $category->update($data);

            return ApiResponse::success($category);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al actualizar la categoría', debug: $e->getMessage());
        }
    }

    public function categoriesDestroy(int $id): JsonResponse
    {
        try {
            $category = InventoryCategory::findOrFail($id);

            if ($category->products()->exists() || $category->purchases()->exists()) {
                return ApiResponse::error(
                    'No se puede eliminar una categoría que tiene productos o compras asociadas.',
                    422
                );
            }

            $category->delete();

            return ApiResponse::success(['message' => 'Categoría eliminada correctamente']);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al eliminar la categoría', debug: $e->getMessage());
        }
    }

    // ══════════════════════════════════════════
    // PRODUCTOS (solo ADMIN — middleware en rutas)
    // ══════════════════════════════════════════

    public function productsIndex(): JsonResponse
    {
        try {
            return ApiResponse::success(
                InventoryProduct::with('category')->orderBy('name')->get()
            );
        } catch (Throwable $e) {
            return ApiResponse::error('Error al obtener productos', debug: $e->getMessage());
        }
    }

    public function productsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category_id' => 'required|exists:inventory_categories,id',
            'name'        => 'required|string|max:200',
            'description' => 'nullable|string|max:500',
            'unit_price'  => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
        ]);

        try {
            $product = InventoryProduct::create($data);
            $product->load('category');

            return ApiResponse::success($product, 201);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al crear el producto', debug: $e->getMessage());
        }
    }

    public function productsUpdate(Request $request, int $id): JsonResponse
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

        try {
            $product->update($data);
            $product->load('category');

            return ApiResponse::success($product);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al actualizar el producto', debug: $e->getMessage());
        }
    }

    public function productsDestroy(int $id): JsonResponse
    {
        try {
            $product = InventoryProduct::findOrFail($id);

            if ($product->usages()->exists()) {
                return ApiResponse::error(
                    'No se puede eliminar un producto que tiene consumos registrados.',
                    422
                );
            }

            $product->delete();

            return ApiResponse::success(['message' => 'Producto eliminado correctamente']);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al eliminar el producto', debug: $e->getMessage());
        }
    }

    // ══════════════════════════════════════════
    // COMPRAS (ambos roles)
    // ══════════════════════════════════════════

    public function purchasesIndex(Request $request): JsonResponse
    {
        try {
            $user  = $request->user();
            $query = InventoryPurchase::with([
                'category',
                'product',
                'user:id,first_name,last_name',
            ]);

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

            return ApiResponse::success(
                $query->orderByDesc('purchase_date')->get()
            );
        } catch (Throwable $e) {
            return ApiResponse::error('Error al obtener compras', debug: $e->getMessage());
        }
    }

    public function purchasesStore(Request $request): JsonResponse
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

        try {
            $purchase = $this->stockService->registerPurchase($data);

            return ApiResponse::success($purchase, 201);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al registrar la compra', debug: $e->getMessage());
        }
    }

    public function purchasesUpdate(Request $request, int $id): JsonResponse
    {
        $purchase = InventoryPurchase::findOrFail($id);

        if (! $this->canModify($request->user(), $purchase->user_id)) {
            return ApiResponse::forbidden('No autorizado.');
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

        // Recalcular total si cambia cantidad o precio
        if (isset($data['quantity']) || isset($data['unit_price'])) {
            $data['total_price'] = ($data['quantity'] ?? $purchase->quantity)
                                 * ($data['unit_price'] ?? $purchase->unit_price);
        }

        try {
            // Transacción en el service — stock + update atómicos
            $purchase = $this->stockService->updatePurchase($purchase, $data);
            $purchase->load(['category', 'product', 'user:id,first_name,last_name']);

            return ApiResponse::success($purchase);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al actualizar la compra', debug: $e->getMessage());
        }
    }

    public function purchasesDestroy(Request $request, int $id): JsonResponse
    {
        try {
            $purchase = InventoryPurchase::findOrFail($id);

            if (! $this->canModify($request->user(), $purchase->user_id)) {
                return ApiResponse::forbidden('No autorizado.');
            }

            $purchase->delete();

            return ApiResponse::success(['message' => 'Compra eliminada correctamente']);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al eliminar la compra', debug: $e->getMessage());
        }
    }

    // ══════════════════════════════════════════
    // CONSUMOS (ambos roles)
    // ══════════════════════════════════════════

    public function usagesIndex(Request $request): JsonResponse
    {
        try {
            $user  = $request->user();
            $query = InventoryUsage::with([
                'product.category',
                'user:id,first_name,last_name',
                'medicalEvaluation.patient:id,first_name,last_name',
            ]);

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

            return ApiResponse::success(
                $query->orderByDesc('usage_date')->get()
            );
        } catch (Throwable $e) {
            return ApiResponse::error('Error al obtener consumos', debug: $e->getMessage());
        }
    }

    public function usagesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id'            => 'required|exists:inventory_products,id',
            'quantity'              => 'required|integer|min:1',
            'usage_date'            => 'required|date',
            'status'                => 'nullable|in:con_paciente,sin_paciente',
            'reason'                => 'nullable|string|max:500',
            'medical_evaluation_id' => 'nullable|exists:medical_evaluations,id',
            'notes'                 => 'nullable|string',
        ]);

        $data['user_id'] = $request->user()->id;

        try {
            $usage = $this->stockService->registerUsage($data);

            return ApiResponse::success($usage, 201);
        } catch (\RuntimeException $e) {
            // Stock insuficiente — error de negocio controlado
            return ApiResponse::error($e->getMessage(), 422);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al registrar el consumo', debug: $e->getMessage());
        }
    }

    public function usagesDestroy(Request $request, int $id): JsonResponse
    {
        try {
            $usage = InventoryUsage::findOrFail($id);

            if (! $this->canModify($request->user(), $usage->user_id)) {
                return ApiResponse::forbidden('No autorizado.');
            }

            // Transacción en el service — stock + delete atómicos
            $this->stockService->deleteUsage($usage);

            return ApiResponse::success(['message' => 'Consumo eliminado correctamente']);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al eliminar el consumo', debug: $e->getMessage());
        }
    }

    // ══════════════════════════════════════════
    // RESUMEN (gastos vs ingresos)
    // ══════════════════════════════════════════

    public function summary(Request $request): JsonResponse
    {
        $month = (int) ($request->query('month') ?? Carbon::now()->month);
        $year  = (int) ($request->query('year')  ?? Carbon::now()->year);
        $user  = $request->user();

        // Mes anterior para calcular variaciones
        $prevDate  = Carbon::create($year, $month)->subMonthNoOverflow();
        $prevMonth = $prevDate->month;
        $prevYear  = $prevDate->year;

        try {
            $purchasesQuery = InventoryPurchase::whereMonth('purchase_date', $month)
                ->whereYear('purchase_date', $year);

            $prevPurchasesQuery = InventoryPurchase::whereMonth('purchase_date', $prevMonth)
                ->whereYear('purchase_date', $prevYear);

            if (! $user->isAdmin()) {
                $purchasesQuery->where('inventory_purchases.user_id', $user->id);
                $prevPurchasesQuery->where('inventory_purchases.user_id', $user->id);
            }

            $totalExpenses = (float) $purchasesQuery->sum('total_price');
            $prevExpenses  = (float) $prevPurchasesQuery->sum('total_price');

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
                'month'              => $month,
                'year'               => $year,
                'total_expenses'     => $totalExpenses,
                'expenses_variation' => $this->calcVariation($totalExpenses, $prevExpenses),
                'by_category'        => $byCategory,
            ];

            // Solo ADMIN ve ingresos y margen neto
            if ($user->isAdmin()) {
                $totalIncome = (float) DB::table('procedure_items')
                    ->join('procedures', 'procedure_items.procedure_id', '=', 'procedures.id')
                    ->join('medical_evaluations', 'procedures.medical_evaluation_id', '=', 'medical_evaluations.id')
                    ->where('medical_evaluations.status', 'CONFIRMADO')
                    ->whereMonth('medical_evaluations.confirmed_at', $month)
                    ->whereYear('medical_evaluations.confirmed_at', $year)
                    ->sum('procedure_items.price');

                $prevIncome = (float) DB::table('procedure_items')
                    ->join('procedures', 'procedure_items.procedure_id', '=', 'procedures.id')
                    ->join('medical_evaluations', 'procedures.medical_evaluation_id', '=', 'medical_evaluations.id')
                    ->where('medical_evaluations.status', 'CONFIRMADO')
                    ->whereMonth('medical_evaluations.confirmed_at', $prevMonth)
                    ->whereYear('medical_evaluations.confirmed_at', $prevYear)
                    ->sum('procedure_items.price');

                $netProfit  = $totalIncome - $totalExpenses;
                $prevProfit = $prevIncome - $prevExpenses;

                $response['total_income']     = $totalIncome;
                $response['income_variation']  = $this->calcVariation($totalIncome, $prevIncome);
                $response['net_profit']        = $netProfit;
                $response['profit_variation']  = $this->calcVariation($netProfit, $prevProfit);
            }

            return ApiResponse::success($response);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al obtener el resumen', debug: $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────
    // Privado
    // ─────────────────────────────────────────────

    /**
     * Verifica si el usuario puede modificar un recurso.
     * ADMIN puede modificar cualquiera.
     * REMITENTE solo puede modificar los suyos.
     */
    private function canModify(\App\Models\User $user, int $ownerId): bool
    {
        return $user->isAdmin() || $user->id === $ownerId;
    }

    /**
     * Variación porcentual entre el valor actual y el anterior.
     * Devuelve null si no hay base de comparación.
     */
    private function calcVariation(float $current, float $previous): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }
}