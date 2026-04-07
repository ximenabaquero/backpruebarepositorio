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
            // product_id ya existe, solo agregamos distributor
            $table->string('distributor', 200)->nullable()->after('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_purchases', function (Blueprint $table) {
            $table->dropColumn('distributor');
        });
    }
};
