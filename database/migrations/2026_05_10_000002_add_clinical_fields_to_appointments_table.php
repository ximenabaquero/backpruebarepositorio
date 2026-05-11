<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('medical_evaluation_id')
                  ->nullable()
                  ->after('procedure_id')
                  ->constrained('medical_evaluations')
                  ->nullOnDelete();

            $table->enum('procedure_type', ['concejacion', 'sincecion'])
                  ->nullable()
                  ->after('medical_evaluation_id');

            $table->string('doctor_name', 100)
                  ->nullable()
                  ->after('procedure_type');

            $table->boolean('fasting_required')
                  ->default(false)
                  ->after('doctor_name');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropForeign(['medical_evaluation_id']);
            $table->dropColumn(['medical_evaluation_id', 'procedure_type', 'doctor_name', 'fasting_required']);
        });
    }
};
