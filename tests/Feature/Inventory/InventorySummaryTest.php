<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryPurchase;
use Tests\TestCase;

class InventorySummaryTest extends TestCase
{
    // ─────────────────────────────────────────────
    // Autorización
    // ─────────────────────────────────────────────

    public function test_admin_puede_ver_el_summary(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/inventory/summary')
            ->assertOk()
            ->assertJsonPath('error', null)
            ->assertJsonStructure([
                'data' => ['total_income', 'total_expenses', 'net_profit'],
            ]);
    }

    public function test_remitente_no_puede_ver_el_summary(): void
    {
        $this->actingAsRemitente();

        $this->getJson('/api/v1/inventory/summary')
            ->assertForbidden();
    }

    public function test_usuario_no_autenticado_no_puede_ver_el_summary(): void
    {
        $this->getJson('/api/v1/inventory/summary')
            ->assertUnauthorized();
    }

    // ─────────────────────────────────────────────
    // Cálculos
    // ─────────────────────────────────────────────

    public function test_total_expenses_suma_todas_las_compras(): void
    {
        $this->actingAsAdmin();

        InventoryPurchase::factory()->create(['total_price' => 100000]);
        InventoryPurchase::factory()->create(['total_price' => 200000]);

        $response = $this->getJson('/api/v1/inventory/summary')->assertOk();

        $this->assertEquals(300000.0, $response->json('data.total_expenses'));
    }

    public function test_net_profit_es_ingresos_menos_gastos(): void
    {
        $this->actingAsAdmin();

        InventoryPurchase::factory()->create(['total_price' => 50000]);

        $response = $this->getJson('/api/v1/inventory/summary')->assertOk();

        $income   = $response->json('data.total_income');
        $expenses = $response->json('data.total_expenses');
        $profit   = $response->json('data.net_profit');

        $this->assertEquals(round($income - $expenses, 2), round($profit, 2));
    }

    public function test_sin_compras_los_gastos_son_cero(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/v1/inventory/summary')->assertOk();

        $this->assertEquals(0.0, $response->json('data.total_expenses'));
    }
}