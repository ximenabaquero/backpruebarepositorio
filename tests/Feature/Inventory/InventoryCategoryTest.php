<?php

namespace Tests\Feature\Inventory;

use App\Models\InventoryCategory;
use Tests\TestCase;

class InventoryCategoryTest extends TestCase
{
    // ─────────────────────────────────────────────
    // Lectura
    // ─────────────────────────────────────────────

    public function test_admin_puede_listar_categorias(): void
    {
        $this->actingAsAdmin();
        InventoryCategory::factory()->count(3)->create();

        $this->getJson('/api/v1/inventory/categories')
            ->assertOk()
            ->assertJsonPath('error', null)
            ->assertJsonCount(3, 'data');
    }

    public function test_remitente_puede_listar_categorias(): void
    {
        $this->actingAsRemitente();
        InventoryCategory::factory()->count(2)->create();

        $this->getJson('/api/v1/inventory/categories')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_usuario_no_autenticado_no_puede_listar_categorias(): void
    {
        $this->getJson('/api/v1/inventory/categories')
            ->assertUnauthorized();
    }

    // ─────────────────────────────────────────────
    // Crear
    // ─────────────────────────────────────────────

    public function test_admin_puede_crear_categoria(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/inventory/categories', ['name' => 'Medicamentos'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Medicamentos')
            ->assertJsonPath('error', null);

        $this->assertDatabaseHas('inventory_categories', ['name' => 'Medicamentos']);
    }

    public function test_remitente_no_puede_crear_categoria(): void
    {
        $this->actingAsRemitente();

        $this->postJson('/api/v1/inventory/categories', ['name' => 'Medicamentos'])
            ->assertForbidden();

        $this->assertDatabaseMissing('inventory_categories', ['name' => 'Medicamentos']);
    }

    public function test_nombre_es_obligatorio_al_crear_categoria(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/inventory/categories', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_no_se_permiten_categorias_con_nombre_duplicado(): void
    {
        $this->actingAsAdmin();
        InventoryCategory::factory()->create(['name' => 'Insumos']);

        $this->postJson('/api/v1/inventory/categories', ['name' => 'Insumos'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_nombre_no_puede_superar_los_100_caracteres(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/inventory/categories', ['name' => str_repeat('a', 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    // ─────────────────────────────────────────────
    // Actualizar
    // ─────────────────────────────────────────────

    public function test_admin_puede_actualizar_nombre_de_categoria(): void
    {
        $this->actingAsAdmin();
        $categoria = InventoryCategory::factory()->create(['name' => 'Insumos']);

        $this->putJson("/api/v1/inventory/categories/{$categoria->id}", ['name' => 'Insumos Médicos'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Insumos Médicos');

        $this->assertDatabaseHas('inventory_categories', ['id' => $categoria->id, 'name' => 'Insumos Médicos']);
    }

    public function test_actualizar_con_el_mismo_nombre_es_valido(): void
    {
        // Editar sin cambiar el nombre no debe fallar la regla unique
        $this->actingAsAdmin();
        $categoria = InventoryCategory::factory()->create(['name' => 'Insumos']);

        $this->putJson("/api/v1/inventory/categories/{$categoria->id}", ['name' => 'Insumos'])
            ->assertOk();
    }

    public function test_no_se_puede_actualizar_a_nombre_que_ya_existe_en_otra_categoria(): void
    {
        $this->actingAsAdmin();
        InventoryCategory::factory()->create(['name' => 'Medicamentos']);
        $otra = InventoryCategory::factory()->create(['name' => 'Insumos']);

        $this->putJson("/api/v1/inventory/categories/{$otra->id}", ['name' => 'Medicamentos'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_remitente_no_puede_actualizar_categoria(): void
    {
        $this->actingAsRemitente();
        $categoria = InventoryCategory::factory()->create();

        $this->putJson("/api/v1/inventory/categories/{$categoria->id}", ['name' => 'Nuevo nombre'])
            ->assertForbidden();
    }

    public function test_actualizar_categoria_inexistente_devuelve_404(): void
    {
        $this->actingAsAdmin();

        $this->putJson('/api/v1/inventory/categories/99999', ['name' => 'Cualquiera'])
            ->assertNotFound();
    }
}