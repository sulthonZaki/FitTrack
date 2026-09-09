<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ExerciseController;
use App\Http\Controllers\Api\V1\MuscleGroupController;
use App\Http\Controllers\Api\V1\WorkoutPlanController;
use App\Http\Controllers\Api\V1\WorkoutPlanExerciseController;
use App\Http\Controllers\Api\V1\WorkoutSessionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ========================================
    // AUTHENTICATION
    // ========================================

    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    // Semua endpoint berikut membutuhkan token.
    Route::middleware('auth:sanctum')->group(function () {

        // ========================================
        // PROFILE & LOGOUT
        // ========================================

        Route::get('/me', [AuthController::class, 'me']);

        Route::post('/logout', [AuthController::class, 'logout']);

        // ========================================
        // MUSCLE GROUPS
        // ========================================

        Route::get(
            '/muscle-groups',
            [MuscleGroupController::class, 'index']
        );

        // ========================================
        // EXERCISE LIBRARY
        // ========================================

        Route::get(
            '/exercises',
            [ExerciseController::class, 'index']
        );

        Route::get(
            '/exercises/{exercise}',
            [ExerciseController::class, 'show']
        )->whereNumber('exercise');

        // ========================================
        // WORKOUT PLANS
        // ========================================

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

        // ========================================
        // EXERCISES WITHIN WORKOUT PLANS
        // ========================================

        Route::post(
            '/workout-plans/{workoutPlan}/exercises',
            [WorkoutPlanExerciseController::class, 'store']
        )->whereNumber('workoutPlan');

        Route::patch(
            '/workout-plans/{workoutPlan}/exercises/{itemId}',
            [WorkoutPlanExerciseController::class, 'update']
        )->whereNumber('workoutPlan')->whereNumber('itemId');

        Route::delete(
            '/workout-plans/{workoutPlan}/exercises/{itemId}',
            [WorkoutPlanExerciseController::class, 'destroy']
        )->whereNumber('workoutPlan')->whereNumber('itemId');

        Route::put(
            '/workout-plans/{workoutPlan}/exercise-order',
            [WorkoutPlanExerciseController::class, 'reorder']
        )->whereNumber('workoutPlan');

        // ========================================
        // WORKOUT SESSIONS
        // ========================================

        Route::post(
            '/workout-sessions',
            [WorkoutSessionController::class, 'store']
        );

        Route::get(
            '/workout-sessions/active',
            [WorkoutSessionController::class, 'active']
        );

        Route::get(
            '/workout-sessions/{workoutSession}',
            [WorkoutSessionController::class, 'show']
        )->whereNumber('workoutSession');

        Route::put(
            '/workout-sessions/{workoutSession}/cancel',
            [WorkoutSessionController::class, 'cancel']
        )->whereNumber('workoutSession');

    });
});