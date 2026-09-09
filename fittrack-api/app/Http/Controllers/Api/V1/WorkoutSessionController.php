<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkoutSessionResource;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\User;
use App\Models\WorkoutSession;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkoutSessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workout_plan_id' => ['required', 'integer', 'min:1'],
            'client_request_id' => ['required', 'uuid'],
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'started_at' => ['prohibited'],
        ]);

        $requestId = strtolower($validated['client_request_id']);

        [$session, $created] = DB::transaction(function () use (
            $request,
            $validated,
            $requestId
        ) {
            // Semua request start untuk user yang sama diproses bergantian.
            $user = User::query()
                ->whereKey($request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Retry request lama mengembalikan sesi yang sama.
            $existing = $user->workoutSessions()
                ->where('client_request_id', $requestId)
                ->first();

            if ($existing) {
                if (
                    (int) $existing->workout_plan_id
                    !== (int) $validated['workout_plan_id']
                ) {
                    throw new HttpResponseException(
                        response()->json([
                            'message' => 'client_request_id sudah digunakan untuk plan lain.',
                        ], 409)
                    );
                }

                return [$existing, false];
            }

            $activeSession = $user->workoutSessions()
                ->where('status', WorkoutSession::STATUS_IN_PROGRESS)
                ->first();

            if ($activeSession) {
                throw new HttpResponseException(
                    response()->json([
                        'message' => 'Masih ada workout yang sedang berjalan.',
                        'data' => [
                            'active_session_id' => $activeSession->id,
                        ],
                    ], 409)
                );
            }

            // Plan harus aktif dan dimiliki user yang login.
            $plan = $user->workoutPlans()
                ->lockForUpdate()
                ->findOrFail($validated['workout_plan_id']);

            $items = $plan->planExercises()
                ->reorder()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'workout_plan_id' => 'Tambahkan minimal satu exercise sebelum memulai workout.',
                ]);
            }

            // Kunci data katalog selama snapshot dibuat.
            $exercises = Exercise::query()
                ->whereIn('id', $items->pluck('exercise_id')->unique())
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $muscleGroups = MuscleGroup::query()
                ->whereIn(
                    'id',
                    $exercises->pluck('muscle_group_id')->unique()
                )
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $exercise = $exercises->get($item->exercise_id);

                if (
                    ! $exercise
                    || ! $muscleGroups->has($exercise->muscle_group_id)
                ) {
                    throw ValidationException::withMessages([
                        'workout_plan_id' => "Item plan ID {$item->id} memakai exercise atau kategori yang tidak aktif. Ganti atau hapus item tersebut.",
                    ]);
                }
            }

            $session = $user->workoutSessions()->create([
                'workout_plan_id' => $plan->id,
                'client_request_id' => $requestId,
                'plan_name_snapshot' => $plan->name,
                'status' => WorkoutSession::STATUS_IN_PROGRESS,
                'started_at' => now(),
            ]);

            foreach ($items->sortBy('sort_order') as $item) {
                $exercise = $exercises->get($item->exercise_id);
                $muscleGroup = $muscleGroups->get(
                    $exercise->muscle_group_id
                );

                $session->sessionExercises()->create([
                    'exercise_id' => $exercise->id,
                    'exercise_name_snapshot' => $exercise->name,
                    'muscle_group_name_snapshot' => $muscleGroup->name,
                    'equipment_snapshot' => $exercise->equipment,

                    'sort_order' => $item->sort_order,
                    'target_sets' => $item->target_sets,
                    'target_reps' => $item->target_reps,
                    'target_weight_kg' => $item->target_weight_kg,
                    'rest_seconds' => $item->rest_seconds,
                    'notes' => $item->notes,
                ]);
            }

            return [$session, true];
        }, 3);

        $this->loadSession($session);

        return (new WorkoutSessionResource($session))
            ->additional([
                'message' => $created
                    ? 'Workout berhasil dimulai.'
                    : 'Request sudah diproses. Sesi yang sama dikembalikan.',
            ])
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }

    public function active(Request $request): JsonResponse
    {
        $session = $request->user()
            ->workoutSessions()
            ->where('status', WorkoutSession::STATUS_IN_PROGRESS)
            ->first();

        if (! $session) {
            return response()->json([
                'message' => 'Tidak ada workout aktif.',
                'data' => null,
            ]);
        }

        $this->loadSession($session);

        return (new WorkoutSessionResource($session))
            ->additional([
                'message' => 'Workout aktif berhasil diambil.',
            ])
            ->response();
    }

    public function show(
        Request $request,
        string $workoutSession
    ): WorkoutSessionResource {
        $session = $request->user()
            ->workoutSessions()
            ->findOrFail($workoutSession);

        $this->loadSession($session);

        return (new WorkoutSessionResource($session))
            ->additional([
                'message' => 'Detail workout berhasil diambil.',
            ]);
    }

    public function cancel(
        Request $request,
        string $workoutSession
    ): WorkoutSessionResource {
        $session = DB::transaction(function () use (
            $request,
            $workoutSession
        ) {
            $session = $request->user()
                ->workoutSessions()
                ->lockForUpdate()
                ->findOrFail($workoutSession);

            // Retry pembatalan tidak mengubah waktu selesai.
            if ($session->status === WorkoutSession::STATUS_CANCELLED) {
                return $session;
            }

            if ($session->status !== WorkoutSession::STATUS_IN_PROGRESS) {
                throw new HttpResponseException(
                    response()->json([
                        'message' => 'Workout yang sudah selesai tidak dapat dibatalkan.',
                    ], 409)
                );
            }

            $finishedAt = now();

            $session->update([
                'status' => WorkoutSession::STATUS_CANCELLED,
                'finished_at' => $finishedAt,
                'duration_seconds' => max(
                    0,
                    (int) $session->started_at->diffInSeconds($finishedAt)
                ),
            ]);

            return $session;
        }, 3);

        $this->loadSession($session);

        return (new WorkoutSessionResource($session))
            ->additional([
                'message' => 'Workout dibatalkan.',
            ]);
    }

    public function complete(
    Request $request,
    string $workoutSession
): WorkoutSessionResource {
    $session = DB::transaction(function () use (
        $request,
        $workoutSession
    ) {
        $session = $request->user()
            ->workoutSessions()
            ->lockForUpdate()
            ->findOrFail($workoutSession);

        // Retry complete mengembalikan sesi yang sama.
        if ($session->status === WorkoutSession::STATUS_COMPLETED) {
            return $session;
        }

        if ($session->status !== WorkoutSession::STATUS_IN_PROGRESS) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'Workout yang dibatalkan tidak dapat diselesaikan.',
                ], 409)
            );
        }

        $hasCompletedSet = $session->sessionExercises()
            ->whereHas('sets')
            ->exists();

        if (! $hasCompletedSet) {
            throw ValidationException::withMessages([
                'workout_session' =>
                    'Catat minimal satu set sebelum menyelesaikan workout.',
            ]);
        }

        $finishedAt = now();

        $session->update([
            'status' => WorkoutSession::STATUS_COMPLETED,
            'finished_at' => $finishedAt,
            'duration_seconds' => max(
                0,
                (int) $session->started_at->diffInSeconds($finishedAt)
            ),
        ]);

        return $session;
    }, 3);

    $this->loadSession($session);

    return (new WorkoutSessionResource($session))
        ->additional([
            'message' => 'Workout berhasil diselesaikan.',
        ]);
}

    private function loadSession(WorkoutSession $session): void
    {
        $session->load('sessionExercises.sets');
        $session->loadCount('sessionExercises');
    }
}