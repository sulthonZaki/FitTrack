<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkoutSessionExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exercise_id' => $this->exercise_id,

            'exercise_name' => $this->exercise_name_snapshot,
            'muscle_group_name' => $this->muscle_group_name_snapshot,
            'equipment' => $this->equipment_snapshot,

            'sort_order' => $this->sort_order,
            'target_sets' => $this->target_sets,
            'target_reps' => $this->target_reps,
            'target_weight_kg' => $this->target_weight_kg,
            'rest_seconds' => $this->rest_seconds,
            'notes' => $this->notes,

            'sets' => $this->whenLoaded('sets', function () {
                return $this->sets->map(function ($set) {
                    return [
                        'id' => $set->id,
                        'set_number' => $set->set_number,
                        'reps' => $set->reps,
                        'weight_kg' => $set->weight_kg,
                        'completed_at' => $set->completed_at
                            ?->toIso8601String(),
                    ];
                });
            }),
        ];
    }
}