<?php

namespace Tests\Feature\Inventory;

use App\Models\Distributor;
use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use App\Models\InventoryPurchase;
use Tests\TestCase;

class InventoryPurchaseTest extends TestCase
{
    // ─────────────────────────────────────────────
    // Lectura y filtros
    // ─────────────────────────────────────────────

    public function test_admin_puede_listar_compras(): void
    {
        $this->actingAsAdmin();
        InventoryPurchase::factory()->count(3)->create();

        $this->getJson('/api/v1/inventory/purchases')
            ->assertOk()
            ->assertJsonPath('error', null)
            ->assertJsonCount(3, 'data');
    }

    public function test_remitente_puede_listar_compras(): void
    {
        $this->actingAsRemitente();
        InventoryPurchase::factory()->count(2)->create();

        $this->getJson('/api/v1/inventory/purchases')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_usuario_no_autenticado_no_puede_listar_compras(): void
    {
        $this->getJson('/api/v1/inventory/purchases')
            ->assertUnauthorized();
    }

    public function test_search_filtra_por_nombre_de_producto(): void
    {
        $this->actingAsAdmin();

        $faja  = InventoryProduct::factory()->insumo()->conStock(0)->create(['name' => 'Faja post-op']);
        $suero = InventoryProduct::factory()->insumo()->conStock(0)->create(['name' => 'Suero fisiológico']);
        InventoryPurchase::factory()->paraProducto($faja)->create();
        InventoryPurchase::factory()->paraProducto($suero)->create();

        $this->getJson('/api/v1/inventory/purchases?search=faja')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product.name', 'Faja post-op');
    }

    public function test_search_filtra_por_nombre_del_comprador(): void
    {
        $this->actingAsAdmin();

        $laura  = \App\Models\User::factory()->remitente()->create(['name' => 'Laura Pérez']);
        $carlos = \App\Models\User::factory()->remitente()->create(['name' => 'Carlos Mora']);
        InventoryPurchase::factory()->create(['user_id' => $laura->id]);
        InventoryPurchase::factory()->create(['user_id' => $carlos->id]);

        $this->getJson('/api/v1/inventory/purchases?search=laura')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.name', 'Laura Pérez');
    }

    public function test_search_filtra_por_nombre_del_distribuidor(): void
    {
        $this->actingAsAdmin();

        $dist1 = Distributor::factory()->withName('MediSupply')->create();
        $dist2 = Distributor::factory()->withName('Farmacol')->create();
        InventoryPurchase::factory()->conDistribuidor($dist1)->create();
        InventoryPurchase::factory()->conDistribuidor($dist2)->create();

        $this->getJson('/api/v1/inventory/purchases?search=medisupply')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_category_id_filtra_por_categoria_exacta(): void
    {
        $this->actingAsAdmin();

        $cat1  = InventoryCategory::factory()->create();
        $cat2  = InventoryCategory::factory()->create();
        $prod1 = InventoryProduct::factory()->insumo()->conStock(0)->create(['category_id' => $cat1->id]);
        $prod2 = InventoryProduct::factory()->insumo()->conStock(0)->create(['category_id' => $cat2->id]);
        InventoryPurchase::factory()->paraProducto($prod1)->create();
        InventoryPurchase::factory()->paraProducto($prod2)->create();

        $this->getJson("/api/v1/inventory/purchases?category_id={$cat1->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_search_y_category_id_se_pueden_combinar(): void
    {
        $this->actingAsAdmin();

        $cat   = InventoryCategory::factory()->create();
        $prod1 = InventoryProduct::factory()->insumo()->conStock(0)->create(['name' => 'Faja', 'category_id' => $cat->id]);
        $prod2 = InventoryProduct::factory()->insumo()->conStock(0)->create(['name' => 'Faja', 'category_id' => $cat->id]);
        $prod3 = InventoryProduct::factory()->insumo()->conStock(0)->create(['name' => 'Faja']);
        InventoryPurchase::factory()->paraProducto($prod1)->create();
        InventoryPurchase::factory()->paraProducto($prod2)->create();
        InventoryPurchase::factory()->paraProducto($prod3)->create();

        $this->getJson("/api/v1/inventory/purchases?search=faja&category_id={$cat->id}")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    // ─────────────────────────────────────────────
    // Registrar compra — producto existente
    // ─────────────────────────────────────────────

    public function test_registrar_compra_con_producto_existente_incrementa_stock(): void
    {
        $this->actingAsAdmin();
        $producto = InventoryProduct::factory()->insumo()->conStock(10)->create();

        $this->postJson('/api/v1/inventory/purchases', [
            'product_id' => $producto->id,
            'quantity'   => 5,
            'unit_price' => 12000,
        ])->assertCreated();

        $this->assertDatabaseHas('inventory_products', [
            'id'    => $producto->id,
            'stock' => 15,
        ]);
    }

    public function test_registrar_compra_calcula_total_price_automaticamente(): void
    {
        $this->actingAsAdmin();
        $producto = InventoryProduct::factory()->insumo()->conStock(0)->create();

        $response = $this->postJson('/api/v1/inventory/purchases', [
            'product_id' => $producto->id,
            'quantity'   => 10,
            'unit_price' => 5000,
        ])->assertCreated();

        // El cast 'decimal:2' serializa como string "50000.00" — comparamos numéricamente
        $this->assertEquals(50000, (float) $response->json('data.total_price'));
    }

    public function test_registrar_compra_guarda_fecha_automaticamente(): void
    {
        $this->actingAsAdmin();
        $producto = InventoryProduct::factory()->insumo()->conStock(0)->create();

        $response = $this->postJson('/api/v1/inventory/purchases', [
            'product_id' => $producto->id,
            'quantity'   => 1,
            'unit_price' => 1000,
        ])->assertCreated();

        // El cast 'date:Y-m-d' garantiza formato Y-m-d sin componente de tiempo
        $this->assertEquals(now()->toDateString(), $response->json('data.purchase_date'));
    }

    public function test_registrar_compra_asigna_usuario_autenticado(): void
    {
        $admin    = $this->actingAsAdmin();
        $producto = InventoryProduct::factory()->insumo()->conStock(0)->create();

        $this->postJson('/api/v1/inventory/purchases', [
            'product_id' => $producto->id,
            'quantity'   => 1,
            'unit_price' => 1000,
        ])->assertCreated();

        $this->assertDatabaseHas('inventory_purchases', [
            'product_id' => $producto->id,
            'user_id'    => $admin->id,
        ]);
    }

    public function test_registrar_compra_sin_distribuidor_es_valido(): void
    {
        $this->actingAsAdmin();
        $producto = InventoryProduct::factory()->insumo()->conStock(0)->create();

        $this->postJson('/api/v1/inventory/purchases', [
            'product_id'     => $producto->id,
            'distributor_id' => null,
            'quantity'       => 3,
            'unit_price'     => 2000,
        ])->assertCreated()
          ->assertJsonPath('data.distributor', null);
    }

    public function test_registrar_compra_con_distribuidor_valido(): void
    {
        $this->actingAsAdmin();
        $producto     = InventoryProduct::factory()->insumo()->conStock(0)->create();
        $distribuidor = Distributor::factory()->create();

        $this->postJson('/api/v1/inventory/purchases', [
            'product_id'     => $producto->id,
            'distributor_id' => $distribuidor->id,
            'quantity'       => 5,
            'unit_price'     => 3000,
        ])->assertCreated()
          ->assertJsonPath('data.distributor.id', $distribuidor->id);
    }

    // ─────────────────────────────────────────────
    // Registrar compra — producto nuevo
    // ─────────────────────────────────────────────

    public function test_registrar_compra_sin_product_id_crea_el_producto(): void
    {
        $this->actingAsAdmin();
        $categoria = InventoryCategory::factory()->create();

        $this->postJson('/api/v1/inventory/purchases', [
            'name'        => 'Nuevo insumo',
            'category_id' => $categoria->id,
            'type'        => 'insumo',
            'quantity'    => 10,
            'unit_price'  => 3000,
        ])->assertCreated();

        $this->assertDatabaseHas('inventory_products', [
            'name'  => 'Nuevo insumo',
            'stock' => 10,
        ]);
    }

    public function test_sin_product_id_requiere_name_category_id_y_type(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/inventory/purchases', [
            'quantity'   => 5,
            'unit_price' => 1000,
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['name', 'category_id', 'type']);
    }

    public function test_quantity_y_unit_price_son_siempre_obligatorios(): void
    {
        $this->actingAsAdmin();
        $producto = InventoryProduct::factory()->insumo()->conStock(0)->create();

        $this->postJson('/api/v1/inventory/purchases', [
            'product_id' => $producto->id,
        ])->assertUnprocessable()
          ->assertJsonValidationErrors(['quantity', 'unit_price']);
    }

    // ─────────────────────────────────────────────
    // Equipos — gasto único, no afectan stock
    // ─────────────────────────────────────────────

    public function test_compra_de_equipo_no_modifica_stock(): void
    {
        $this->actingAsAdmin();
        $categoria = InventoryCategory::factory()->create();

        $response = $this->postJson('/api/v1/inventory/purchases', [
            'name'        => 'Cavitación RF Pro',
            'category_id' => $categoria->id,
            'type'        => 'equipo',
            'quantity'    => 1,
            'unit_price'  => 1800000,
        ])->assertCreated();

        $this->assertDatabaseHas('inventory_products', [
            'id'    => $response->json('data.product_id'),
            'stock' => 0,
        ]);
    }

    // ─────────────────────────────────────────────
    // Autorización
    // ─────────────────────────────────────────────

    public function test_remitente_puede_registrar_compras(): void
    {
        $this->actingAsRemitente();
        $producto = InventoryProduct::factory()->insumo()->conStock(0)->create();

        $this->postJson('/api/v1/inventory/purchases', [
            'product_id' => $producto->id,
            'quantity'   => 5,
            'unit_price' => 1000,
        ])->assertCreated();
    }

    public function test_usuario_no_autenticado_no_puede_registrar_compras(): void
    {
        $producto = InventoryProduct::factory()->insumo()->conStock(0)->create();

        $this->postJson('/api/v1/inventory/purchases', [
            'product_id' => $producto->id,
            'quantity'   => 1,
            'unit_price' => 1000,
        ])->assertUnauthorized();
    }
}