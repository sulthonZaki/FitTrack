<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkoutSession;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $timezone = $user->timezone ?: 'Asia/Jakarta';

        $now = CarbonImmutable::now($timezone);

        $weekStart = $now->startOfWeek(CarbonInterface::MONDAY);
        $weekEnd = $weekStart->addWeek();

        $monthStart = $now->startOfMonth();
        $monthEnd = $monthStart->addMonth();

        $completed = WorkoutSession::query()
            ->where('user_id', $user->id)
            ->where('status', WorkoutSession::STATUS_COMPLETED);

        $allTime = $this->summarize(clone $completed);

        $thisWeek = $this->summarize(
            (clone $completed)
                ->where('finished_at', '>=', $weekStart->utc())
                ->where('finished_at', '<', $weekEnd->utc())
        );

        $thisMonth = $this->summarize(
            (clone $completed)
                ->where('finished_at', '>=', $monthStart->utc())
                ->where('finished_at', '<', $monthEnd->utc())
        );

        // Hanya set dari sesi completed milik user ini.
        $setTotals = DB::table('workout_sets as ws')
            ->join(
                'workout_session_exercises as wse',
                'wse.id',
                '=',
                'ws.workout_session_exercise_id'
            )
            ->join(
                'workout_sessions as sessions',
                'sessions.id',
                '=',
                'wse.workout_session_id'
            )
            ->where('sessions.user_id', $user->id)
            ->where(
                'sessions.status',
                WorkoutSession::STATUS_COMPLETED
            )
            ->selectRaw(
                'COUNT(*) AS total_sets,
                 COALESCE(SUM(ws.reps * ws.weight_kg), 0.000)
                    AS total_volume_kg_reps'
            )
            ->first();

        // Grafik tujuh hari terakhir, termasuk hari ini.
        $dailyStart = $now->startOfDay()->subDays(6);
        $dailyEnd = $now->startOfDay()->addDay();

        $recentSessions = (clone $completed)
            ->where('finished_at', '>=', $dailyStart->utc())
            ->where('finished_at', '<', $dailyEnd->utc())
            ->get(['finished_at']);

        $countsByDate = $recentSessions
            ->groupBy(function ($session) use ($timezone) {
                return $session->finished_at
                    ->copy()
                    ->setTimezone($timezone)
                    ->toDateString();
            })
            ->map(fn ($sessions) => $sessions->count());

        $dailyWorkouts = [];

        for ($i = 0; $i < 7; $i++) {
            $date = $dailyStart->addDays($i)->toDateString();

            $dailyWorkouts[] = [
                'date' => $date,
                'workout_count' => (int) $countsByDate->get($date, 0),
            ];
        }

        return response()->json([
            'message' => 'Statistik berhasil diambil.',
            'data' => [
                'timezone' => $timezone,

                'total_workouts' => $allTime['workout_count'],
                'total_duration_seconds' => $allTime['duration_seconds'],

                'total_sets' => (int) $setTotals->total_sets,

                // String decimal untuk menjaga presisi hasil SQL.
                'total_volume_kg_reps' =>
                    (string) $setTotals->total_volume_kg_reps,

                'this_week' => [
                    'start_date' => $weekStart->toDateString(),
                    'end_date' => $weekEnd->subDay()->toDateString(),
                    ...$thisWeek,
                ],

                'this_month' => [
                    'start_date' => $monthStart->toDateString(),
                    'end_date' => $monthEnd->subDay()->toDateString(),
                    ...$thisMonth,
                ],

                'daily_workouts' => $dailyWorkouts,
            ],
        ]);
    }

    private function summarize(Builder $query): array
    {
        $result = $query
            ->selectRaw(
                'COUNT(*) AS workout_count,
                 COALESCE(SUM(duration_seconds), 0)
                    AS total_duration'
            )
            ->first();

        return [
            'workout_count' => (int) $result->workout_count,
            'duration_seconds' => (int) $result->total_duration,
        ];
    }
}