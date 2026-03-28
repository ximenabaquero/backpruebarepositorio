<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migración obsoleta — vaciada intencionalmente.
     *
     * Originalmente agregaba procedure_id a medical_evaluations,
     * pero esa relación fue rediseñada: es procedures quien tiene
     * medical_evaluation_id (relación correcta hasMany).
     *
     * La migración que eliminaba procedure_id también fue vaciada.
     * Ambas se neutralizan — la columna nunca se crea ni se elimina.
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