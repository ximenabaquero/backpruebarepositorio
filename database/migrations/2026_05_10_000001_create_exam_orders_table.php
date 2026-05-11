<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_evaluation_id')
                  ->constrained('medical_evaluations')
                  ->cascadeOnDelete();
            $table->json('exams');
            $table->enum('status', ['pendiente', 'apto', 'no_apto'])->default('pendiente');
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique('medical_evaluation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_orders');
    }
};
