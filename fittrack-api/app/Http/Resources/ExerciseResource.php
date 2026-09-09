<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ExerciseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,

            'muscle_group' => $this->whenLoaded(
                'muscleGroup',
                fn () => $this->muscleGroup?->only([
                    'id',
                    'name',
                    'slug',
                ])
            ),

            'equipment' => $this->equipment,
            'difficulty' => $this->difficulty,
            'description' => $this->description,
            'instructions' => $this->instructions,

            'image_url' => $this->image_path
                ? Storage::disk('public')->url($this->image_path)
                : null,
        ];
    }
}