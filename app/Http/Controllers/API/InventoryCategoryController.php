<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use Illuminate\Http\Request;

class InventoryCategoryController extends Controller
{
    // GET /inventory/categories — solo admin (middleware en rutas)
    public function index()
    {
        return response()->json(
            InventoryCategory::orderBy('name')->get()
        );
    }

    // POST /inventory/categories
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'color' => 'sometimes|string|size:7|regex:/^#[0-9a-fA-F]{6}$/',
        ]);

        $category = InventoryCategory::create([
            'user_id' => auth()->id(),
            'name'    => $data['name'],
            'color'   => $data['color'] ?? '#6366f1',
        ]);

        return response()->json($category, 201);
    }

    // PUT /inventory/categories/{category}
    public function update(Request $request, InventoryCategory $category)
    {
        $data = $request->validate([
            'name'  => 'sometimes|required|string|max:100',
            'color' => 'sometimes|string|size:7|regex:/^#[0-9a-fA-F]{6}$/',
        ]);

        $category->update($data);

        return response()->json($category);
    }

    // DELETE /inventory/categories/{category}
    public function destroy(InventoryCategory $category)
    {
        if ($category->purchases()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar una categoría que tiene compras registradas.'
            ], 409);
        }

        $category->delete();

        return response()->json(['message' => 'Categoría eliminada.']);
    }
}
