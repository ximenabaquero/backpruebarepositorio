<?php

namespace App\Http\Controllers\API;

use App\Exceptions\Inventory\EquipoHasNoStockException;
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
use App\Services\Inventory\InventoryPurchaseService;
use App\Services\Inventory\InventoryUsageService;
use App\Services\StatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryCategoryService $categoryService,
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

    /** Solo admin — garantizado por middleware en rutas */
    public function categoriesStore(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create(
            adminId: auth()->id(),
            name: $request->validated('name'),
        );

        return ApiResponse::success($category, 201);
    }

    /** Solo admin — garantizado por middleware en rutas */
    public function categoriesUpdate(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $category = InventoryCategory::findOrFail($id);

        return ApiResponse::success(
            $this->categoryService->update($category, $request->validated('name'))
        );
    }

    // =========================================================================
    // PRODUCTOS (solo lectura — se crean al registrar una compra)
    // =========================================================================

    public function productsIndex(): JsonResponse
    {
        $products = InventoryProduct::with('category')
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return ApiResponse::success($products);
    }

    // =========================================================================
    // COMPRAS
    // =========================================================================

    /**
     * Ambos roles pueden ver el listado de compras.
     *
     * Query params opcionales (combinables):
     *   ?search=texto      — busca en nombre de producto, comprador y distribuidor (OR)
     *   ?category_id=2     — filtra por categoría del producto
     */
    public function purchasesIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'category_id']);

        return ApiResponse::success($this->purchaseService->listAll($filters));
    }

    /**
     * Ambos roles pueden registrar compras.
     * user_id se inyecta desde auth() — nunca desde el request.
     */
    public function purchasesStore(StorePurchaseRequest $request): JsonResponse
    {
        $data            = $request->validated();
        $data['user_id'] = auth()->id();

        return ApiResponse::success($this->purchaseService->register($data), 201);
    }

    // =========================================================================
    // CONSUMOS
    // =========================================================================

    /**
     * Ambos roles pueden ver el listado de consumos.
     *
     * Query params opcionales (combinables):
     *   ?search=texto      — busca en nombre de producto y nombre de quien registró (OR)
     *   ?category_id=2     — filtra por categoría del producto
     */
    public function usagesIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'category_id']);

        return ApiResponse::success($this->usageService->listAll($filters));
    }

    /**
     * Registra consumos clínicos (con paciente) o generales (sin paciente).
     *
     * El request valida que:
     *   - Si status = con_paciente → medical_evaluation_id requerido y CONFIRMADO
     *   - Si status = sin_paciente → reason requerido
     *
     * Si la cantidad supera el stock disponible → 422 con mensaje del producto afectado.
     */
    public function usagesStore(StoreUsageRequest $request): JsonResponse
    {
        $data   = $request->validated();
        $userId = auth()->id();

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
        } catch (InsufficientStockException | EquipoHasNoStockException $e) {
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success($usages, 201);
    }

    // =========================================================================
    // SUMMARY — solo admin (garantizado por middleware en rutas)
    // =========================================================================

    /**
     * Ingresos totales, gastos totales y utilidad neta.
     */
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
}