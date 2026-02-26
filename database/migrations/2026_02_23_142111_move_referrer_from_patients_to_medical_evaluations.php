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
        // Agregar referrer_name a medical_evaluations
        Schema::table('medical_evaluations', function (Blueprint $table) {
            $table->string('referrer_name', 50)
                ->after('patient_age_at_evaluation');
        });

        // Eliminar referrer_name de patients
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn('referrer_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir cambios

        // Volver a agregar en patients
        Schema::table('patients', function (Blueprint $table) {
            $table->string('referrer_name', 60)
                    ->after('cellphone');
        });

        // Eliminar de medical_evaluations
        Schema::table('medical_evaluations', function (Blueprint $table) {
            $table->dropColumn('referrer_name');
        });
    }
};
