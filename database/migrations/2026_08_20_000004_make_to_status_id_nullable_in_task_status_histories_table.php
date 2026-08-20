<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_status_histories', function (Blueprint $table) {
            $table->dropForeign(['to_status_id']);
            $table->foreignId('to_status_id')->nullable()->change();
            $table->foreign('to_status_id')->references('id')->on('task_statuses')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('task_status_histories', function (Blueprint $table) {
            $table->dropForeign(['to_status_id']);
            $table->foreignId('to_status_id')->change();
            $table->foreign('to_status_id')->references('id')->on('task_statuses')->cascadeOnDelete();
        });
    }
};
