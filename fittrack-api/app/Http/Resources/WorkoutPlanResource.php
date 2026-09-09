<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'scheduled_days' => $this->scheduled_days,

            'exercise_count' => $this->whenCounted('planExercises'),

            'exercises' => WorkoutPlanExerciseResource::collection(
                $this->whenLoaded('planExercises')
            ),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}