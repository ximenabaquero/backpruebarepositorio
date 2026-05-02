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
            $table->renameColumn('stock', 'stock_actual');
            $table->integer('stock_minimo')->default(0)->after('stock_actual');
            $table->dropColumn('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_products', function (Blueprint $table) {
            $table->renameColumn('stock_actual', 'stock');
            $table->dropColumn('stock_minimo');
            $table->boolean('active')->default(true);
        });
    }
};
