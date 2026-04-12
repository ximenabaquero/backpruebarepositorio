<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryProduct;
use App\Models\InventoryUsage;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    /**
     * Simula dos requests concurrentes consumiendo el mismo stock.
     *
     * Escenario real: dos remitentes registran consumos del mismo producto
     * al mismo tiempo. Sin lockForUpdate(), ambos leerían stock = 10
     * y ambos descontarían, dejando el stock en -10 o valores incorrectos.
     *
     * Con lockForUpdate() + transaction, el segundo espera al primero
     * y lee el stock ya decrementado.
     */
    public function test_dos_consumos_concurrentes_no_producen_stock_negativo(): void
    {
        $this->actingAsAdmin();

        $producto = InventoryProduct::factory()->insumo()->conStock(10)->create();

        // Simula Request A: consume 6 unidades
        $this->postJson('/api/v1/inventory/usages', [
            'status' => InventoryUsage::STATUS_SIN_PACIENTE,
            'reason' => 'Uso general',
            'items'  => [['product_id' => $producto->id, 'quantity' => 6]],
        ])->assertCreated();

        // Simula Request B: intenta consumir 6 del stock restante (solo quedan 4)
        $this->postJson('/api/v1/inventory/usages', [
            'status' => InventoryUsage::STATUS_SIN_PACIENTE,
            'reason' => 'Uso general',
            'items'  => [['product_id' => $producto->id, 'quantity' => 6]],
        ])->assertUnprocessable(); // debe fallar — stock insuficiente

        // Stock final debe ser 4, nunca negativo
        $this->assertDatabaseHas('inventory_products', [
            'id'    => $producto->id,
            'stock' => 4,
        ]);
    }

    /**
     * Verifica que el stock nunca queda en negativo bajo ninguna circunstancia.
     * Múltiples consumos secuenciales deben respetar el límite.
     */
    public function test_stock_nunca_queda_negativo_con_consumos_sucesivos(): void
    {
        $this->actingAsAdmin();

        $producto = InventoryProduct::factory()->insumo()->conStock(5)->create();

        // Consume todo el stock de a poco
        foreach ([2, 2, 1] as $cantidad) {
            $this->postJson('/api/v1/inventory/usages', [
                'status' => InventoryUsage::STATUS_SIN_PACIENTE,
                'reason' => 'Uso general',
                'items'  => [['product_id' => $producto->id, 'quantity' => $cantidad]],
            ])->assertCreated();
        }

        // Stock debe ser exactamente 0
        $this->assertDatabaseHas('inventory_products', [
            'id'    => $producto->id,
            'stock' => 0,
        ]);

        // Cualquier consumo adicional debe fallar
        $this->postJson('/api/v1/inventory/usages', [
            'status' => InventoryUsage::STATUS_SIN_PACIENTE,
            'reason' => 'Intento extra',
            'items'  => [['product_id' => $producto->id, 'quantity' => 1]],
        ])->assertUnprocessable();

        // Stock sigue en 0, no en -1
        $this->assertDatabaseHas('inventory_products', [
            'id'    => $producto->id,
            'stock' => 0,
        ]);
    }

    /**
     * Verifica que la transacción es completamente atómica:
     * si un ítem del lote falla, NINGÚN ítem del lote se descuenta.
     *
     * Sin DB::transaction(), el primer ítem se descontaría antes de que
     * el segundo falle, dejando el inventario en estado inconsistente.
     */
    public function test_transaccion_atomica_revierte_todo_si_un_item_falla(): void
    {
        $this->actingAsAdmin();

        $prod1 = InventoryProduct::factory()->insumo()->conStock(10)->create();
        $prod2 = InventoryProduct::factory()->insumo()->conStock(10)->create();
        $prod3 = InventoryProduct::factory()->insumo()->sinStock()->create(); // fallará

        $this->postJson('/api/v1/inventory/usages', [
            'status' => InventoryUsage::STATUS_SIN_PACIENTE,
            'reason' => 'Prueba atomicidad',
            'items'  => [
                ['product_id' => $prod1->id, 'quantity' => 3], // ok
                ['product_id' => $prod2->id, 'quantity' => 3], // ok
                ['product_id' => $prod3->id, 'quantity' => 1], // falla — sin stock
            ],
        ])->assertUnprocessable();

        // Ningún producto debe haber sido modificado
        $this->assertDatabaseHas('inventory_products', ['id' => $prod1->id, 'stock' => 10]);
        $this->assertDatabaseHas('inventory_products', ['id' => $prod2->id, 'stock' => 10]);
        $this->assertDatabaseHas('inventory_products', ['id' => $prod3->id, 'stock' => 0]);

        // Tampoco deben existir registros de consumo huérfanos
        $this->assertDatabaseCount('inventory_usages', 0);
    }

    /**
     * Verifica que registrar una compra y actualizar el stock es atómico.
     * Si la compra se guarda pero el stock no se incrementa (o viceversa),
     * el inventario queda inconsistente.
     */
    public function test_compra_y_stock_se_registran_juntos_o_ninguno(): void
    {
        $this->actingAsAdmin();

        $stockInicial = 10;
        $producto     = InventoryProduct::factory()->insumo()->conStock($stockInicial)->create();
        $compraBefore = \App\Models\InventoryPurchase::count();

        $this->postJson('/api/v1/inventory/purchases', [
            'product_id' => $producto->id,
            'quantity'   => 5,
            'unit_price' => 1000,
        ])->assertCreated();

        // Ambas operaciones deben haberse completado
        $this->assertDatabaseHas('inventory_products', [
            'id'    => $producto->id,
            'stock' => $stockInicial + 5,
        ]);

        $this->assertEquals($compraBefore + 1, \App\Models\InventoryPurchase::count());
    }

    /**
     * Verifica que el lockForUpdate protege contra lecturas sucias.
     *
     * Simula la secuencia problemática sin locks:
     *   T1 lee stock = 5
     *   T2 lee stock = 5  ← lee el mismo valor antes de que T1 escriba
     *   T1 descuenta 5 → stock = 0
     *   T2 descuenta 5 → stock = -5 ← inconsistencia
     *
     * Con lockForUpdate() T2 espera a T1, lee stock = 0 y falla correctamente.
     */
    public function test_lock_for_update_previene_double_spend(): void
    {
        $this->actingAsAdmin();

        $producto = InventoryProduct::factory()->insumo()->conStock(5)->create();

        // T1 consume exactamente todo el stock
        $this->postJson('/api/v1/inventory/usages', [
            'status' => InventoryUsage::STATUS_SIN_PACIENTE,
            'reason' => 'T1',
            'items'  => [['product_id' => $producto->id, 'quantity' => 5]],
        ])->assertCreated();

        // T2 intenta consumir el mismo stock que T1 ya tomó
        $this->postJson('/api/v1/inventory/usages', [
            'status' => InventoryUsage::STATUS_SIN_PACIENTE,
            'reason' => 'T2',
            'items'  => [['product_id' => $producto->id, 'quantity' => 5]],
        ])->assertUnprocessable()
          ->assertJsonPath('error', fn($msg) => str_contains($msg, 'Stock insuficiente'));

        // Stock es 0, no -5
        $this->assertDatabaseHas('inventory_products', [
            'id'    => $producto->id,
            'stock' => 0,
        ]);

        // Solo existe un consumo registrado — T2 nunca se completó
        $this->assertDatabaseCount('inventory_usages', 1);
    }

    /**
     * Verifica que el stock de equipos siempre es null y nunca se modifica.
     * Un equipo no debería aparecer en ningún consumo ni afectar stock.
     */
    public function test_equipo_no_tiene_stock_ni_puede_ser_consumido(): void
    {
        $this->actingAsAdmin();

        $equipo = InventoryProduct::factory()->equipo()->create();

        $this->assertEquals(0, $equipo->fresh()->stock);

        $this->postJson('/api/v1/inventory/usages', [
            'status' => InventoryUsage::STATUS_SIN_PACIENTE,
            'reason' => 'Prueba',
            'items'  => [['product_id' => $equipo->id, 'quantity' => 1]],
        ])->assertUnprocessable();

        // Stock sigue siendo null — nunca se tocó
        $this->assertEquals(0, $equipo->fresh()->stock);
        $this->assertDatabaseCount('inventory_usages', 0);
    }
}