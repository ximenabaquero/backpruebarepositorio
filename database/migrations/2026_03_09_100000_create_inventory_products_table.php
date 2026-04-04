<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                  ->constrained('inventory_categories')
                  ->restrictOnDelete();
            $table->string('name', 200);
            $table->string('description', 500)->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->integer('stock')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_products');
    }
};
