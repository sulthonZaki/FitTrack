<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
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

            'exercises' => WorkoutSessionExerciseResource::collection(
                $this->whenLoaded('sessionExercises')
            ),
        ];
    }
}