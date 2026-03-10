<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InventoryProduct;
use Illuminate\Http\Request;

class InventoryProductController extends Controller
{
    // GET /inventory/products
    public function index()
    {
        $products = InventoryProduct::with('category')
            ->orderBy('name')
            ->get();

        return response()->json($products);
    }

    // POST /inventory/products
    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id'  => 'required|exists:inventory_categories,id',
            'name'         => 'required|string|max:200',
            'description'  => 'nullable|string|max:500',
            'unit_price'   => 'required|numeric|min:0',
            'stock'        => 'required|integer|min:0',
            'active'       => 'sometimes|boolean',
        ]);

        $product = InventoryProduct::create($data);
        $product->load('category');

        return response()->json($product, 201);
    }

    // PUT /inventory/products/{product}
    public function update(Request $request, InventoryProduct $product)
    {
        $data = $request->validate([
            'category_id'  => 'sometimes|exists:inventory_categories,id',
            'name'         => 'sometimes|required|string|max:200',
            'description'  => 'nullable|string|max:500',
            'unit_price'   => 'sometimes|required|numeric|min:0',
            'stock'        => 'sometimes|required|integer|min:0',
            'active'       => 'sometimes|boolean',
        ]);

        $product->update($data);
        $product->load('category');

        return response()->json($product);
    }

    // DELETE /inventory/products/{product}
    public function destroy(InventoryProduct $product)
    {
        if ($product->stock > 0) {
            return response()->json([
                'message' => 'No se puede eliminar un producto con stock disponible. Lleva el stock a 0 primero.',
            ], 422);
        }

        $product->delete();

        return response()->json(['message' => 'Producto eliminado.']);
    }
}
