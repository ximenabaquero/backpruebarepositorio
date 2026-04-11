<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use App\Models\InventoryUsage;
use App\Models\MedicalEvaluation;
use Tests\TestCase;

class InventoryUsageTest extends TestCase
{
    // ─────────────────────────────────────────────
    // Lectura y filtros
    // ─────────────────────────────────────────────

    public function test_admin_puede_listar_consumos(): void
    {
        $this->actingAsAdmin();
        InventoryUsage::factory()->count(3)->create();

        $this->getJson('/api/v1/inventory/usages')
            ->assertOk()
            ->assertJsonPath('error', null)
            ->assertJsonCount(3, 'data');
    }

    public function test_remitente_puede_listar_consumos(): void
    {
        $this->actingAsRemitente();
        InventoryUsage::factory()->count(2)->create();

        $this->getJson('/api/v1/inventory/usages')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_usuario_no_autenticado_no_puede_listar_consumos(): void
    {
        $this->getJson('/api/v1/inventory/usages')
            ->assertUnauthorized();
    }

    public function test_search_filtra_por_nombre_de_producto(): void
    {
        $this->actingAsAdmin();

        $faja   = InventoryProduct::factory()->insumo()->conStock(10)->create(['name' => 'Faja post-op']);
        $guante = InventoryProduct::factory()->insumo()->conStock(10)->create(['name' => 'Guante nitrilo']);
        InventoryUsage::factory()->sinPaciente()->create(['product_id' => $faja->id]);
        InventoryUsage::factory()->sinPaciente()->create(['product_id' => $guante->id]);

        $this->getJson('/api/v1/inventory/usages?search=faja')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product.name', 'Faja post-op');
    }

    public function test_search_filtra_por_nombre_de_quien_registro(): void
    {
        $this->actingAsAdmin();

        $laura  = \App\Models\User::factory()->remitente()->create(['name' => 'Laura Pérez']);
        $carlos = \App\Models\User::factory()->remitente()->create(['name' => 'Carlos Mora']);
        InventoryUsage::factory()->sinPaciente()->create(['user_id' => $laura->id]);
        InventoryUsage::factory()->sinPaciente()->create(['user_id' => $carlos->id]);

        $this->getJson('/api/v1/inventory/usages?search=laura')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.name', 'Laura Pérez');
    }

    public function test_category_id_filtra_por_categoria_exacta(): void
    {
        $this->actingAsAdmin();

        $cat1  = InventoryCategory::factory()->create();
        $cat2  = InventoryCategory::factory()->create();
        $prod1 = InventoryProduct::factory()->insumo()->conStock(10)->create(['category_id' => $cat1->id]);
        $prod2 = InventoryProduct::factory()->insumo()->conStock(10)->create(['category_id' => $cat2->id]);
        InventoryUsage::factory()->sinPaciente()->create(['product_id' => $prod1->id]);
        InventoryUsage::factory()->sinPaciente()->create(['product_id' => $prod2->id]);

        $this->getJson("/api/v1/inventory/usages?category_id={$cat1->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ─────────────────────────────────────────────
    // Consumo general (sin paciente)
    // ─────────────────────────────────────────────

    public function test_consumo_general_descuenta_stock(): void
    {
        $this->actingAsAdmin();
        $producto = InventoryProduct::factory()->insumo()->conStock(20)->create();

        $this->postJson('/api/v1/inventory/usages', [
            'status' => InventoryUsage::STATUS_SIN_PACIENTE,
            'reason' => 'Merma / daño',
            'items'  => [['product_id' => $producto->id, 'quantity' => 3]],
        ])->assertCreated();

        $this->assertDatabaseHas('inventory_products', [
            'id'    => $producto->id,
            'stock' => 17, // 20 - 3
        ]);
    }

    public function test_consumo_general_requiere_motivo(): void
    {
        $this->actingAsAdmin();
        $producto = InventoryProduct::factory()->insumo()->conStock(10)->create();

        $this->postJson('/api/v1/inventory/usages', [
            'status' => InventoryUsage::STATUS_SIN_PACIENTE,
            'items'  => [['product_id' => $producto->id, 'quantity' => 1]],
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['reason']);
    }

    public function test_consumo_general_guarda_fecha_automaticamente(): void
    {
        $this->actingAsAdmin();
        $producto = InventoryProduct::factory()->insumo()->conStock(10)->create();

        $this->postJson('/api/v1/inventory/usages', [
            'status' => InventoryUsage::STATUS_SIN_PACIENTE,
            'reason' => 'Prueba de protocolo',
            'items'  => [['product_id' => $producto->id, 'quantity' => 1]],
        ])->assertCreated();

        $this->assertDatabaseHas('inventory_usages', [
            'product_id' => $producto->id,
            'usage_date' => now()->startOfDay()->toDateTimeString(),
        ]);
    }

    public function test_consumo_multiple_descuenta_cada_producto_correctamente(): void
    {
        $this->actingAsAdmin();

        $prod1 = InventoryProduct::factory()->insumo()->conStock(10)->create();
        $prod2 = InventoryProduct::factory()->insumo()->conStock(20)->create();

        $this->postJson('/api/v1/inventory/usages', [
            'status' => InventoryUsage::STATUS_SIN_PACIENTE,
            'reason' => 'Uso general',
            'items'  => [
                ['product_id' => $prod1->id, 'quantity' => 2],
                ['product_id' => $prod2->id, 'quantity' => 5],
            ],
        ])->assertCreated();

        $this->assertDatabaseHas('inventory_products', ['id' => $prod1->id, 'stock' => 8]);
        $this->assertDatabaseHas('inventory_products', ['id' => $prod2->id, 'stock' => 15]);
    }

    // ─────────────────────────────────────────────
    // Consumo clínico (con paciente)
    // ─────────────────────────────────────────────

    public function test_consumo_clinico_descuenta_stock_y_vincula_evaluacion(): void
    {
        $this->actingAsRemitente();

        $evaluacion = MedicalEvaluation::factory()->confirmado()->create();
        $producto   = InventoryProduct::factory()->insumo()->conStock(10)->create();

        $this->postJson('/api/v1/inventory/usages', [
            'status'                => InventoryUsage::STATUS_CON_PACIENTE,
            'medical_evaluation_id' => $evaluacion->id,
            'items'                 => [['product_id' => $producto->id, 'quantity' => 2]],
        ])->assertCreated();

        $this->assertDatabaseHas('inventory_products', [
            'id'    => $producto->id,
            'stock' => 8, // 10 - 2
        ]);

        $this->assertDatabaseHas('inventory_usages', [
            'product_id'            => $producto->id,
            'medical_evaluation_id' => $evaluacion->id,
            'status'                => InventoryUsage::STATUS_CON_PACIENTE,
        ]);
    }

    public function test_consumo_clinico_requiere_evaluacion_confirmada(): void
    {
        $this->actingAsRemitente();

        $evaluacion = MedicalEvaluation::factory()->enEspera()->create();
        $producto   = InventoryProduct::factory()->insumo()->conStock(10)->create();

        $this->postJson('/api/v1/inventory/usages', [
            'status'                => InventoryUsage::STATUS_CON_PACIENTE,
            'medical_evaluation_id' => $evaluacion->id,
            'items'                 => [['product_id' => $producto->id, 'quantity' => 1]],
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['medical_evaluation_id']);
    }

    public function test_consumo_clinico_rechaza_evaluacion_cancelada(): void
    {
        $this->actingAsRemitente();

        $evaluacion = MedicalEvaluation::factory()->cancelado()->create();
        $producto   = InventoryProduct::factory()->insumo()->conStock(10)->create();

        $this->postJson('/api/v1/inventory/usages', [
            'status'                => InventoryUsage::STATUS_CON_PACIENTE,
            'medical_evaluation_id' => $evaluacion->id,
            'items'                 => [['product_id' => $producto->id, 'quantity' => 1]],
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['medical_evaluation_id']);
    }

    public function test_consumo_clinico_requiere_medical_evaluation_id(): void
    {
        $this->actingAsRemitente();
        $producto = InventoryProduct::factory()->insumo()->conStock(10)->create();

        $this->postJson('/api/v1/inventory/usages', [
            'status' => InventoryUsage::STATUS_CON_PACIENTE,
            'items'  => [['product_id' => $producto->id, 'quantity' => 1]],
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['medical_evaluation_id']);
    }

    // ─────────────────────────────────────────────
    // Guards — stock
    // ─────────────────────────────────────────────

    public function test_consumo_falla_si_stock_es_insuficiente(): void
    {
        $this->actingAsAdmin();
        $producto = InventoryProduct::factory()->insumo()->sinStock()->create();

        $this->postJson('/api/v1/inventory/usages', [
            'status' => InventoryUsage::STATUS_SIN_PACIENTE,
            'reason' => 'Prueba',
            'items'  => [['product_id' => $producto->id, 'quantity' => 1]],
        ])->assertUnprocessable()
          ->assertJsonPath('error', fn($msg) => str_contains($msg, 'Stock insuficiente'));
    }

    public function test_stock_no_cambia_si_un_item_del_lote_falla(): void
    {
        // Verifica atomicidad: si cualquier ítem falla, ninguno se descuenta
        $this->actingAsAdmin();

        $prod1 = InventoryProduct::factory()->insumo()->conStock(10)->create();
        $prod2 = InventoryProduct::factory()->insumo()->sinStock()->create();

        $this->postJson('/api/v1/inventory/usages', [
            'status' => InventoryUsage::STATUS_SIN_PACIENTE,
            'reason' => 'Prueba',
            'items'  => [
                ['product_id' => $prod1->id, 'quantity' => 2], // ok
                ['product_id' => $prod2->id, 'quantity' => 1], // falla
            ],
        ])->assertUnprocessable();

        // El stock de prod1 no debe haber cambiado
        $this->assertDatabaseHas('inventory_products', ['id' => $prod1->id, 'stock' => 10]);
    }

    public function test_consumo_falla_si_el_producto_es_equipo(): void
    {
        $this->actingAsAdmin();
        $equipo = InventoryProduct::factory()->equipo()->create();

        $this->postJson('/api/v1/inventory/usages', [
            'status' => InventoryUsage::STATUS_SIN_PACIENTE,
            'reason' => 'Prueba',
            'items'  => [['product_id' => $equipo->id, 'quantity' => 1]],
        ])->assertUnprocessable()
          ->assertJsonPath('error', fn($msg) => str_contains($msg, 'equipo'));
    }

    public function test_items_es_obligatorio_y_debe_tener_al_menos_uno(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/inventory/usages', [
            'status' => InventoryUsage::STATUS_SIN_PACIENTE,
            'reason' => 'Prueba',
            'items'  => [],
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['items']);
    }

    public function test_status_invalido_es_rechazado(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/inventory/usages', [
            'status' => 'estado_inventado',
            'reason' => 'Prueba',
            'items'  => [['product_id' => 1, 'quantity' => 1]],
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['status']);
    }

    // ─────────────────────────────────────────────
    // Autorización
    // ─────────────────────────────────────────────

    public function test_usuario_no_autenticado_no_puede_registrar_consumos(): void
    {
        $producto = InventoryProduct::factory()->insumo()->conStock(10)->create();

        $this->postJson('/api/v1/inventory/usages', [
            'status' => InventoryUsage::STATUS_SIN_PACIENTE,
            'reason' => 'Prueba',
            'items'  => [['product_id' => $producto->id, 'quantity' => 1]],
        ])->assertUnauthorized();
    }
}