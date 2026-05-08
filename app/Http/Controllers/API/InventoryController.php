<?php

namespace App\Http\Controllers\API;

use App\Exceptions\Inventory\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreCategoryRequest;
use App\Http\Requests\Inventory\StorePurchaseRequest;
use App\Http\Requests\Inventory\StoreUsageRequest;
use App\Http\Requests\Inventory\UpdateCategoryRequest;
use App\Http\Responses\ApiResponse;
use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use App\Models\InventoryUsage;
use App\Services\Inventory\InventoryCategoryService;
use App\Services\Inventory\InventoryProductService;     
use App\Services\Inventory\InventoryPurchaseService;
use App\Services\Inventory\InventoryUsageService;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryCategoryService $categoryService,
        private readonly InventoryProductService  $productService,   
        private readonly InventoryPurchaseService $purchaseService,
        private readonly InventoryUsageService    $usageService,
        private readonly StatsService             $statsService,
    ) {}

    // =========================================================================
    // CATEGORÍAS
    // =========================================================================

    public function categoriesIndex(): JsonResponse
    {
        return ApiResponse::success($this->categoryService->all());
    }

    public function categoriesStore(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create(
            adminId: Auth::id(),
            name: $request->validated('name'),
        );

        return ApiResponse::success($category, 201);
    }

    public function categoriesUpdate(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $category = InventoryCategory::findOrFail($id);

        return ApiResponse::success(
            $this->categoryService->update($category, $request->validated('name'))
        );
    }

    // =========================================================================
    // PRODUCTOS
    // =========================================================================

    /**
     * Catálogo completo — diferencia insumos de equipos en los campos expuestos.
     * Soporta filtros opcionales de búsqueda (search) y categoría (category_id).
     */
    public function productsIndex(Request $request): JsonResponse
    {
        $filters = [
            'search'      => $request->query('search'),
            'category_id' => $request->query('category_id'),
        ];

        $catalog = $this->productService->getCatalogForDashboard($filters);

        return ApiResponse::success($catalog);
    }

    /**
     * Resumen de alertas de stock bajo para la campana.
     * Solo insumos — los equipos no tienen stock mínimo.
     */
    public function productsNotifications(): JsonResponse
    {
        return ApiResponse::success($this->productService->getNotificationSummary());
    }

    // =========================================================================
    // COMPRAS
    // =========================================================================

    public function purchasesIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'category_id']);

        return ApiResponse::success($this->purchaseService->listAll($filters));
    }

    public function purchasesStore(StorePurchaseRequest $request): JsonResponse
    {
        $data            = $request->validated();
        $data['user_id'] = Auth::id();

        return ApiResponse::success($this->purchaseService->register($data), 201);
    }

    public function lastPurchase(int $productId): JsonResponse
    {
        $lastPurchase = DB::table('inventory_purchases')
            ->select('unit_price', 'distributor_id', 'purchase_date')
            ->where('product_id', $productId)
            ->orderBy('purchase_date', 'desc')
            ->first();

        return ApiResponse::success($lastPurchase); // null si no existe — el front lo maneja
    }

    // =========================================================================
    // CONSUMOS
    // =========================================================================

    public function usagesIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'category_id']);

        return ApiResponse::success($this->usageService->listAll($filters));
    }

    /**
     * Insumos  → valida stock y descuenta.
     * Equipos  → registra el uso sin tocar stock_actual.
     * Stock insuficiente en insumo → 422.
     */
    public function usagesStore(StoreUsageRequest $request): JsonResponse
    {
        $data   = $request->validated();
        $userId = Auth::id();

        try {
            $usages = $data['status'] === InventoryUsage::STATUS_CON_PACIENTE
                ? $this->usageService->registerClinical(
                    userId:              $userId,
                    medicalEvaluationId: $data['medical_evaluation_id'],
                    items:               $data['items'],
                    reason:              $data['reason'] ?? null,
                )
                : $this->usageService->registerGeneral(
                    userId: $userId,
                    reason: $data['reason'],
                    items:  $data['items'],
                );
        } catch (InsufficientStockException $e) {   
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($usages, 201);
    }

    // =========================================================================
    // SUMMARY
    // =========================================================================

    public function summary(): JsonResponse
    {
        $totalIncome   = $this->statsService->getSummary()['total_income'];
        $totalExpenses = $this->purchaseService->getTotalExpenses();

        return ApiResponse::success([
            'total_income'   => $totalIncome,
            'total_expenses' => $totalExpenses,
            'net_profit'     => $this->purchaseService->getNetProfit($totalIncome),
        ]);
    }

    // =========================================================================
    // REPORTES
    // =========================================================================

    public function spendByCategory(Request $request): JsonResponse
    {
        $month = $request->query('month');
        $year  = $request->query('year');

        $query = DB::table('inventory_purchases as ip')
            ->join('inventory_products as prod', 'ip.product_id', '=', 'prod.id')
            ->join('inventory_categories as ic', 'prod.category_id', '=', 'ic.id')
            ->select(
                'ic.id as category_id',
                'ic.name as category_name',
                DB::raw('SUM(ip.total_price) as amount'),
                DB::raw('COUNT(ip.id) as count')
            );

        if ($month) $query->whereMonth('ip.purchase_date', $month);
        if ($year)  $query->whereYear('ip.purchase_date', $year);

        $items = $query->groupBy('ic.id', 'ic.name')->orderByDesc('amount')->get();

        return ApiResponse::success([
            'period' => $this->buildPeriodLabel($month, $year),
            'total'  => $items->sum('amount'),
            'items'  => $items->values(),
        ]);
    }

    public function spendByDistributor(Request $request): JsonResponse
    {
        $month = $request->query('month');
        $year  = $request->query('year');

        $query = DB::table('inventory_purchases as ip')
            ->leftJoin('distributors as d', 'ip.distributor_id', '=', 'd.id')
            ->select(
                'd.id as distributor_id',
                DB::raw("COALESCE(d.name, 'Sin distribuidor') as distributor_name"),
                DB::raw('SUM(ip.total_price) as amount'),
                DB::raw('COUNT(ip.id) as count')
            );

        if ($month) $query->whereMonth('ip.purchase_date', $month);
        if ($year)  $query->whereYear('ip.purchase_date', $year);

        $items = $query->groupBy('d.id', 'd.name')->orderByDesc('amount')->get();

        return ApiResponse::success([
            'period' => $this->buildPeriodLabel($month, $year),
            'total'  => $items->sum('amount'),
            'items'  => $items->values(),
        ]);
    }

    private function buildPeriodLabel(?string $month, ?string $year): string
    {
        $y = $year ?? now()->year;

        if (!$month) return (string) $y;

        $months = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return ($months[(int) $month] ?? '') . ' ' . $y;
    }

    public function priceHistory(int $productId): JsonResponse
    {
        $history = DB::table('inventory_purchases')
            ->select(
                DB::raw('DATE(purchase_date) as date'),
                'unit_price as price',
                'id as purchase_id'
            )
            ->where('product_id', $productId)
            ->orderBy('purchase_date', 'asc')
            ->limit(12)
            ->get();

        return ApiResponse::success($history);
    }
}