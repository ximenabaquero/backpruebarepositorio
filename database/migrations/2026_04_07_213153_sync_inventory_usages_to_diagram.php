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
        Schema::table('inventory_usages', function (Blueprint $table) {
            // 1. Ajustar el campo 'reason' a VARCHAR(200)
            // Si ya existe 'reason' de 500, lo acortamos a 200.
            if (Schema::hasColumn('inventory_usages', 'reason')) {
                $table->string('reason', 200)->nullable()->change();
            } else {
                $table->string('reason', 200)->nullable()->after('status');
            }

            // 2. Eliminar 'notes' si existe (ya que no aparece en la imagen)
            if (Schema::hasColumn('inventory_usages', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_usages', function (Blueprint $table) {
            $table->text('notes')->nullable();
            $table->string('reason', 500)->nullable()->change();
        });
    }
};
