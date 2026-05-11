<?php

namespace App\Http\Controllers\API;

use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\MedicalEvaluation;
use App\Models\PatientPhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * PatientPhotoController
 *
 * Gestiona las fotos clínicas por registro (evaluación), organizadas por etapa.
 *
 * Rutas en api.php:
 *   GET    /medical-evaluations/{evaluation}/photos           → index()
 *   POST   /medical-evaluations/{evaluation}/photos           → store()
 *   DELETE /medical-evaluations/{evaluation}/photos/{photo}   → destroy()
 */
class PatientPhotoController extends Controller
{
    public function index(MedicalEvaluation $medicalEvaluation): JsonResponse
    {
        $user = auth()->user();
        if ($user->isRemitente() && $medicalEvaluation->user_id !== $user->id) {
            return ApiResponse::forbidden();
        }

        $photos = PatientPhoto::where('medical_evaluation_id', $medicalEvaluation->id)
            ->orderBy('taken_at', 'asc')
            ->get()
            ->map(fn($p) => [
                'id'        => $p->id,
                'stage'     => $p->stage,
                'image_url' => asset('storage/' . $p->image_path),
                'notes'     => $p->notes,
                'taken_at'  => $p->taken_at,
            ]);

        return ApiResponse::success($photos);
    }

    public function store(Request $request, MedicalEvaluation $medicalEvaluation): JsonResponse
    {
        $user = auth()->user();
        if ($user->isRemitente() && $medicalEvaluation->user_id !== $user->id) {
            return ApiResponse::forbidden();
        }

        $request->validate([
            'stage' => ['required', 'in:' . implode(',', PatientPhoto::STAGES)],
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            'notes' => ['nullable', 'string', 'max:300'],
        ]);

        try {
            $path = ImageHelper::compressAndStore(
                $request->file('image'),
                'patient-photos/' . $medicalEvaluation->patient_id,
            );

            $photo = PatientPhoto::create([
                'patient_id'            => $medicalEvaluation->patient_id,
                'medical_evaluation_id' => $medicalEvaluation->id,
                'uploaded_by_user_id'   => auth()->id(),
                'stage'                 => $request->stage,
                'image_path'            => $path,
                'notes'                 => $request->notes,
                'taken_at'              => now(),
            ]);

            return ApiResponse::success([
                'id'        => $photo->id,
                'stage'     => $photo->stage,
                'image_url' => asset('storage/' . $photo->image_path),
                'notes'     => $photo->notes,
                'taken_at'  => $photo->taken_at,
            ], 201);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al subir la foto', debug: $e->getMessage());
        }
    }

    public function destroy(MedicalEvaluation $medicalEvaluation, PatientPhoto $photo): JsonResponse
    {
        if ($photo->medical_evaluation_id !== $medicalEvaluation->id) {
            return ApiResponse::forbidden();
        }

        try {
            Storage::disk('public')->delete($photo->image_path);
            $photo->delete();

            return ApiResponse::success(null);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al eliminar la foto', debug: $e->getMessage());
        }
    }
}
