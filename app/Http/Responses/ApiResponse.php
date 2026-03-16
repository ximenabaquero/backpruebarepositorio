<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

/**
 * Centraliza la estructura de todas las respuestas JSON de la API.
 *
 * Beneficio TypeScript: al tener siempre { data, error, message }
 * el frontend puede tipar el wrapper una sola vez y desaparecer los `any`.
 */
final class ApiResponse
{
    /**
     * Respuesta exitosa estándar.
     *
     * @param  mixed  $data
     */
    public static function success(mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'data'    => $data,
            'error'   => null,
            'message' => 'ok',
        ], $status);
    }

    /**
     * Respuesta de error estructurada.
     */
    public static function error(string $message, int $status = 500, mixed $debug = null): JsonResponse
    {
        $body = [
            'data'    => null,
            'error'   => $message,
            'message' => 'error',
        ];

        // Solo incluir debug en entornos no-productivos
        if ($debug !== null && config('app.debug')) {
            $body['debug'] = $debug;
        }

        return response()->json($body, $status);
    }

    /**
     * Respuesta 403 Forbidden estandarizada.
     */
    public static function forbidden(string $message = 'No autorizado'): JsonResponse
    {
        return self::error($message, 403);
    }
}