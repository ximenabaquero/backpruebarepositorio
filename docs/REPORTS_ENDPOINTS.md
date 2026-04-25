# Backend Implementation Guide - Reports Endpoints

## Overview
These endpoints need to be added to the Laravel backend to support the Reports tab in the inventory system.

## Base Route
All endpoints should be under: `/api/v1/inventory/reports/`

---

## 1. Spend by Category

**Endpoint:** `GET /api/v1/inventory/reports/spend-by-category`

**Query Parameters:**
- `month` (optional): Filter by month (1-12)
- `year` (optional): Filter by year (e.g., 2026)

**Response Format:**
```json
{
  "data": [
    {
      "category_id": 1,
      "category_name": "Insumos Médicos",
      "amount": 1250000,
      "count": 15
    },
    {
      "category_id": 2,
      "category_name": "Material Quirúrgico",
      "amount": 850000,
      "count": 8
    }
  ]
}
```

**SQL Logic:**
```sql
SELECT 
    ic.id as category_id,
    ic.name as category_name,
    SUM(ip.total_price) as amount,
    COUNT(ip.id) as count
FROM inventory_purchases ip
JOIN inventory_products prod ON ip.product_id = prod.id
JOIN inventory_categories ic ON prod.category_id = ic.id
WHERE 
    (MONTH(ip.purchase_date) = ? OR ? IS NULL)
    AND (YEAR(ip.purchase_date) = ? OR ? IS NULL)
GROUP BY ic.id, ic.name
ORDER BY amount DESC
```

**Laravel Implementation Example:**
```php
public function spendByCategory(Request $request)
{
    $month = $request->query('month');
    $year = $request->query('year');

    $query = DB::table('inventory_purchases as ip')
        ->join('inventory_products as prod', 'ip.product_id', '=', 'prod.id')
        ->join('inventory_categories as ic', 'prod.category_id', '=', 'ic.id')
        ->select(
            'ic.id as category_id',
            'ic.name as category_name',
            DB::raw('SUM(ip.total_price) as amount'),
            DB::raw('COUNT(ip.id) as count')
        );

    if ($month) {
        $query->whereMonth('ip.purchase_date', $month);
    }
    if ($year) {
        $query->whereYear('ip.purchase_date', $year);
    }

    $results = $query
        ->groupBy('ic.id', 'ic.name')
        ->orderByDesc('amount')
        ->get();

    return response()->json(['data' => $results]);
}
```

---

## 2. Spend by Distributor

**Endpoint:** `GET /api/v1/inventory/reports/spend-by-distributor`

**Query Parameters:**
- `month` (optional): Filter by month (1-12)
- `year` (optional): Filter by year (e.g., 2026)

**Response Format:**
```json
{
  "data": [
    {
      "distributor_id": 3,
      "distributor_name": "Distribuidora Médica ABC",
      "amount": 2500000,
      "count": 25
    },
    {
      "distributor_id": null,
      "distributor_name": "Sin distribuidor",
      "amount": 450000,
      "count": 5
    }
  ]
}
```

**SQL Logic:**
```sql
SELECT 
    d.id as distributor_id,
    COALESCE(d.name, 'Sin distribuidor') as distributor_name,
    SUM(ip.total_price) as amount,
    COUNT(ip.id) as count
FROM inventory_purchases ip
LEFT JOIN distributors d ON ip.distributor_id = d.id
WHERE 
    (MONTH(ip.purchase_date) = ? OR ? IS NULL)
    AND (YEAR(ip.purchase_date) = ? OR ? IS NULL)
GROUP BY d.id, d.name
ORDER BY amount DESC
```

**Laravel Implementation Example:**
```php
public function spendByDistributor(Request $request)
{
    $month = $request->query('month');
    $year = $request->query('year');

    $query = DB::table('inventory_purchases as ip')
        ->leftJoin('distributors as d', 'ip.distributor_id', '=', 'd.id')
        ->select(
            'd.id as distributor_id',
            DB::raw("COALESCE(d.name, 'Sin distribuidor') as distributor_name"),
            DB::raw('SUM(ip.total_price) as amount'),
            DB::raw('COUNT(ip.id) as count')
        );

    if ($month) {
        $query->whereMonth('ip.purchase_date', $month);
    }
    if ($year) {
        $query->whereYear('ip.purchase_date', $year);
    }

    $results = $query
        ->groupBy('d.id', 'd.name')
        ->orderByDesc('amount')
        ->get();

    return response()->json(['data' => $results]);
}
```

