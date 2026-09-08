<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->foreignId('workout_plan_id')
                ->nullable()
                ->constrained('workout_plans')
                ->restrictOnDelete()
                ->restrictOnUpdate();

            $table->uuid('client_request_id');
            $table->string('plan_name_snapshot', 120);
            $table->string('status', 20)->default('in_progress');

            $table->dateTime('started_at');
            $table->dateTime('finished_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(
                ['user_id', 'client_request_id'],
                'sessions_user_request_unique'
            );

            $table->index(
                ['user_id', 'status', 'started_at'],
                'sessions_user_status_started_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workout_sessions');
    }
};