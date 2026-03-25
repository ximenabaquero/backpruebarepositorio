<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\User;
use Tests\TestCase;

class EnsureUserIsActiveTest extends TestCase
{
    // ─────────────────────────────────────────────
    // Usuario activo — puede operar
    // ─────────────────────────────────────────────

    public function test_remitente_activo_puede_listar_pacientes(): void
    {
        $remitente = $this->actingAsRemitente();
        Patient::factory()->forRemitente($remitente)->count(2)->create();

        $this->getJson('/api/v1/patients')
            ->assertOk()
            ->assertJsonPath('error', null);
    }

    // ─────────────────────────────────────────────
    // Usuario inactivo — bloqueado
    // ─────────────────────────────────────────────

    public function test_remitente_inactivo_no_puede_listar_pacientes(): void
    {
        $this->actingAsRemitenteInactivo();

        $this->getJson('/api/v1/patients')
            ->assertForbidden()
            ->assertJsonPath('error', 'Tu cuenta no está activa. Contactá al administrador.');
    }

    public function test_remitente_inactivo_no_puede_crear_pacientes(): void
    {
        $this->actingAsRemitenteInactivo();

        $this->postJson('/api/v1/patients', [
            'first_name'     => 'María',
            'last_name'      => 'García',
            'cedula'         => '1234567890',
            'document_type'  => 'Cédula de Ciudadanía',
            'cellphone'      => '3001234567',
            'biological_sex' => 'Femenino',
            'date_of_birth'  => '1990-01-01',
        ])
        ->assertForbidden();
    }

    public function test_remitente_inactivo_no_puede_crear_evaluaciones(): void
    {
        $this->actingAsRemitenteInactivo();

        // POST /clinical-records es la ruta activa — POST /medical-evaluations fue eliminada
        $this->postJson('/api/v1/clinical-records', [
            'patient' => [
                'first_name'     => 'María',
                'last_name'      => 'García',
                'cedula'         => '1234567890',
                'document_type'  => 'Cédula de Ciudadanía',
                'cellphone'      => '3001234567',
                'biological_sex' => 'Femenino',
                'date_of_birth'  => '1990-01-01',
            ],
            'evaluation' => [
                'weight'             => 65,
                'height'             => 1.65,
                'medical_background' => 'Sin antecedentes',
            ],
            'procedure' => [
                'notes' => 'Test',
                'items' => [['item_name' => 'Test', 'price' => 100]],
            ],
        ])
        ->assertForbidden();
    }

    // ─────────────────────────────────────────────
    // Usuario despedido — bloqueado igual que inactivo
    // ─────────────────────────────────────────────

    public function test_remitente_despedido_no_puede_operar(): void
    {
        $despedido = User::factory()->remitente()->despedido()->create();
        $this->actingAs($despedido);

        $this->getJson('/api/v1/patients')->assertForbidden();
    }

    // ─────────────────────────────────────────────
    // Sin autenticación — 401
    // ─────────────────────────────────────────────

    public function test_sin_autenticacion_devuelve_401(): void
    {
        $this->getJson('/api/v1/patients')
            ->assertUnauthorized()
            ->assertJsonPath('error', 'Unauthenticated');
    }
}