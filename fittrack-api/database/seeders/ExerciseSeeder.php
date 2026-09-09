<?php

namespace Database\Seeders;

use App\Models\Exercise;
use App\Models\MuscleGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExerciseSeeder extends Seeder
{
    public function run(): void
    {
        $exercises = [
            [
                'muscle_slug' => 'chest',
                'name' => 'Bench Press',
                'slug' => 'bench-press',
                'equipment' => 'barbell',
                'difficulty' => 'intermediate',
                'description' => 'Latihan dorong menggunakan barbell untuk otot dada.',
                'instructions' => [
                    'Berbaring di bench dengan kaki menapak lantai.',
                    'Pegang barbell dengan kedua tangan dan stabilkan posisi tubuh.',
                    'Turunkan barbell secara terkontrol ke arah dada.',
                    'Dorong barbell kembali ke posisi awal.',
                ],
            ],
            [
                'muscle_slug' => 'back',
                'name' => 'Lat Pulldown',
                'slug' => 'lat-pulldown',
                'equipment' => 'machine',
                'difficulty' => 'beginner',
                'description' => 'Latihan tarik menggunakan mesin untuk otot punggung.',
                'instructions' => [
                    'Duduk dan sesuaikan bantalan penahan paha.',
                    'Pegang bar dengan kedua tangan.',
                    'Tarik bar ke arah dada bagian atas tanpa mengayunkan tubuh.',
                    'Kembalikan bar ke posisi awal secara terkontrol.',
                ],
            ],
            [
                'muscle_slug' => 'shoulders',
                'name' => 'Dumbbell Shoulder Press',
                'slug' => 'dumbbell-shoulder-press',
                'equipment' => 'dumbbell',
                'difficulty' => 'intermediate',
                'description' => 'Latihan dorong vertikal menggunakan dumbbell untuk otot bahu.',
                'instructions' => [
                    'Duduk dengan punggung tersangga dan kaki menapak lantai.',
                    'Posisikan dumbbell di sekitar tinggi bahu.',
                    'Dorong dumbbell ke atas dengan gerakan terkontrol.',
                    'Turunkan kembali ke posisi awal.',
                ],
            ],
            [
                'muscle_slug' => 'biceps',
                'name' => 'Dumbbell Bicep Curl',
                'slug' => 'dumbbell-bicep-curl',
                'equipment' => 'dumbbell',
                'difficulty' => 'beginner',
                'description' => 'Latihan menekuk siku menggunakan dumbbell untuk otot biceps.',
                'instructions' => [
                    'Berdiri tegak sambil memegang dumbbell di sisi tubuh.',
                    'Jaga siku tetap dekat dengan tubuh.',
                    'Tekuk siku untuk mengangkat dumbbell tanpa mengayunkan badan.',
                    'Turunkan dumbbell secara perlahan.',
                ],
            ],
            [
                'muscle_slug' => 'triceps',
                'name' => 'Cable Tricep Pushdown',
                'slug' => 'cable-tricep-pushdown',
                'equipment' => 'cable',
                'difficulty' => 'beginner',
                'description' => 'Latihan meluruskan siku menggunakan kabel untuk otot triceps.',
                'instructions' => [
                    'Berdiri menghadap mesin kabel dan pegang attachment.',
                    'Posisikan siku dekat dengan sisi tubuh.',
                    'Dorong attachment ke bawah dengan meluruskan siku.',
                    'Kembalikan ke posisi awal secara terkontrol.',
                ],
            ],
            [
                'muscle_slug' => 'legs',
                'name' => 'Bodyweight Squat',
                'slug' => 'bodyweight-squat',
                'equipment' => 'bodyweight',
                'difficulty' => 'beginner',
                'description' => 'Latihan squat tanpa beban eksternal untuk otot tungkai.',
                'instructions' => [
                    'Berdiri dengan kaki sekitar selebar bahu.',
                    'Tekuk lutut dan pinggul untuk menurunkan tubuh.',
                    'Jaga telapak kaki menapak dan lutut mengikuti arah jari kaki.',
                    'Berdiri kembali secara terkontrol.',
                ],
            ],
            [
                'muscle_slug' => 'abs',
                'name' => 'Crunch',
                'slug' => 'crunch',
                'equipment' => 'bodyweight',
                'difficulty' => 'beginner',
                'description' => 'Latihan mengangkat bahu dari lantai untuk otot perut.',
                'instructions' => [
                    'Berbaring dengan lutut ditekuk dan kaki menapak lantai.',
                    'Letakkan tangan menyilang di depan dada.',
                    'Kontraksikan otot perut untuk mengangkat bahu sedikit dari lantai.',
                    'Turunkan bahu kembali secara perlahan.',
                ],
            ],
        ];

        DB::transaction(function () use ($exercises) {
            foreach ($exercises as $exercise) {
                // Pertahankan exercise yang sudah ada, termasuk yang diarsipkan.
                $exists = Exercise::withTrashed()
                    ->where('slug', $exercise['slug'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                $muscleGroup = MuscleGroup::query()
                    ->where('slug', $exercise['muscle_slug'])
                    ->first();

                if (! $muscleGroup) {
                    throw new RuntimeException(
                        "Muscle group aktif tidak ditemukan: {$exercise['muscle_slug']}"
                    );
                }

                Exercise::create([
                    'muscle_group_id' => $muscleGroup->id,
                    'name' => $exercise['name'],
                    'slug' => $exercise['slug'],
                    'equipment' => $exercise['equipment'],
                    'difficulty' => $exercise['difficulty'],
                    'description' => $exercise['description'],
                    'instructions' => $exercise['instructions'],
                    'image_path' => null,
                ]);
            }
        });
    }
}