<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use App\Http\Responses\ApiResponse;

class DistributorController extends Controller
{
    public function index()
    {
        try {
            $distributors = Distributor::select('id', 'name', 'cellphone', 'email')->get();

            return ApiResponse::success($distributors);

        } catch (\Throwable $e) {
            return ApiResponse::error('Error al obtener distribuidores', 500, $e->getMessage());
        }
    }
}