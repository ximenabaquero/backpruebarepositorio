<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ClinicalImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * ClinicalImageController
 *
 * La autorización (solo ADMIN) se delega al middleware 'admin' en api.php.
 * Este controller no repite esa verificación.
 */
class ClinicalImageController extends Controller
{
    /**
     * Listar todas las imágenes clínicas.
     * Ruta pública — sin auth.
     */
    public function index(): JsonResponse
    {
        try {
            $images = ClinicalImage::select(
                'id',
                'title',
                'before_image',
                'after_image',
                'description',
                'created_at'
            )->get();

            return ApiResponse::success($images);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al obtener las imágenes', debug: $e->getMessage());
        }
    }

    /**
     * Crear una imagen clínica con fotos antes/después.
     * Máximo 10 imágenes — límite del carrusel público.
     * Acceso: admin (middleware en api.php)
     */
    public function store(Request $request): JsonResponse
    {
        // Verificar límite de negocio antes de validar el archivo
        // — evita procesar uploads innecesarios si ya se alcanzó el límite
        $total = ClinicalImage::count();
        if ($total >= 10) {
            return ApiResponse::error(
                'Se alcanzó el límite máximo de 10 imágenes clínicas. Eliminá una existente antes de agregar una nueva.',
                422
            );
        }

        $request->validate([
            'title'        => 'required|string|max:100',
            'description'  => 'nullable|string',
            'before_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
            'after_image'  => 'required|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        try {
            $beforePath = $request->file('before_image')->store('before-after', 'public');
            $afterPath  = $request->file('after_image')->store('before-after', 'public');

            $image = ClinicalImage::create([
                'title'        => $request->title,
                'description'  => $request->description,
                'before_image' => $beforePath,
                'after_image'  => $afterPath,
                'user_id'      => auth()->id(),
            ]);

            return ApiResponse::success($image, 201);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al crear la imagen clínica', debug: $e->getMessage());
        }
    }

    /**
     * Actualizar una imagen clínica.
     * Acceso: admin (middleware en api.php)
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $item = ClinicalImage::findOrFail($id);

        $request->validate([
            'title'        => 'sometimes|string|max:100',
            'description'  => 'nullable|string',
            'before_image' => 'sometimes|image|mimes:jpg,jpeg,png,webp|max:4096',
            'after_image'  => 'sometimes|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        try {
            // Campos de texto — filled() evita sobreescribir con string vacío
            if ($request->filled('title')) {
                $item->title = $request->title;
            }

            if ($request->has('description')) {
                $item->description = $request->description;
            }

            // Reemplazar imagen before — borra la anterior del disco
            if ($request->hasFile('before_image')) {
                Storage::disk('public')->delete($item->before_image);
                $item->before_image = $request->file('before_image')
                    ->store('before-after', 'public');
            }

            // Reemplazar imagen after — borra la anterior del disco
            if ($request->hasFile('after_image')) {
                Storage::disk('public')->delete($item->after_image);
                $item->after_image = $request->file('after_image')
                    ->store('before-after', 'public');
            }

            $item->save();

            return ApiResponse::success([
                'message' => 'Imagen clínica actualizada correctamente',
                'data'    => $item,
            ]);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al actualizar la imagen clínica', debug: $e->getMessage());
        }
    }

    /**
     * Eliminar una imagen clínica y sus archivos del disco.
     * Acceso: admin (middleware en api.php)
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $item = ClinicalImage::findOrFail($id);

            Storage::disk('public')->delete([
                $item->before_image,
                $item->after_image,
            ]);

            $item->delete();

            return ApiResponse::success(['message' => 'Imagen clínica eliminada correctamente']);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al eliminar la imagen clínica', debug: $e->getMessage());
        }
    }
}