<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function me(Request $request)
    {
        return response()->json($request->user());
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'email'    => 'required|email',
                'password' => 'required',
            ]);

            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json(['message' => 'Credenciales incorrectas'], 401);
            }

            $user = Auth::user();

            if ($user->status !== User::STATUS_ACTIVE) {
                return response()->json(['message' => 'Tu cuenta no está activa.'], 403);
            }

            // Revocar tokens anteriores y crear uno nuevo
            $user->tokens()->delete();
            $token = $user->createToken('demo')->plainTextToken;

            return response()->json([
                'user'    => $user->only(['id', 'name', 'email', 'role', 'status']),
                'token'   => $token,
                'message' => 'Inicio de sesión exitoso',
            ]);

        } catch (\Throwable $th) {
            return response()->json(['error' => $th->getMessage()], 500);
        }
    }

    public function confirmPassword(Request $request)
    {
        $request->validate(['password' => 'required|string']);

        if (!Hash::check($request->password, $request->user()->password)) {
            return response()->json(['message' => 'Contraseña incorrecta.'], 401);
        }

        return response()->json(['ok' => true]);
    }

    public function logout(Request $request)
    {
        $request->user()?->tokens()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
