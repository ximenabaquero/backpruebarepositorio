<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patient_photos', function (Blueprint $table) {
            $table->foreignId('medical_evaluation_id')
                  ->nullable()
                  ->after('patient_id')
                  ->constrained('medical_evaluations')
                  ->nullOnDelete();

            $table->index('medical_evaluation_id');
        });
    }

    public function down(): void
    {
        Schema::table('patient_photos', function (Blueprint $table) {
            $table->dropForeign(['medical_evaluation_id']);
            $table->dropIndex(['medical_evaluation_id']);
            $table->dropColumn('medical_evaluation_id');
        });
    }
};
