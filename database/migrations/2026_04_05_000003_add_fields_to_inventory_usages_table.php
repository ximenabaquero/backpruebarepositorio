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
            // Agregar medical_evaluation_id como foreign key nullable
            $table->foreignId('medical_evaluation_id')
                  ->nullable()
                  ->after('product_id')
                  ->constrained('medical_evaluations')
                  ->nullOnDelete();
            
            // Agregar campo status (con_paciente | sin_paciente)
            $table->enum('status', ['con_paciente', 'sin_paciente'])
                  ->default('sin_paciente')
                  ->after('quantity');
            
            // Agregar campo reason (nullable)
            $table->string('reason', 500)->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_usages', function (Blueprint $table) {
            $table->dropForeign(['medical_evaluation_id']);
            $table->dropColumn(['medical_evaluation_id', 'status', 'reason']);
        });
    }
};
