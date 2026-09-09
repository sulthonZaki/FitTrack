<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkoutPlanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class WorkoutPlanController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $plans = $request->user()
            ->workoutPlans()
            ->withCount('planExercises')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return WorkoutPlanResource::collection($plans)
            ->additional([
                'message' => 'Daftar workout plan berhasil diambil.',
            ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePlan($request);

        // Pemilik plan berasal dari user yang login.
        $plan = $request->user()
            ->workoutPlans()
            ->create($validated);

        $plan->loadCount('planExercises');

        return (new WorkoutPlanResource($plan))
            ->additional([
                'message' => 'Workout plan berhasil dibuat.',
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        Request $request,
        string $workoutPlan
    ): WorkoutPlanResource {
        $plan = $request->user()
            ->workoutPlans()
            ->withCount('planExercises')
            ->findOrFail($workoutPlan);

        return (new WorkoutPlanResource($plan))
            ->additional([
                'message' => 'Detail workout plan berhasil diambil.',
            ]);
    }

    public function update(
        Request $request,
        string $workoutPlan
    ): WorkoutPlanResource {
        $plan = DB::transaction(function () use ($request, $workoutPlan) {
            $plan = $request->user()
                ->workoutPlans()
                ->lockForUpdate()
                ->findOrFail($workoutPlan);

            $validated = $this->validatePlan($request, true);

            $plan->update($validated);
            $plan->loadCount('planExercises');

            return $plan;
        });

        return (new WorkoutPlanResource($plan))
            ->additional([
                'message' => 'Workout plan berhasil diperbarui.',
            ]);
    }

    public function destroy(
        Request $request,
        string $workoutPlan
    ): JsonResponse {
        DB::transaction(function () use ($request, $workoutPlan) {
            $plan = $request->user()
                ->workoutPlans()
                ->lockForUpdate()
                ->findOrFail($workoutPlan);

            // Model memakai SoftDeletes: hanya mengisi deleted_at.
            $plan->delete();
        });

        return response()->json([
            'message' => 'Workout plan berhasil diarsipkan.',
        ]);
    }

    private function validatePlan(
        Request $request,
        bool $updating = false
    ): array {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
            'scheduled_days' => [
                'sometimes',
                'array',
                'list',
                'max:7',
            ],
            'scheduled_days.*' => [
                'required',
                'integer',
                'between:1,7',
                'distinct',
            ],
            'user_id' => ['prohibited'],
            'exercises' => ['prohibited'],
        ];

        if ($updating) {
            array_unshift($rules['name'], 'sometimes');
        }

        $validated = $request->validate($rules);

        // Hanya tiga field ini yang boleh disimpan dari request.
        $validated = Arr::only($validated, [
            'name',
            'description',
            'scheduled_days',
        ]);

        if (array_key_exists('scheduled_days', $validated)) {
            $validated['scheduled_days'] = array_map(
                'intval',
                $validated['scheduled_days']
            );

            sort($validated['scheduled_days']);
        }

        return $validated;
    }
}