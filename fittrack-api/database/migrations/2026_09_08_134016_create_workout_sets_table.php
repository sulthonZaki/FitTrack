<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_sets', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workout_session_exercise_id')
                ->constrained('workout_session_exercises')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->unsignedSmallInteger('set_number');
            $table->unsignedSmallInteger('reps');
            $table->decimal('weight_kg', 8, 3)->default(0);
            $table->dateTime('completed_at');

            $table->timestamps();

            $table->unique(
                ['workout_session_exercise_id', 'set_number'],
                'workout_sets_exercise_number_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_sets');
    }
};