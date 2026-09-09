<?php

namespace Database\Seeders;

use App\Models\MuscleGroup;
use Illuminate\Database\Seeder;

class MuscleGroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'name' => 'Chest',
                'slug' => 'chest',
                'description' => 'Kelompok otot dada.',
            ],
            [
                'name' => 'Back',
                'slug' => 'back',
                'description' => 'Kelompok otot punggung.',
            ],
            [
                'name' => 'Shoulders',
                'slug' => 'shoulders',
                'description' => 'Kelompok otot bahu.',
            ],
            [
                'name' => 'Biceps',
                'slug' => 'biceps',
                'description' => 'Kelompok otot lengan atas bagian depan.',
            ],
            [
                'name' => 'Triceps',
                'slug' => 'triceps',
                'description' => 'Kelompok otot lengan atas bagian belakang.',
            ],
            [
                'name' => 'Legs',
                'slug' => 'legs',
                'description' => 'Kelompok otot tungkai.',
            ],
            [
                'name' => 'Abs',
                'slug' => 'abs',
                'description' => 'Kelompok otot perut.',
            ],
        ];

        foreach ($groups as $group) {
            MuscleGroup::withTrashed()->firstOrCreate(
                ['slug' => $group['slug']],
                [
                    'name' => $group['name'],
                    'description' => $group['description'],
                ]
            );
        }
    }
}