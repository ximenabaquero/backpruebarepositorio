<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migración obsoleta.
     *
     * Originalmente intentaba eliminar procedure_id de medical_evaluations,
     * pero esa columna nunca existió en esa tabla.
     * La relación correcta es procedures.medical_evaluation_id (no al revés).
     *
     * Se deja vacía para preservar el historial de migraciones
     * sin romper RefreshDatabase en los tests.
     */
    public function up(): void
    {
        //
    }

    public function down(): void
    {
        //
    }
};