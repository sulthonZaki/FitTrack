<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_plan_exercises', function (Blueprint $table) {
            $table->id();

            $table->foreignId('workout_plan_id')
                ->constrained('workout_plans')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreignId('exercise_id')
                ->constrained('exercises')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->unsignedSmallInteger('sort_order');
            $table->unsignedSmallInteger('target_sets');
            $table->unsignedSmallInteger('target_reps');
            $table->decimal('target_weight_kg', 8, 3)->nullable();
            $table->unsignedSmallInteger('rest_seconds')->default(90);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['workout_plan_id', 'sort_order'],
                'plan_exercises_order_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_plan_exercises');
    }
};