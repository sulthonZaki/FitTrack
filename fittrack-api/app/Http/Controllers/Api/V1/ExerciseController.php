<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class ExerciseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],

            'muscle_group_id' => [
                'nullable',
                'integer',
                Rule::exists('muscle_groups', 'id')
                    ->whereNull('deleted_at'),
            ],

            'equipment' => ['nullable', 'string', 'max:80'],

            'difficulty' => [
                'nullable',
                Rule::in([
                    'beginner',
                    'intermediate',
                    'advanced',
                ]),
            ],

            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $query = Exercise::query()
            ->with('muscleGroup:id,name,slug')
            ->whereHas('muscleGroup');

        $search = trim($validated['search'] ?? '');

        if ($search !== '') {
            $query->where('name', 'like', '%' . $search . '%');
        }

        if (isset($validated['muscle_group_id'])) {
            $query->where(
                'muscle_group_id',
                $validated['muscle_group_id']
            );
        }

        if (isset($validated['equipment'])) {
            $query->where('equipment', $validated['equipment']);
        }

        if (isset($validated['difficulty'])) {
            $query->where('difficulty', $validated['difficulty']);
        }

        $exercises = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($validated['per_page'] ?? 15)
            ->withQueryString();

        return ExerciseResource::collection($exercises)
            ->additional([
                'message' => 'Daftar exercise berhasil diambil.',
            ]);
    }

    public function show(Exercise $exercise): ExerciseResource
    {
        $exercise->load('muscleGroup:id,name,slug');

        // Jangan tampilkan exercise dari kategori yang diarsipkan.
        abort_if(
            $exercise->muscleGroup === null,
            404,
            'Exercise tidak ditemukan.'
        );

        return (new ExerciseResource($exercise))
            ->additional([
                'message' => 'Detail exercise berhasil diambil.',
            ]);
    }
}