---

## 3. Price History

**Endpoint:** `GET /api/v1/inventory/reports/price-history/{productId}`

**Path Parameters:**
- `productId`: ID of the product

**Response Format:**
```json
{
  "data": [
    {
      "date": "2026-01-15",
      "price": 12500,
      "purchase_id": 45
    },
    {
      "date": "2026-02-20",
      "price": 13000,
      "purchase_id": 67
    },
    {
      "date": "2026-03-10",
      "price": 12800,
      "purchase_id": 89
    }
  ]
}
```

**SQL Logic:**
```sql
SELECT 
    DATE(purchase_date) as date,
    unit_price as price,
    id as purchase_id
FROM inventory_purchases
WHERE product_id = ?
ORDER BY purchase_date ASC
LIMIT 12  -- Limit to last 12 purchases for performance
```

**Laravel Implementation Example:**
```php
public function priceHistory($productId)
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

    return response()->json(['data' => $history]);
}
```

---

## Routes Registration

Add to `routes/api.php`:

```php
Route::middleware(['auth:sanctum'])->prefix('v1/inventory')->group(function () {
    // ... existing routes ...

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('/spend-by-category', [InventoryController::class, 'spendByCategory']);
        Route::get('/spend-by-distributor', [InventoryController::class, 'spendByDistributor']);
        Route::get('/price-history/{productId}', [InventoryController::class, 'priceHistory']);
    });
});
```

---

## Controller Updates

Add these methods to `App\Http\Controllers\API\InventoryController.php`:

```php
use Illuminate\Support\Facades\DB;

public function spendByCategory(Request $request)
{
    // Implementation above
}

public function spendByDistributor(Request $request)
{
    // Implementation above
}

public function priceHistory($productId)
{
    // Implementation above
}
```

---

## Testing the Endpoints

### Using cURL:

```bash
# Spend by Category
curl -X GET "http://localhost:8000/api/v1/inventory/reports/spend-by-category?month=4&year=2026" \
  -H "Accept: application/json" \
  --cookie "laravel_session=..."

# Spend by Distributor
curl -X GET "http://localhost:8000/api/v1/inventory/reports/spend-by-distributor?month=4&year=2026" \
  -H "Accept: application/json" \
  --cookie "laravel_session=..."

# Price History
curl -X GET "http://localhost:8000/api/v1/inventory/reports/price-history/1" \
  -H "Accept: application/json" \
  --cookie "laravel_session=..."
```

---

## Performance Considerations

1. **Indexes**: Ensure these indexes exist:
   ```sql
   CREATE INDEX idx_purchases_date ON inventory_purchases(purchase_date);
   CREATE INDEX idx_purchases_product ON inventory_purchases(product_id);
   CREATE INDEX idx_purchases_distributor ON inventory_purchases(distributor_id);
   ```

2. **Caching**: Consider caching reports for frequently accessed date ranges:
   ```php
   Cache::remember("spend_by_category_{$month}_{$year}", 3600, function() {
       // query logic
   });
   ```

3. **Pagination**: If dealing with large datasets, consider paginating the results

---

## Error Handling

All endpoints should return proper error responses:

```php
try {
    // ... query logic ...
} catch (\Exception $e) {
    Log::error('Error in reports endpoint: ' . $e->getMessage());
    return response()->json([
        'message' => 'Error al generar reporte',
        'error' => $e->getMessage()
    ], 500);
}
```

---

## Authorization

All reports endpoints should be protected with authentication. Consider adding role-based access:

```php
Route::middleware(['auth:sanctum', 'role:ADMIN,USER'])->group(function () {
    // reports routes
});
```

Or check permissions in the controller:

```php
public function spendByCategory(Request $request)
{
    // Optional: restrict to admins only
    if ($request->user()->role !== 'ADMIN') {
        return response()->json(['message' => 'No autorizado'], 403);
    }
    
    // ... rest of implementation
}
```
