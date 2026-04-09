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
        Schema::table('inventory_products', function (Blueprint $table) {
            // Cambiar tamaño de name
            $table->string('name', 100)->change();

            // Cambiar description
            $table->string('description', 255)->nullable()->change();

            // Eliminar unit_price
            $table->dropColumn('unit_price');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_products', function (Blueprint $table) {
            // Revertir cambios
            $table->string('name', 200)->change();

            $table->string('description', 500)->nullable()->change();

            $table->decimal('unit_price', 12, 2)->default(0);
        });
    }
};
