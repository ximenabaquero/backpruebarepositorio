<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class UserController extends Controller
{
    // ─────────────────────────────────────────────
    // ADMIN
    // ─────────────────────────────────────────────

    /**
     * Editar campos del admin autenticado.
     * Solo puede modificar su propia cuenta.
     */
    public function updateAdmin(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! $user->isAdmin()) {
            return ApiResponse::error('Solo se puede modificar un administrador', 400);
        }

        if (auth()->id() !== $user->id) {
            return ApiResponse::forbidden('Solo puedes modificar tu propia cuenta.');
        }

        $validated = $request->validate([
            'name'       => 'sometimes|string|max:50|unique:users,name,' . $id,
            'first_name' => 'sometimes|string|max:100',
            'last_name'  => 'sometimes|string|max:100',
            'email'      => 'sometimes|email|unique:users,email,' . $id,
            'password'   => 'sometimes|min:6',
        ]);

        try {
            $user->update($validated);

            return ApiResponse::success([
                'message' => 'Administrador actualizado correctamente',
                'user'    => $user,
            ]);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al actualizar el administrador', debug: $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────
    // REMITENTES
    // ─────────────────────────────────────────────

    /**
     * Listar todos los remitentes.
     */
    public function listRemitentes(): JsonResponse
    {
        try {
            $remitentes = User::where('role', User::ROLE_REMITENTE)
                ->orderByDesc('created_at')
                ->get();

            return ApiResponse::success($remitentes);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al listar remitentes', debug: $e->getMessage());
        }
    }

    /**
     * Crear un nuevo remitente.
     */
    public function createRemitente(Request $request): JsonResponse
    {
        $request->validate([
            'name'       => 'required|string|max:50|unique:users,name',
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'cellphone'  => 'required|string|max:15',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|min:6',
        ]);

        try {
            $user = User::create([
                'name'       => $request->name,
                'first_name' => $request->first_name,
                'last_name'  => $request->last_name,
                'cellphone'  => $request->cellphone,
                'brand_name' => config('app.brand_name'),
                'brand_slug' => config('app.brand_slug'),
                'email'      => $request->email,
                'password'   => $request->password,
                'role'       => User::ROLE_REMITENTE,
                'status'     => User::STATUS_ACTIVE,
            ]);

            return ApiResponse::success([
                'message' => 'Remitente creado correctamente',
                'user'    => $user,
            ], 201);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al crear el remitente', debug: $e->getMessage());
        }
    }

    /**
     * Actualizar datos de un remitente.
     */
    public function updateRemitente(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! $user->isRemitente()) {
            return ApiResponse::error('Solo se pueden modificar remitentes', 400);
        }

        if ($user->status === User::STATUS_FIRED) {
            return ApiResponse::error(
                'No se puede modificar un remitente despedido. Debe activarse primero.',
                400
            );
        }

        $validated = $request->validate([
            'name'       => 'sometimes|string|max:50|unique:users,name,' . $id,
            'first_name' => 'sometimes|string|max:100',
            'last_name'  => 'sometimes|string|max:100',
            'cellphone'  => 'sometimes|string|max:15',
            'email'      => 'sometimes|email|unique:users,email,' . $id,
            'password'   => 'sometimes|min:6',
        ]);

        try {
            $user->update($validated);

            return ApiResponse::success([
                'message' => 'Remitente actualizado correctamente',
                'user'    => $user,
            ]);
        } catch (Throwable $e) {
            return ApiResponse::error('Error al actualizar el remitente', debug: $e->getMessage());
        }
    }

    /**
     * Activar un remitente.
     */
    public function activarRemitente(int $id): JsonResponse
    {
        return $this->changeRemitenteStatus($id, User::STATUS_ACTIVE, 'activado');
    }

    /**
     * Inactivar un remitente (pausa temporal).
     */
    public function inactivarRemitente(int $id): JsonResponse
    {
        return $this->changeRemitenteStatus($id, User::STATUS_INACTIVE, 'inactivado');
    }

    /**
     * Marcar un remitente como despedido.
     */
    public function despedirRemitente(int $id): JsonResponse
    {
        return $this->changeRemitenteStatus($id, User::STATUS_FIRED, 'marcado como despedido');
    }

    // ─────────────────────────────────────────────
    // PRIVADO
    // ─────────────────────────────────────────────

    /**
     * Cambia el status de un remitente.
     * Punto único para activar / inactivar / despedir — elimina la triplicación.
     */
    private function changeRemitenteStatus(int $id, string $status, string $verb): JsonResponse
    {
        try {
            $user = User::findOrFail($id);

            if (! $user->isRemitente()) {
                return ApiResponse::error('Solo se pueden modificar remitentes', 400);
            }

            $user->status = $status;
            $user->save();

            return ApiResponse::success([
                'message' => "Remitente {$verb} correctamente",
                'user'    => $user,
            ]);
        } catch (Throwable $e) {
            return ApiResponse::error("Error al cambiar el estado del remitente", debug: $e->getMessage());
        }
    }
}