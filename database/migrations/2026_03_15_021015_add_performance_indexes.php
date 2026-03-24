<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Índices de rendimiento — versión auditada.
 *
 * Criterio: solo se indexa lo que se consulta frecuentemente
 * en rutas de usuario (no endpoints admin esporádicos).
 *
 * Eliminados vs versión anterior:
 *   - medical_evaluations.user_id        → solo se asigna, casi nunca se filtra
 *   - medical_evaluations.referrer_name  → solo referrerStats(), admin esporádico
 *   - medical_evaluations.patient_id     → cubierto por compuesto [patient_id, status]
 *   - procedures.medical_evaluation_id   → cubierto por compuesto [evaluation_id, date]
 *   - procedure_items.item_name          → GROUP BY en 2 endpoints admin mensuales
 *
 * Total: 12 → 6 índices.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            if (! $this->indexExists('patients', 'patients_user_id_index')) {
                $table->index('user_id', 'patients_user_id_index');
            }
            if (! $this->indexExists('patients', 'patients_cedula_unique')) {
                $table->unique('cedula', 'patients_cedula_unique');
            }
        });

        Schema::table('medical_evaluations', function (Blueprint $table) {
            // Compuesto: cubre WHERE patient_id = ? Y WHERE patient_id = ? AND status = ?
            if (! $this->indexExists('medical_evaluations', 'me_patient_status_index')) {
                $table->index(['patient_id', 'status'], 'me_patient_status_index');
            }
        });

        Schema::table('procedures', function (Blueprint $table) {
            // Compuesto: cubre el patrón principal de StatsService
            if (! $this->indexExists('procedures', 'proc_evaluation_date_index')) {
                $table->index(['medical_evaluation_id', 'procedure_date'], 'proc_evaluation_date_index');
            }
        });

        Schema::table('procedure_items', function (Blueprint $table) {
            if (! $this->indexExists('procedure_items', 'pi_procedure_id_index')) {
                $table->index('procedure_id', 'pi_procedure_id_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('patients_user_id_index');
            $table->dropUnique('patients_cedula_unique');
        });
        Schema::table('medical_evaluations', function (Blueprint $table) {
            $table->dropIndex('me_patient_status_index');
        });
        Schema::table('procedures', function (Blueprint $table) {
            $table->dropIndex('proc_evaluation_date_index');
        });
        Schema::table('procedure_items', function (Blueprint $table) {
            $table->dropIndex('pi_procedure_id_index');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return ! empty(DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        ));
    }
};