<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $hasExercises = $this->resource
            ->relationLoaded('sessionExercises');

        $exercises = $hasExercises
            ? $this->sessionExercises
            : collect();

        // Controller memuat sessionExercises.sets.
        $sets = $exercises->flatMap(
            fn ($exercise) => $exercise->sets
        );

        $performedExerciseCount = $exercises
            ->filter(fn ($exercise) => $exercise->sets->isNotEmpty())
            ->count();

        $volume = round(
            $sets->sum(
                fn ($set) => (float) $set->weight_kg * $set->reps
            ),
            3
        );

        return [
            'id' => $this->id,
            'workout_plan_id' => $this->workout_plan_id,
            'client_request_id' => $this->client_request_id,

            'name' => $this->plan_name_snapshot,
            'status' => $this->status,

            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'duration_seconds' => $this->duration_seconds,
            'notes' => $this->notes,

            'planned_exercise_count' => $this->whenCounted(
                'sessionExercises'
            ),

            'performed_exercise_count' => $this->when(
                $hasExercises,
                $performedExerciseCount
            ),

            'total_sets' => $this->when(
                $hasExercises,
                $sets->count()
            ),

            'total_volume_kg_reps' => $this->when(
                $hasExercises,
                $volume
            ),

            'exercises' => WorkoutSessionExerciseResource::collection(
                $this->whenLoaded('sessionExercises')
            ),
        ];
    }
}