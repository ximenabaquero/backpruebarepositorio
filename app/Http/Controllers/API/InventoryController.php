<?php

namespace App\Http\Controllers\API;

use App\Exceptions\Inventory\EquipoHasNoStockException;
use App\Exceptions\Inventory\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreCategoryRequest;
use App\Http\Requests\Inventory\StorePurchaseRequest;
use App\Http\Requests\Inventory\StoreUsageRequest;
use App\Http\Requests\Inventory\UpdateCategoryRequest;
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
        return response()->json($this->categoryService->all());
    }

    /** Solo admin — garantizado por middleware en rutas */
    public function categoriesStore(StoreCategoryRequest $request): JsonResponse
    {
        $category = $this->categoryService->create(
            adminId: auth()->id(),
            name: $request->validated('name'),
        );

        return response()->json($category, 201);
    }

    /** Solo admin — garantizado por middleware en rutas */
    public function categoriesUpdate(UpdateCategoryRequest $request, int $id): JsonResponse
    {
        $category = InventoryCategory::findOrFail($id);

        return response()->json(
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

        return response()->json($products);
    }

    // =========================================================================
    // COMPRAS
    // =========================================================================

    /**
     * Ambos roles pueden ver el listado de compras.
     *
     * Query params opcionales (combinables):
     *   ?product_name=faja
     *   ?category_id=2
     *   ?buyer_name=laura
     *   ?distributor_name=medisupply
     */
    public function purchasesIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['product_name', 'category_id', 'buyer_name', 'distributor_name']);

        return response()->json($this->purchaseService->listAll($filters));
    }

    /**
     * Ambos roles pueden registrar compras.
     * user_id se inyecta desde auth() — nunca desde el request.
     */
    public function purchasesStore(StorePurchaseRequest $request): JsonResponse
    {
        $data            = $request->validated();
        $data['user_id'] = auth()->id();

        $purchase = $this->purchaseService->register($data);

        return response()->json($purchase, 201);
    }

    // =========================================================================
    // CONSUMOS
    // =========================================================================

    /**
     * Ambos roles pueden ver el listado de consumos.
     *
     * Query params opcionales (combinables):
     *   ?product_name=faja
     *   ?category_id=2
     *   ?user_name=laura
     *   ?status=con_paciente|sin_paciente
     */
    public function usagesIndex(Request $request): JsonResponse
    {
        $filters = $request->only(['product_name', 'category_id', 'user_name', 'status']);

        return response()->json($this->usageService->listAll($filters));
    }

    /**
     * Registra consumos clínicos (con paciente) o generales (sin paciente).
     *
     * El request valida que:
     *   - Si status = con_paciente → medical_evaluation_id requerido y CONFIRMADO
     *   - Si status = sin_paciente → reason requerido
     *
     * Si la cantidad solicitada supera el stock disponible se devuelve 422
     * con un mensaje claro indicando el producto y los valores en conflicto.
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
        } catch (InsufficientStockException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error'   => 'insufficient_stock',
            ], 422);
        } catch (EquipoHasNoStockException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error'   => 'equipo_no_consumible',
            ], 422);
        }

        return response()->json($usages, 201);
    }

    // =========================================================================
    // SUMMARY — solo admin
    // =========================================================================

    /**
     * El remitente no tiene acceso a ningún dato financiero.
     * Solo el admin puede ver ingresos, gastos y utilidad.
     *
     * Query params opcionales para filtrar gastos por período:
     *   ?from=2025-01-01&to=2025-03-31
     */
    public function summary(Request $request): JsonResponse
    {
        if (! auth()->user()->isAdmin()) {
            return response()->json(['message' => 'No tenés permisos para ver esta información.'], 403);
        }

        $request->validate([
            'from' => ['nullable', 'date'],
            'to'   => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $from = $request->query('from');
        $to   = $request->query('to');

        $totalIncome   = $this->statsService->getSummary()['total_income'];
        $totalExpenses = $this->purchaseService->getTotalExpenses($from, $to);

        return response()->json([
            'total_income'   => $totalIncome,
            'total_expenses' => $totalExpenses,
            'net_profit'     => $this->purchaseService->getNetProfit($totalIncome, $from, $to),
        ]);
    }
}