<?php

namespace App\Http\Controllers\API;

use App\Helpers\ImageHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\ExamOrder;
use App\Models\MedicalEvaluation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * ExamOrderController
 *
 * Gestiona la orden de exámenes asociada a una valoración médica.
 * Cada valoración tiene como máximo una orden (unique en DB).
 *
 * Rutas en api.php:
 *   GET   /medical-evaluations/{medicalEvaluation}/exam-order  → show()
 *   POST  /medical-evaluations/{medicalEvaluation}/exam-order  → store()
 *   PATCH /exam-orders/{examOrder}                             → update()
 */
class ExamOrderController extends Controller
{
    /**
     * Obtener la orden de exámenes de una valoración.
     * Devuelve null si aún no existe.
     */
    public function show(MedicalEvaluation $medicalEvaluation): JsonResponse
    {
        $user = auth()->user();

        if ($user->isRemitente() && $medicalEvaluation->user_id !== $user->id) {
            return ApiResponse::forbidden();
        }

        return ApiResponse::success($medicalEvaluation->examOrder);
    }

    /**
     * Crear o reemplazar la orden de exámenes.
     * Es idempotente: si ya existe, la reinicia a "pendiente".
     */
    public function store(Request $request, MedicalEvaluation $medicalEvaluation): JsonResponse
    {
        $user = auth()->user();

        if ($user->isRemitente() && $medicalEvaluation->user_id !== $user->id) {
            return ApiResponse::forbidden();
        }

        $request->validate([
            'exams'   => ['required', 'array', 'min:1'],
            'exams.*' => ['required', 'string', 'max:100'],
        ]);

        try {
            $order = ExamOrder::updateOrCreate(
                ['medical_evaluation_id' => $medicalEvaluation->id],
                [
                    'exams'       => $request->exams,
                    'status'      => ExamOrder::STATUS_PENDIENTE,
                    'notes'       => null,
                    'received_at' => null,
                ]
            );

            return ApiResponse::success($order);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al guardar la orden de exámenes', debug: $e->getMessage());
        }
    }

    /**
     * Actualizar el resultado de la orden (apto / no_apto).
     */
    public function update(Request $request, ExamOrder $examOrder): JsonResponse
    {
        $user = auth()->user();

        if ($user->isRemitente() && $examOrder->medicalEvaluation->user_id !== $user->id) {
            return ApiResponse::forbidden();
        }

        $request->validate([
            'status' => ['required', 'in:pendiente,apto,no_apto'],
            'notes'  => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $examOrder->update([
                'status'      => $request->status,
                'notes'       => $request->notes,
                'received_at' => $request->status !== ExamOrder::STATUS_PENDIENTE ? now() : null,
            ]);

            return ApiResponse::success($examOrder->fresh());
        } catch (Throwable $e) {
            return ApiResponse::error('Error al actualizar la orden de exámenes', debug: $e->getMessage());
        }
    }

    /**
     * Subir el archivo de resultados (PDF o imagen del laboratorio).
     */
    public function uploadResult(Request $request, ExamOrder $examOrder): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:10240'],
        ]);

        try {
            // Eliminar archivo anterior si existe
            if ($examOrder->result_file_path) {
                Storage::disk('public')->delete($examOrder->result_file_path);
            }

            $file = $request->file('file');
            $dir  = 'exam-results/' . $examOrder->medical_evaluation_id;

            // Comprimir solo si es imagen; los PDFs se guardan tal cual
            $path = str_contains($file->getMimeType() ?? '', 'image')
                ? ImageHelper::compressAndStore($file, $dir)
                : $file->store($dir, 'public');

            $examOrder->update(['result_file_path' => $path]);

            return ApiResponse::success([
                'result_file_url' => asset('storage/' . $path),
            ]);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al subir el archivo de resultados', debug: $e->getMessage());
        }
    }
}
