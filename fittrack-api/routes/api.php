<?php

use App\Http\Controllers\Api\V1\Admin\ExerciseController as AdminExerciseController;
use App\Http\Controllers\Api\V1\Admin\MuscleGroupController as AdminMuscleGroupController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ExerciseController;
use App\Http\Controllers\Api\V1\MuscleGroupController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProgressController;
use App\Http\Controllers\Api\V1\StatisticsController;
use App\Http\Controllers\Api\V1\WorkoutPlanController;
use App\Http\Controllers\Api\V1\WorkoutPlanExerciseController;
use App\Http\Controllers\Api\V1\WorkoutSessionController;
use App\Http\Controllers\Api\V1\WorkoutSetController;
use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Authentication
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        // Profile
        Route::get('/me', [AuthController::class, 'me']);

        Route::patch('/me', [ProfileController::class, 'update'])
            ->middleware('throttle:10,1');

        Route::post('/logout', [AuthController::class, 'logout']);

        // Exercise Library
        Route::get('/muscle-groups', [
            MuscleGroupController::class, 'index',
        ]);

        Route::get('/exercises', [
            ExerciseController::class, 'index',
        ]);

        Route::get('/exercises/{exercise}', [
            ExerciseController::class, 'show',
        ])->whereNumber('exercise');

        // Workout Plans
        Route::get('/workout-plans', [
            WorkoutPlanController::class, 'index',
        ]);

        Route::post('/workout-plans', [
            WorkoutPlanController::class, 'store',
        ]);

        Route::get('/workout-plans/{workoutPlan}', [
            WorkoutPlanController::class, 'show',
        ])->whereNumber('workoutPlan');

        Route::put('/workout-plans/{workoutPlan}', [
            WorkoutPlanController::class, 'update',
        ])->whereNumber('workoutPlan');

        Route::delete('/workout-plans/{workoutPlan}', [
            WorkoutPlanController::class, 'destroy',
        ])->whereNumber('workoutPlan');

        // Exercises dalam Workout Plan
        Route::post('/workout-plans/{workoutPlan}/exercises', [
            WorkoutPlanExerciseController::class, 'store',
        ])->whereNumber('workoutPlan');

        Route::patch('/workout-plans/{workoutPlan}/exercises/{itemId}', [
            WorkoutPlanExerciseController::class, 'update',
        ])->whereNumber(['workoutPlan', 'itemId']);

        Route::delete('/workout-plans/{workoutPlan}/exercises/{itemId}', [
            WorkoutPlanExerciseController::class, 'destroy',
        ])->whereNumber(['workoutPlan', 'itemId']);

        Route::put('/workout-plans/{workoutPlan}/exercise-order', [
            WorkoutPlanExerciseController::class, 'reorder',
        ])->whereNumber('workoutPlan');

        // Workout Sessions
        Route::get('/workout-sessions', [
            WorkoutSessionController::class, 'index',
        ]);

        Route::post('/workout-sessions', [
            WorkoutSessionController::class, 'store',
        ]);

        Route::get('/workout-sessions/active', [
            WorkoutSessionController::class, 'active',
        ]);

        Route::get('/workout-sessions/{workoutSession}', [
            WorkoutSessionController::class, 'show',
        ])->whereNumber('workoutSession');

        Route::put('/workout-sessions/{workoutSession}/cancel', [
            WorkoutSessionController::class, 'cancel',
        ])->whereNumber('workoutSession');

        Route::put('/workout-sessions/{workoutSession}/complete', [
            WorkoutSessionController::class, 'complete',
        ])->whereNumber('workoutSession');

        // Workout Sets
        Route::put(
            '/workout-sessions/{workoutSession}/exercises/{sessionExerciseId}/sets/{setNumber}',
            [WorkoutSetController::class, 'upsert']
        )->whereNumber([
            'workoutSession',
            'sessionExerciseId',
            'setNumber',
        ]);

        Route::delete(
            '/workout-sessions/{workoutSession}/exercises/{sessionExerciseId}/sets/{setNumber}',
            [WorkoutSetController::class, 'destroy']
        )->whereNumber([
            'workoutSession',
            'sessionExerciseId',
            'setNumber',
        ]);

        // Progress dan Statistics
        Route::get('/progress', [
            ProgressController::class, 'index',
        ]);

        Route::get('/progress/{exercise}', [
            ProgressController::class, 'show',
        ])->whereNumber('exercise');

        Route::get('/statistics', [
            StatisticsController::class, 'index',
        ]);

        // Admin
        Route::prefix('admin')
            ->middleware(EnsureUserIsAdmin::class)
            ->group(function () {
                // Muscle Groups
                Route::get('/muscle-groups', [
                    AdminMuscleGroupController::class, 'index',
                ]);

                Route::post('/muscle-groups', [
                    AdminMuscleGroupController::class, 'store',
                ]);

                Route::get('/muscle-groups/{muscleGroup}', [
                    AdminMuscleGroupController::class, 'show',
                ])->whereNumber('muscleGroup');

                Route::put('/muscle-groups/{muscleGroup}', [
                    AdminMuscleGroupController::class, 'update',
                ])->whereNumber('muscleGroup');

                Route::delete('/muscle-groups/{muscleGroup}', [
                    AdminMuscleGroupController::class, 'destroy',
                ])->whereNumber('muscleGroup');

                // Exercises
                Route::get('/exercises', [
                    AdminExerciseController::class, 'index',
                ]);

                Route::post('/exercises', [
                    AdminExerciseController::class, 'store',
                ]);

                Route::get('/exercises/{exercise}', [
                    AdminExerciseController::class, 'show',
                ])->whereNumber('exercise');

                Route::put('/exercises/{exercise}', [
                    AdminExerciseController::class, 'update',
                ])->whereNumber('exercise');

                Route::delete('/exercises/{exercise}', [
                    AdminExerciseController::class, 'destroy',
                ])->whereNumber('exercise');

                Route::post('/exercises/{exercise}/image', [
                    AdminExerciseController::class, 'uploadImage',
                ])->whereNumber('exercise')
                    ->middleware('throttle:10,1');
            });
    });
});