<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    // RefreshDatabase ejecuta migrate:fresh antes de cada test
    // Con SQLite en memoria es instantáneo — no toca la DB real
    use RefreshDatabase;

    // ─────────────────────────────────────────────
    // Helpers de autenticación
    // ─────────────────────────────────────────────

    /**
     * Crea un admin y autentica la sesión.
     */
    protected function actingAsAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        return $admin;
    }

    /**
     * Crea un remitente activo y autentica la sesión.
     */
    protected function actingAsRemitente(): User
    {
        $remitente = User::factory()->remitente()->create();
        $this->actingAs($remitente);
        return $remitente;
    }

    /**
     * Crea un remitente inactivo y autentica la sesión.
     */
    protected function actingAsRemitenteInactivo(): User
    {
        $remitente = User::factory()->remitente()->inactivo()->create();
        $this->actingAs($remitente);
        return $remitente;
    }
}