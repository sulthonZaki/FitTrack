<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();

            $table->foreignId('muscle_group_id')
                ->constrained('muscle_groups')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->string('name', 150)->index();
            $table->string('slug', 180)->unique();
            $table->string('equipment', 80);
            $table->string('difficulty', 20);
            $table->text('description');
            $table->json('instructions');
            $table->string('image_path', 512)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['muscle_group_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};