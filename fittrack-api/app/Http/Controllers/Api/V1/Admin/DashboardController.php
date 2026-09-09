<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\User;
use App\Models\WorkoutSession;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $timezone = 'Asia/Jakarta';

        $now = CarbonImmutable::now($timezone);
        $todayStart = $now->startOfDay();
        $tomorrowStart = $todayStart->addDay();
        $chartStart = $todayStart->subDays(6);

        $completed = WorkoutSession::query()
            ->where('status', WorkoutSession::STATUS_COMPLETED);

        $totals = (clone $completed)
            ->selectRaw('
                COUNT(*) AS total_workouts,
                COALESCE(SUM(duration_seconds), 0) AS total_duration_seconds
            ')
            ->first();

        $today = (clone $completed)
            ->where('finished_at', '>=', $todayStart->utc())
            ->where('finished_at', '<', $tomorrowStart->utc())
            ->selectRaw('
                COUNT(*) AS workout_count,
                COUNT(DISTINCT user_id) AS active_users,
                COALESCE(SUM(duration_seconds), 0) AS duration_seconds
            ')
            ->first();

        // Nilai awal: selalu tujuh hari, termasuk hari tanpa workout.
        $daily = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $chartStart->addDays($i)->toDateString();

            $daily[$date] = [
                'date' => $date,
                'completed_workouts' => 0,
                'duration_seconds' => 0,
            ];
        }

        // Diproses bertahap agar tidak memuat semua sesi ke memori.
        $recentSessions = (clone $completed)
            ->where('finished_at', '>=', $chartStart->utc())
            ->where('finished_at', '<', $tomorrowStart->utc())
            ->select(['id', 'finished_at', 'duration_seconds'])
            ->lazyById(500);

        foreach ($recentSessions as $session) {
            $date = $session->finished_at
                ->copy()
                ->setTimezone($timezone)
                ->toDateString();

            $daily[$date]['completed_workouts']++;
            $daily[$date]['duration_seconds'] +=
                (int) $session->duration_seconds;
        }

        $userCounts = User::query()
            ->selectRaw('role, COUNT(*) AS total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return response()->json([
            'message' => 'Dashboard admin berhasil diambil.',
            'data' => [
                'timezone' => $timezone,
                'generated_at' => $now->utc()->toISOString(),

                'accounts' => [
                    'total' => (int) $userCounts->sum(),
                    'users' => (int) $userCounts->get('user', 0),
                    'admins' => (int) $userCounts->get('admin', 0),
                ],

                'catalog' => [
                    'active_exercises' => Exercise::query()
                        ->whereHas('muscleGroup')
                        ->count(),
                    'active_muscle_groups' => MuscleGroup::count(),
                ],

                'workouts' => [
                    'completed' => (int) $totals->total_workouts,
                    'in_progress' => WorkoutSession::query()
                        ->where(
                            'status',
                            WorkoutSession::STATUS_IN_PROGRESS
                        )
                        ->count(),
                    'cancelled' => WorkoutSession::query()
                        ->where(
                            'status',
                            WorkoutSession::STATUS_CANCELLED
                        )
                        ->count(),
                    'total_completed_duration_seconds' =>
                        (int) $totals->total_duration_seconds,
                ],

                'today' => [
                    'date' => $todayStart->toDateString(),
                    'completed_workouts' => (int) $today->workout_count,
                    'active_users' => (int) $today->active_users,
                    'duration_seconds' => (int) $today->duration_seconds,
                ],

                'daily_workouts' => array_values($daily),
            ],
        ]);
    }
}