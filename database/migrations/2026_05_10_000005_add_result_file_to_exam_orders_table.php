<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exam_orders', function (Blueprint $table) {
            $table->string('result_file_path')->nullable()->after('received_at');
        });
    }

    public function down(): void
    {
        Schema::table('exam_orders', function (Blueprint $table) {
            $table->dropColumn('result_file_path');
        });
    }
};
