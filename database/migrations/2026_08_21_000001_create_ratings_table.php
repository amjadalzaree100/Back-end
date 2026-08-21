<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ratings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('rater_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('rated_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('project_id')
                ->constrained('projects')
                ->cascadeOnDelete();

            $table->tinyInteger('rating');
            $table->timestamps();

            $table->unique(['project_id', 'rater_id', 'rated_user_id'], 'ratings_project_rater_rated_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
