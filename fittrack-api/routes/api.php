<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ExerciseController;
use App\Http\Controllers\Api\V1\MuscleGroupController;
use App\Http\Controllers\Api\V1\WorkoutPlanController;
use App\Http\Controllers\Api\V1\WorkoutPlanExerciseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // Register dan login
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    // Semua endpoint berikut membutuhkan autentikasi
    Route::middleware('auth:sanctum')->group(function () {

        // Profil dan logout
        Route::get('/me', [AuthController::class, 'me']);

        Route::post('/logout', [AuthController::class, 'logout']);

        // Katalog muscle group
        Route::get(
            '/muscle-groups',
            [MuscleGroupController::class, 'index']
        );

        // Katalog exercise
        Route::get(
            '/exercises',
            [ExerciseController::class, 'index']
        );

        Route::get(
            '/exercises/{exercise}',
            [ExerciseController::class, 'show']
        )->whereNumber('exercise');

        // Workout plan CRUD
        Route::get(
            '/workout-plans',
            [WorkoutPlanController::class, 'index']
        );

        Route::post(
            '/workout-plans',
            [WorkoutPlanController::class, 'store']
        );

        Route::get(
            '/workout-plans/{workoutPlan}',
            [WorkoutPlanController::class, 'show']
        )->whereNumber('workoutPlan');

        Route::put(
            '/workout-plans/{workoutPlan}',
            [WorkoutPlanController::class, 'update']
        )->whereNumber('workoutPlan');

        Route::delete(
            '/workout-plans/{workoutPlan}',
            [WorkoutPlanController::class, 'destroy']
        )->whereNumber('workoutPlan');

        // Tambah exercise ke plan
        Route::post(
            '/workout-plans/{workoutPlan}/exercises',
            [WorkoutPlanExerciseController::class, 'store']
        )->whereNumber('workoutPlan');

        // Ubah exercise atau target dalam plan
        Route::patch(
            '/workout-plans/{workoutPlan}/exercises/{itemId}',
            [WorkoutPlanExerciseController::class, 'update']
        )->whereNumber('workoutPlan')->whereNumber('itemId');

        // Hapus item exercise dari plan
        Route::delete(
            '/workout-plans/{workoutPlan}/exercises/{itemId}',
            [WorkoutPlanExerciseController::class, 'destroy']
        )->whereNumber('workoutPlan')->whereNumber('itemId');

        // Atur urutan seluruh item exercise dalam plan
        Route::put(
            '/workout-plans/{workoutPlan}/exercise-order',
            [WorkoutPlanExerciseController::class, 'reorder']
        )->whereNumber('workoutPlan');

    });
});