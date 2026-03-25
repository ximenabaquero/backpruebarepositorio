<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
            if (! $this->indexExists('medical_evaluations', 'me_patient_status_index')) {
                $table->index(['patient_id', 'status'], 'me_patient_status_index');
            }
        });

        Schema::table('procedures', function (Blueprint $table) {
            if (! $this->indexExists('procedures', 'proc_evaluation_date_index')) {
                $table->index(
                    ['medical_evaluation_id', 'procedure_date'],
                    'proc_evaluation_date_index'
                );
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

    /**
     * Verifica si un índice existe de forma compatible con MySQL y SQLite.
     * SHOW INDEX es sintaxis MySQL — SQLite usa sqlite_master.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select(
                "SELECT name FROM sqlite_master WHERE type='index' AND tbl_name=? AND name=?",
                [$table, $indexName]
            );
            return ! empty($indexes);
        }

        // MySQL / MariaDB
        $indexes = DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        );
        return ! empty($indexes);
    }
};