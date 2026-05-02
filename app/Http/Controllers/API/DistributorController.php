<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreDistributorRequest;
use App\Models\Distributor;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;

class DistributorController extends Controller
{
    /**
     * Obtener lista de distribuidores con soporte para búsqueda por nombre y celular.
     */
    public function index(Request $request)
    {
        try {
            $query = Distributor::select('id', 'name', 'cellphone', 'email');

            if ($request->filled('search')) {
                $searchTerm = $request->query('search');
                $query->where('name', 'LIKE', "%{$searchTerm}%");
                $query->orWhere('cellphone', 'LIKE', "%{$searchTerm}%");
            }
            $distributors = $query->orderBy('name')->get();
            return ApiResponse::success($distributors);
        } catch (\Throwable $e) {
            return ApiResponse::error('Error al obtener distribuidores', 500, $e->getMessage());
        }
    }

    public function store(StoreDistributorRequest $request)
    {
        try {
            $distributor = Distributor::create($request->validated());

            return ApiResponse::success($distributor, 201);

        } catch (\Throwable $e) {
            return ApiResponse::error('Error al crear distribuidor', 500, $e->getMessage());
        }
    }

    public function update(StoreDistributorRequest $request, int $id)
    {
        try {
            $distributor = Distributor::findOrFail($id);
            $distributor->update($request->validated());

            return ApiResponse::success($distributor);

        } catch (\Throwable $e) {
            return ApiResponse::error('Error al actualizar distribuidor', 500, $e->getMessage());
        }
    }

    /*
    public function destroy(int $id)
    {
        try {
            $distributor = Distributor::findOrFail($id);

            if ($distributor->purchases()->exists()) {
                return ApiResponse::error(
                    'No se puede eliminar: este distribuidor tiene compras registradas.',
                    422
                );
            }

            $distributor->delete();

            return ApiResponse::success(null, 200);

        } catch (\Throwable $e) {
            return ApiResponse::error('Error al eliminar distribuidor', 500, $e->getMessage());
        }
    }*/
}