<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkoutPlanExerciseResource;
use App\Models\Exercise;
use App\Models\WorkoutPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WorkoutPlanExerciseController extends Controller
{
    private const MAX_ITEMS = 100;

    public function store(
        Request $request,
        string $workoutPlan
    ): JsonResponse {
        $item = DB::transaction(function () use ($request, $workoutPlan) {
            $plan = $this->lockPlan($request, $workoutPlan);

            if ($plan->planExercises()->count() >= self::MAX_ITEMS) {
                throw ValidationException::withMessages([
                    'exercise_id' => 'Maksimal 100 item exercise per plan.',
                ]);
            }

            $validated = $this->validateItem($request);

            $validated['sort_order'] =
                (int) $plan->planExercises()->max('sort_order') + 1;

            $item = $plan->planExercises()->create($validated);

            $plan->touch();

            return $item->load('exercise.muscleGroup');
        });

        return (new WorkoutPlanExerciseResource($item))
            ->additional([
                'message' => 'Exercise berhasil ditambahkan ke plan.',
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        Request $request,
        string $workoutPlan,
        string $itemId
    ): WorkoutPlanExerciseResource {
        $item = DB::transaction(function () use (
            $request,
            $workoutPlan,
            $itemId
        ) {
            $plan = $this->lockPlan($request, $workoutPlan);

            // Item harus benar-benar berada dalam plan ini.
            $item = $plan->planExercises()->findOrFail($itemId);

            $validated = $this->validateItem($request, true);

            $item->update($validated);

            $plan->touch();

            return $item->load('exercise.muscleGroup');
        });

        return (new WorkoutPlanExerciseResource($item))
            ->additional([
                'message' => 'Target exercise berhasil diperbarui.',
            ]);
    }

    public function destroy(
        Request $request,
        string $workoutPlan,
        string $itemId
    ): JsonResponse {
        DB::transaction(function () use ($request, $workoutPlan, $itemId) {
            $plan = $this->lockPlan($request, $workoutPlan);

            $item = $plan->planExercises()->findOrFail($itemId);

            $item->delete();

            // Rapikan urutan setelah item dihapus.
            $remainingIds = $plan->planExercises()
                ->pluck('id')
                ->all();

            $this->applyOrder($plan, $remainingIds);

            $plan->touch();
        });

        return response()->json([
            'message' => 'Exercise berhasil dihapus dari plan.',
        ]);
    }

    public function reorder(
        Request $request,
        string $workoutPlan
    ): JsonResponse {
        DB::transaction(function () use ($request, $workoutPlan) {
            $plan = $this->lockPlan($request, $workoutPlan);

            $validated = $request->validate([
                'item_ids' => [
                    'required',
                    'array',
                    'list',
                    'min:1',
                    'max:' . self::MAX_ITEMS,
                ],
                'item_ids.*' => [
                    'required',
                    'integer',
                    'min:1',
                    'distinct',
                ],
            ]);

            $requestedIds = array_map('intval', $validated['item_ids']);

            $currentIds = $plan->planExercises()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $sortedRequested = $requestedIds;
            $sortedCurrent = $currentIds;

            sort($sortedRequested);
            sort($sortedCurrent);

            // Harus memuat seluruh item plan, tanpa tambahan atau kehilangan ID.
            if ($sortedRequested !== $sortedCurrent) {
                throw ValidationException::withMessages([
                    'item_ids' => 'Kirim seluruh ID item dalam plan ini, masing-masing satu kali.',
                ]);
            }

            $this->applyOrder($plan, $requestedIds);

            $plan->touch();
        });

        return response()->json([
            'message' => 'Urutan exercise berhasil diperbarui.',
        ]);
    }

    private function lockPlan(
        Request $request,
        string $workoutPlan
    ): WorkoutPlan {
        return $request->user()
            ->workoutPlans()
            ->lockForUpdate()
            ->findOrFail($workoutPlan);
    }

    private function validateItem(
        Request $request,
        bool $updating = false
    ): array {
        $rules = [
            'exercise_id' => ['required', 'integer', 'min:1'],
            'target_sets' => ['required', 'integer', 'between:1,100'],
            'target_reps' => ['required', 'integer', 'between:1,1000'],

            'target_weight_kg' => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:99999.999',
                'decimal:0,3',
            ],

            'rest_seconds' => [
                'sometimes',
                'integer',
                'between:0,3600',
            ],

            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],

            'sort_order' => ['prohibited'],
            'workout_plan_id' => ['prohibited'],
            'user_id' => ['prohibited'],
        ];

        if ($updating) {
            foreach (['exercise_id', 'target_sets', 'target_reps'] as $field) {
                array_unshift($rules[$field], 'sometimes');
            }
        }

        $validated = $request->validate($rules);

        if (isset($validated['exercise_id'])) {
            $available = Exercise::query()
                ->whereHas('muscleGroup')
                ->whereKey($validated['exercise_id'])
                ->exists();

            if (! $available) {
                throw ValidationException::withMessages([
                    'exercise_id' => 'Exercise tidak ditemukan atau sudah diarsipkan.',
                ]);
            }
        }

        return Arr::only($validated, [
            'exercise_id',
            'target_sets',
            'target_reps',
            'target_weight_kg',
            'rest_seconds',
            'notes',
        ]);
    }

    private function applyOrder(WorkoutPlan $plan, array $itemIds): void
    {
        // Pindahkan sementara ke rentang yang terpisah.
        // Maksimal 100 item, urutan normal adalah 1–100.
        foreach (array_values($itemIds) as $index => $id) {
            $plan->planExercises()
                ->whereKey($id)
                ->update(['sort_order' => 65000 + $index]);
        }

        // Terapkan urutan akhir tanpa bentrok unique constraint.
        foreach (array_values($itemIds) as $index => $id) {
            $plan->planExercises()
                ->whereKey($id)
                ->update(['sort_order' => $index + 1]);
        }
    }
}