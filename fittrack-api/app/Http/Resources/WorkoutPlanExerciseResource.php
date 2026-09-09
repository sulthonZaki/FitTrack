<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutPlanExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workout_plan_id' => $this->workout_plan_id,
            'exercise_id' => $this->exercise_id,

            'exercise' => $this->whenLoaded('exercise', function () {
                return [
                    'id' => $this->exercise->id,
                    'name' => $this->exercise->name,
                    'equipment' => $this->exercise->equipment,

                    'muscle_group' => $this->exercise
                        ->muscleGroup?->only(['id', 'name', 'slug']),

                    'is_available' => ! $this->exercise->trashed()
                        && $this->exercise->muscleGroup !== null,
                ];
            }),

            'sort_order' => $this->sort_order,
            'target_sets' => $this->target_sets,
            'target_reps' => $this->target_reps,
            'target_weight_kg' => $this->target_weight_kg,
            'rest_seconds' => $this->rest_seconds,
            'notes' => $this->notes,
        ];
    }
}