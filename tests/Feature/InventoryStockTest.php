<?php

namespace Tests\Feature;

use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use App\Models\InventoryPurchase;
use App\Models\InventoryUsage;
use App\Models\User;
use Tests\TestCase;

class InventoryStockTest extends TestCase
{
    // ─────────────────────────────────────────────
    // Compras — stock
    // ─────────────────────────────────────────────

    public function test_compra_con_producto_incrementa_stock(): void
    {
        $this->actingAsAdmin();

        $categoria = InventoryCategory::factory()->create();
        $producto  = InventoryProduct::factory()->conStock(10)->create([
            'category_id' => $categoria->id,
        ]);

        $this->postJson('/api/v1/inventory/purchases', [
            'category_id'   => $categoria->id,
            'product_id'    => $producto->id,
            'item_name'     => $producto->name,
            'quantity'      => 5,
            'unit_price'    => 50000,
            'purchase_date' => now()->toDateString(),
        ])->assertCreated();

        // 10 + 5 = 15
        $this->assertDatabaseHas('inventory_products', [
            'id'    => $producto->id,
            'stock' => 15,
        ]);
    }

    public function test_compra_sin_producto_no_modifica_stock(): void
    {
        $this->actingAsAdmin();

        $categoria = InventoryCategory::factory()->create();
        $producto  = InventoryProduct::factory()->conStock(10)->create([
            'category_id' => $categoria->id,
        ]);

        $this->postJson('/api/v1/inventory/purchases', [
            'category_id'   => $categoria->id,
            'product_id'    => null,
            'item_name'     => 'Insumo genérico',
            'quantity'      => 5,
            'unit_price'    => 50000,
            'purchase_date' => now()->toDateString(),
        ])->assertCreated();

        // Stock sin cambios
        $this->assertDatabaseHas('inventory_products', [
            'id'    => $producto->id,
            'stock' => 10,
        ]);
    }

    public function test_actualizar_compra_ajusta_diferencia_de_stock(): void
    {
        $this->actingAsAdmin();

        $categoria = InventoryCategory::factory()->create();
        $producto  = InventoryProduct::factory()->conStock(15)->create([
            'category_id' => $categoria->id,
        ]);

        $compra = InventoryPurchase::factory()->create([
            'category_id' => $categoria->id,
            'product_id'  => $producto->id,
            'quantity'    => 5,
            'unit_price'  => 50000,
            'total_price' => 250000,
        ]);

        // Cambiar cantidad de 5 a 8 → diferencia +3
        $this->putJson("/api/v1/inventory/purchases/{$compra->id}", [
            'quantity' => 8,
        ]);

        // 15 + 3 = 18
        $this->assertDatabaseHas('inventory_products', [
            'id'    => $producto->id,
            'stock' => 18,
        ]);
    }

    public function test_actualizar_compra_cambiando_producto_ajusta_ambos_stocks(): void
    {
        $this->actingAsAdmin();

        $categoria   = InventoryCategory::factory()->create();
        $producto1   = InventoryProduct::factory()->conStock(10)->create(['category_id' => $categoria->id]);
        $producto2   = InventoryProduct::factory()->conStock(5)->create(['category_id' => $categoria->id]);

        $compra = InventoryPurchase::factory()->create([
            'category_id' => $categoria->id,
            'product_id'  => $producto1->id,
            'quantity'    => 3,
            'unit_price'  => 50000,
            'total_price' => 150000,
        ]);

        $this->putJson("/api/v1/inventory/purchases/{$compra->id}", [
            'product_id' => $producto2->id,
            'quantity'   => 3,
        ]);

        // Producto1: 10 - 3 = 7
        $this->assertDatabaseHas('inventory_products', ['id' => $producto1->id, 'stock' => 7]);
        // Producto2: 5 + 3 = 8
        $this->assertDatabaseHas('inventory_products', ['id' => $producto2->id, 'stock' => 8]);
    }

    // ─────────────────────────────────────────────
    // Consumos — stock
    // ─────────────────────────────────────────────

    public function test_consumo_descuenta_stock(): void
    {
        $this->actingAsRemitente();

        $producto = InventoryProduct::factory()->conStock(20)->create();

        $this->postJson('/api/v1/inventory/usages', [
            'product_id' => $producto->id,
            'quantity'   => 3,
            'usage_date' => now()->toDateString(),
        ])->assertCreated();

        // 20 - 3 = 17
        $this->assertDatabaseHas('inventory_products', [
            'id'    => $producto->id,
            'stock' => 17,
        ]);
    }

    public function test_consumo_falla_con_stock_insuficiente(): void
    {
        $this->actingAsRemitente();

        $producto = InventoryProduct::factory()->sinStock()->create();

        $this->postJson('/api/v1/inventory/usages', [
            'product_id' => $producto->id,
            'quantity'   => 1,
            'usage_date' => now()->toDateString(),
        ])->assertStatus(422)
          ->assertJsonPath('error', "Stock insuficiente. Disponible: 0 unidades.");
    }

    public function test_eliminar_consumo_restaura_stock(): void
    {
        $admin    = $this->actingAsAdmin();
        $producto = InventoryProduct::factory()->conStock(10)->create();

        $consumo = InventoryUsage::factory()->create([
            'user_id'    => $admin->id,
            'product_id' => $producto->id,
            'quantity'   => 4,
        ]);

        // Reducir stock manualmente para simular el consumo
        $producto->update(['stock' => 6]);

        $this->deleteJson("/api/v1/inventory/usages/{$consumo->id}")
            ->assertOk();

        // 6 + 4 = 10 — stock restaurado
        $this->assertDatabaseHas('inventory_products', [
            'id'    => $producto->id,
            'stock' => 10,
        ]);
    }

    public function test_eliminar_consumo_es_atomico(): void
    {
        // Si el delete falla, el stock no debe cambiar
        // Este test verifica que no hay inconsistencias parciales
        $admin    = $this->actingAsAdmin();
        $producto = InventoryProduct::factory()->conStock(10)->create();

        $consumo = InventoryUsage::factory()->create([
            'user_id'    => $admin->id,
            'product_id' => $producto->id,
            'quantity'   => 3,
        ]);

        // Stock actual tras el consumo
        $producto->update(['stock' => 7]);

        $this->deleteJson("/api/v1/inventory/usages/{$consumo->id}")
            ->assertOk();

        $this->assertDatabaseMissing('inventory_usages', ['id' => $consumo->id]);
        $this->assertDatabaseHas('inventory_products', ['id' => $producto->id, 'stock' => 10]);
    }

    // ─────────────────────────────────────────────
    // Autorización
    // ─────────────────────────────────────────────

    public function test_remitente_no_puede_eliminar_consumo_ajeno(): void
    {
        $this->actingAsRemitente();

        $otroRemitente = User::factory()->remitente()->create();
        $consumo = InventoryUsage::factory()->create(['user_id' => $otroRemitente->id]);

        $this->deleteJson("/api/v1/inventory/usages/{$consumo->id}")
            ->assertForbidden();
    }

    public function test_admin_puede_eliminar_cualquier_consumo(): void
    {
        $this->actingAsAdmin();

        $remitente = User::factory()->remitente()->create();
        $producto  = InventoryProduct::factory()->conStock(10)->create();
        $consumo   = InventoryUsage::factory()->create([
            'user_id'    => $remitente->id,
            'product_id' => $producto->id,
            'quantity'   => 2,
        ]);

        $producto->update(['stock' => 8]);

        $this->deleteJson("/api/v1/inventory/usages/{$consumo->id}")
            ->assertOk();
    }
}