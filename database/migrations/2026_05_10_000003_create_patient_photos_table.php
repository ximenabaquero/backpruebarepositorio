<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')
                  ->constrained('patients')
                  ->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')
                  ->constrained('users');
            $table->enum('stage', ['antes', 'despues', 'mes1', 'mes2', 'mes3']);
            $table->string('image_path');
            $table->string('notes', 300)->nullable();
            $table->timestamp('taken_at')->useCurrent();
            $table->timestamps();

            $table->index(['patient_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_photos');
    }
};
