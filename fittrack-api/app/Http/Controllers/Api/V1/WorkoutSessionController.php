<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkoutSessionResource;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use App\Models\User;
use App\Models\WorkoutSession;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkoutSessionController extends Controller
{
    /**
     * Riwayat workout milik user.
     * Default: hanya workout yang sudah selesai.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $rules = [
            'status' => [
                'sometimes',
                Rule::in([
                    WorkoutSession::STATUS_IN_PROGRESS,
                    WorkoutSession::STATUS_COMPLETED,
                    WorkoutSession::STATUS_CANCELLED,
                ]),
            ],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ];

        if ($request->filled('date_from')) {
            $rules['date_to'][] = 'after_or_equal:date_from';
        }

        $validated = $request->validate($rules);

        $status = $validated['status']
            ?? WorkoutSession::STATUS_COMPLETED;

        $timezone = $request->user()->timezone ?? 'Asia/Jakarta';

        $dateColumn = $status === WorkoutSession::STATUS_IN_PROGRESS
            ? 'started_at'
            : 'finished_at';

        $query = $request->user()
            ->workoutSessions()
            ->where('status', $status)
            ->withCount('sessionExercises');

        if (! empty($validated['date_from'])) {
            $from = CarbonImmutable::parse(
                $validated['date_from'],
                $timezone
            )->startOfDay()->utc();

            $query->where($dateColumn, '>=', $from);
        }

        if (! empty($validated['date_to'])) {
            // Batas akhir eksklusif: awal hari berikutnya.
            $until = CarbonImmutable::parse(
                $validated['date_to'],
                $timezone
            )->startOfDay()->addDay()->utc();

            $query->where($dateColumn, '<', $until);
        }

        $sessions = $query
            ->orderByDesc($dateColumn)
            ->orderByDesc('id')
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString();

        return WorkoutSessionResource::collection($sessions)
            ->additional([
                'message' => 'Riwayat workout berhasil diambil.',
            ]);
    }

    /**
     * Memulai workout dari plan milik user.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'workout_plan_id' => ['required', 'integer', 'min:1'],
            'client_request_id' => ['required', 'uuid'],
            'user_id' => ['prohibited'],
            'status' => ['prohibited'],
            'started_at' => ['prohibited'],
        ]);

        $planId = (int) $validated['workout_plan_id'];
        $clientRequestId = strtolower($validated['client_request_id']);

        [$session, $created] = DB::transaction(
            function () use ($request, $planId, $clientRequestId) {
                // Serialisasi start workout untuk user yang sama.
                $user = User::query()
                    ->whereKey($request->user()->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $existingSession = $user->workoutSessions()
                    ->where('client_request_id', $clientRequestId)
                    ->first();

                // Retry dengan UUID yang sama mengembalikan sesi sebelumnya.
                if ($existingSession !== null) {
                    if ((int) $existingSession->workout_plan_id !== $planId) {
                        throw new HttpResponseException(
                            response()->json([
                                'message' => 'client_request_id sudah digunakan untuk workout plan lain.',
                            ], 409)
                        );
                    }

                    return [$existingSession, false];
                }

                $activeSession = $user->workoutSessions()
                    ->where(
                        'status',
                        WorkoutSession::STATUS_IN_PROGRESS
                    )
                    ->first();

                if ($activeSession !== null) {
                    throw new HttpResponseException(
                        response()->json([
                            'message' => 'Masih ada workout yang sedang berlangsung.',
                            'data' => [
                                'active_session_id' => $activeSession->id,
                            ],
                        ], 409)
                    );
                }

                $plan = $user->workoutPlans()
                    ->whereKey($planId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $items = $plan->planExercises()
                    ->reorder()
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                if ($items->isEmpty()) {
                    throw ValidationException::withMessages([
                        'workout_plan_id' => [
                            'Tambahkan minimal satu exercise sebelum memulai workout.',
                        ],
                    ]);
                }

                $exercises = Exercise::query()
                    ->whereIn(
                        'id',
                        $items->pluck('exercise_id')->unique()->all()
                    )
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $muscleGroups = MuscleGroup::query()
                    ->whereIn(
                        'id',
                        $exercises->pluck('muscle_group_id')->unique()->all()
                    )
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($items as $item) {
                    $exercise = $exercises->get($item->exercise_id);

                    if (
                        $exercise === null
                        || ! $muscleGroups->has($exercise->muscle_group_id)
                    ) {
                        throw ValidationException::withMessages([
                            'workout_plan_id' => [
                                "Exercise pada item plan {$item->id} sudah tidak tersedia. Perbarui workout plan terlebih dahulu.",
                            ],
                        ]);
                    }
                }

                $session = $user->workoutSessions()->create([
                    'workout_plan_id' => $plan->id,
                    'client_request_id' => $clientRequestId,
                    'plan_name_snapshot' => $plan->name,
                    'status' => WorkoutSession::STATUS_IN_PROGRESS,
                    'started_at' => now(),
                ]);

                // Simpan snapshot agar riwayat tidak mengikuti edit katalog.
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
            },
            3
        );

        $this->loadSession($session);

        return (new WorkoutSessionResource($session))
            ->additional([
                'message' => $created
                    ? 'Workout berhasil dimulai.'
                    : 'Workout dari request sebelumnya berhasil diambil.',
            ])
            ->response()
            ->setStatusCode($created ? 201 : 200);
    }

    /**
     * Mengambil workout yang sedang berlangsung.
     */
    public function active(Request $request): JsonResponse
    {
        $session = $request->user()
            ->workoutSessions()
            ->where('status', WorkoutSession::STATUS_IN_PROGRESS)
            ->first();

        if ($session === null) {
            return response()->json([
                'message' => 'Tidak ada workout yang sedang berlangsung.',
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

    /**
     * Detail workout beserta exercise dan set.
     */
    public function show(
        Request $request,
        string $workoutSession
    ): JsonResponse {
        $session = $request->user()
            ->workoutSessions()
            ->findOrFail($workoutSession);

        $this->loadSession($session);

        return (new WorkoutSessionResource($session))
            ->additional([
                'message' => 'Detail workout berhasil diambil.',
            ])
            ->response();
    }

    /**
     * Menyelesaikan workout.
     */
    public function complete(
        Request $request,
        string $workoutSession
    ): JsonResponse {
        $session = DB::transaction(
            function () use ($request, $workoutSession) {
                $session = $request->user()
                    ->workoutSessions()
                    ->whereKey($workoutSession)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Retry tidak mengubah waktu selesai atau durasi.
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

                $hasSets = $session->sessionExercises()
                    ->whereHas('sets')
                    ->exists();

                if (! $hasSets) {
                    throw ValidationException::withMessages([
                        'workout_session' => [
                            'Catat minimal satu set sebelum menyelesaikan workout.',
                        ],
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
            },
            3
        );

        $this->loadSession($session);

        return (new WorkoutSessionResource($session))
            ->additional([
                'message' => 'Workout berhasil diselesaikan.',
            ])
            ->response();
    }

    /**
     * Membatalkan workout.
     */
    public function cancel(
        Request $request,
        string $workoutSession
    ): JsonResponse {
        $session = DB::transaction(
            function () use ($request, $workoutSession) {
                $session = $request->user()
                    ->workoutSessions()
                    ->whereKey($workoutSession)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Retry pembatalan mengembalikan sesi yang sama.
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
            },
            3
        );

        $this->loadSession($session);

        return (new WorkoutSessionResource($session))
            ->additional([
                'message' => 'Workout berhasil dibatalkan.',
            ])
            ->response();
    }

    /**
     * Relasi untuk detail dan ringkasan workout.
     */
    private function loadSession(WorkoutSession $session): void
    {
        $session->load('sessionExercises.sets');
        $session->loadCount('sessionExercises');
    }
}