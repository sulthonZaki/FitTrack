<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkoutSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProgressController extends Controller
{
    /**
     * Ringkasan semua exercise yang pernah dilatih.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ]);

        $progress = $this->completedSets($request)
            ->join('exercises as e', 'e.id', '=', 'wse.exercise_id')
            ->select([
                'e.id as exercise_id',
                'e.name as exercise_name',
                'e.deleted_at',
            ])
            ->selectRaw('
                COUNT(DISTINCT sessions.id) AS workout_count,
                COUNT(*) AS total_sets,
                MAX(ws.weight_kg) AS max_weight_kg,
                MAX(ws.reps) AS max_reps,
                SUM(ws.weight_kg * ws.reps) AS total_volume_kg_reps,
                MAX(sessions.finished_at) AS last_workout_at
            ')
            ->groupBy('e.id', 'e.name', 'e.deleted_at')
            ->orderByDesc('last_workout_at')
            ->orderBy('e.id')
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString();

        $progress->through(fn ($row) => [
            'exercise_id' => (int) $row->exercise_id,
            'exercise_name' => $row->exercise_name,
            'is_archived' => $row->deleted_at !== null,
            'workout_count' => (int) $row->workout_count,
            'total_sets' => (int) $row->total_sets,
            'max_weight_kg' => (string) $row->max_weight_kg,
            'max_reps' => (int) $row->max_reps,
            'total_volume_kg_reps' => (string) $row->total_volume_kg_reps,
            'last_workout_at' => $this->isoDate($row->last_workout_at),
        ]);

        return response()->json([
            'message' => 'Ringkasan progress berhasil diambil.',
            'data' => $progress->items(),
            'links' => $this->paginationLinks($progress),
            'meta' => $this->paginationMeta($progress),
        ]);
    }

    /**
     * Progress satu exercise, satu baris untuk setiap workout.
     */
    public function show(
        Request $request,
        string $exercise
    ): JsonResponse {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ]);

        $base = $this->completedSets($request)
            ->where('wse.exercise_id', $exercise);

        // Metadata berasal dari snapshot terakhir milik user ini.
        $latest = (clone $base)
            ->orderByDesc('sessions.finished_at')
            ->orderByDesc('sessions.id')
            ->orderByDesc('wse.id')
            ->first([
                'wse.exercise_id',
                'wse.exercise_name_snapshot',
                'wse.muscle_group_name_snapshot',
                'wse.equipment_snapshot',
            ]);

        if ($latest === null) {
            return response()->json([
                'message' => 'Belum ada progress untuk exercise ini.',
            ], 404);
        }

        $summary = (clone $base)
            ->selectRaw('
                COUNT(DISTINCT sessions.id) AS workout_count,
                COUNT(*) AS total_sets,
                MAX(ws.weight_kg) AS max_weight_kg,
                MAX(ws.reps) AS max_reps,
                SUM(ws.weight_kg * ws.reps) AS total_volume_kg_reps
            ')
            ->first();

        $history = (clone $base)
            ->select([
                'sessions.id as workout_session_id',
                'sessions.plan_name_snapshot as workout_name',
                'sessions.finished_at',
            ])
            ->selectRaw('
                COUNT(*) AS total_sets,
                SUM(ws.reps) AS total_reps,
                MAX(ws.weight_kg) AS max_weight_kg,
                MAX(ws.reps) AS max_reps,
                SUM(ws.weight_kg * ws.reps) AS total_volume_kg_reps
            ')
            ->groupBy(
                'sessions.id',
                'sessions.plan_name_snapshot',
                'sessions.finished_at'
            )
            ->orderByDesc('sessions.finished_at')
            ->orderByDesc('sessions.id')
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString();

        $history->through(fn ($row) => [
            'workout_session_id' => (int) $row->workout_session_id,
            'workout_name' => $row->workout_name,
            'finished_at' => $this->isoDate($row->finished_at),
            'total_sets' => (int) $row->total_sets,
            'total_reps' => (int) $row->total_reps,
            'max_weight_kg' => (string) $row->max_weight_kg,
            'max_reps' => (int) $row->max_reps,
            'total_volume_kg_reps' => (string) $row->total_volume_kg_reps,
        ]);

        return response()->json([
            'message' => 'Detail progress berhasil diambil.',
            'data' => [
                'exercise' => [
                    'id' => (int) $latest->exercise_id,
                    'name' => $latest->exercise_name_snapshot,
                    'muscle_group' => $latest->muscle_group_name_snapshot,
                    'equipment' => $latest->equipment_snapshot,
                ],
                'summary' => [
                    'workout_count' => (int) $summary->workout_count,
                    'total_sets' => (int) $summary->total_sets,
                    'max_weight_kg' => (string) $summary->max_weight_kg,
                    'max_reps' => (int) $summary->max_reps,
                    'total_volume_kg_reps' => (string) $summary->total_volume_kg_reps,
                ],
                'history' => $history->items(),
            ],
            'links' => $this->paginationLinks($history),
            'meta' => $this->paginationMeta($history),
        ]);
    }

    /**
     * Semua query progress wajib memakai scope ini.
     */
    private function completedSets(Request $request): Builder
    {
        return DB::table('workout_sets as ws')
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
            ->where('sessions.user_id', $request->user()->id)
            ->where('sessions.status', WorkoutSession::STATUS_COMPLETED);
    }

    private function isoDate(string $value): string
    {
        return CarbonImmutable::parse($value, 'UTC')->toISOString();
    }

    private function paginationLinks(
        LengthAwarePaginator $paginator
    ): array {
        return [
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];
    }

    private function paginationMeta(
        LengthAwarePaginator $paginator
    ): array {
        return [
            'current_page' => $paginator->currentPage(),
            'from' => $paginator->firstItem(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'to' => $paginator->lastItem(),
            'total' => $paginator->total(),
        ];
    }
}