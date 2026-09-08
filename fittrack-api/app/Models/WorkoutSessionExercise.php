<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkoutSessionExercise extends Model
{
    use HasFactory;

    protected $fillable = [
        'exercise_id',
        'exercise_name_snapshot',
        'muscle_group_name_snapshot',
        'equipment_snapshot',
        'sort_order',
        'target_sets',
        'target_reps',
        'target_weight_kg',
        'rest_seconds',
        'notes',
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

    public function workoutSession(): BelongsTo
    {
        return $this->belongsTo(WorkoutSession::class);
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class)->withTrashed();
    }

    public function sets(): HasMany
    {
        return $this->hasMany(WorkoutSet::class)
            ->orderBy('set_number');
    }
}