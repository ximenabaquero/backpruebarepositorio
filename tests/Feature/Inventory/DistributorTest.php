<?php

namespace Tests\Feature\Inventory;

use App\Models\Distributor;
use Tests\TestCase;

class DistributorTest extends TestCase
{
    // ─────────────────────────────────────────────
    // Lectura
    // ─────────────────────────────────────────────

    public function test_admin_puede_listar_distribuidores(): void
    {
        $this->actingAsAdmin();
        Distributor::factory()->count(3)->create();

        $this->getJson('/api/v1/inventory/distributors')
            ->assertOk()
            ->assertJsonPath('error', null)
            ->assertJsonCount(3, 'data');
    }

    public function test_remitente_puede_listar_distribuidores(): void
    {
        $this->actingAsRemitente();
        Distributor::factory()->count(2)->create();

        $this->getJson('/api/v1/inventory/distributors')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_usuario_no_autenticado_no_puede_listar_distribuidores(): void
    {
        $this->getJson('/api/v1/inventory/distributors')
            ->assertUnauthorized();
    }

    public function test_respuesta_incluye_solo_los_campos_necesarios(): void
    {
        $this->actingAsAdmin();
        Distributor::factory()->create();

        $this->getJson('/api/v1/inventory/distributors')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'cellphone', 'email'],
                ],
            ]);
    }

    public function test_sin_distribuidores_devuelve_array_vacio(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/inventory/distributors')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_email_puede_ser_nulo(): void
    {
        $this->actingAsAdmin();
        Distributor::factory()->create(['email' => null]);

        $this->getJson('/api/v1/inventory/distributors')
            ->assertOk()
            ->assertJsonPath('data.0.email', null);
    }
}