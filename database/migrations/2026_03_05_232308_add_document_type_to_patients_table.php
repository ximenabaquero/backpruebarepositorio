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
        Schema::table('patients', function (Blueprint $table) {
                $table->enum('document_type', [
                'Cédula de Ciudadanía',
                'Cédula de Extranjería',
                'Pasaporte',
                'Tarjeta de Identidad',
            ])->default('Cédula de Ciudadanía')->after('cedula');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('document_type');
        });
    }
};
