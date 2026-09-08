<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_session_exercises', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workout_session_id')
                ->constrained('workout_sessions')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreignId('exercise_id')
                ->constrained('exercises')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->string('exercise_name_snapshot', 150);
            $table->string('muscle_group_name_snapshot', 80);
            $table->string('equipment_snapshot', 80);

            $table->unsignedSmallInteger('sort_order');
            $table->unsignedSmallInteger('target_sets');
            $table->unsignedSmallInteger('target_reps');
            $table->decimal('target_weight_kg', 8, 3)->nullable();
            $table->unsignedSmallInteger('rest_seconds');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['workout_session_id', 'sort_order'],
                'session_exercises_order_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_session_exercises');
    }
};