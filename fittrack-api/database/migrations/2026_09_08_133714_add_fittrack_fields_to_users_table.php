<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('user');
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->string('weight_unit', 5)->default('kg');
            $table->string('theme', 10)->default('system');
            $table->string('workout_preference', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role',
                'timezone',
                'weight_unit',
                'theme',
                'workout_preference',
            ]);
        });
    }
};