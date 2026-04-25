<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('inventory_purchases', function (Blueprint $table) {
            // 1. Agregamos el distributor_id (asegúrate de que la tabla 'distributors' ya exista)
            if (!Schema::hasColumn('inventory_purchases', 'distributor_id')) {
                $table->foreignId('distributor_id')
                      ->nullable()
                      ->after('product_id')
                      ->constrained('distributors')
                      ->nullOnDelete();
            }

            // 2. Ajustamos precisión de precios a (10,2)
            if (Schema::hasColumn('inventory_purchases', 'unit_price')) {
                $table->decimal('unit_price', 10, 2)->change();
            }
            if (Schema::hasColumn('inventory_purchases', 'total_price')) {
                $table->decimal('total_price', 10, 2)->change();
            }

            // 3. BORRAR CATEGORY_ID de compras
            if (Schema::hasColumn('inventory_purchases', 'category_id')) {
                try {
                    $table->dropForeign(['category_id']); 
                } catch (\Exception $e) {
                    // Foreign key podría no existir
                }
                $table->dropColumn('category_id');
            }

            // 4. Limpieza de campos de texto viejos
            if (Schema::hasColumn('inventory_purchases', 'item_name')) {
                $table->dropColumn('item_name');
            }
            
            if (Schema::hasColumn('inventory_purchases', 'distributor')) {
                $table->dropColumn('distributor');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_purchases', function (Blueprint $table) {
            $table->dropForeign(['distributor_id']);
            $table->dropColumn('distributor_id');
            
            // Revertir category_id
            $table->foreignId('category_id')
                  ->nullable()
                  ->constrained('inventory_categories')
                  ->nullOnDelete();

            $table->string('item_name', 200)->nullable();
        });
    }
};