<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkoutPlanExercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'exercise_id',
        'sort_order',
        'target_sets',
        'target_reps',
        'target_weight_kg',
        'rest_seconds',
        'notes',
    ];

    protected $attributes = [
        'rest_seconds' => 90,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'target_sets' => 'integer',
            'target_reps' => 'integer',
            'target_weight_kg' => 'decimal:3',
            'rest_seconds' => 'integer',
        ];
    }

    public function workoutPlan(): BelongsTo
    {
        return $this->belongsTo(WorkoutPlan::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class)->withTrashed();
    }
}