<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ExerciseController;
use App\Http\Controllers\Api\V1\MuscleGroupController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('throttle:5,1');

    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);

        Route::post('/logout', [AuthController::class, 'logout']);

        Route::get(
            '/muscle-groups',
            [MuscleGroupController::class, 'index']
        );

        Route::get(
            '/exercises',
            [ExerciseController::class, 'index']
        );

        Route::get(
            '/exercises/{exercise}',
            [ExerciseController::class, 'show']
        )->whereNumber('exercise');
    });
});