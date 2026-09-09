<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\MuscleGroup;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MuscleGroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ]);

        $groups = MuscleGroup::query()
            ->withCount('exercises')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString();

        return response()->json([
            'message' => 'Daftar muscle group berhasil diambil.',
            'data' => $groups->items(),
            'links' => [
                'first' => $groups->url(1),
                'last' => $groups->url($groups->lastPage()),
                'prev' => $groups->previousPageUrl(),
                'next' => $groups->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $groups->currentPage(),
                'last_page' => $groups->lastPage(),
                'per_page' => $groups->perPage(),
                'total' => $groups->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateData($request);

        $group = MuscleGroup::create($validated);
        $group->loadCount('exercises');

        return response()->json([
            'message' => 'Muscle group berhasil ditambahkan.',
            'data' => $group,
        ], 201);
    }

    public function show(string $muscleGroup): JsonResponse
    {
        $group = MuscleGroup::query()
            ->withCount('exercises')
            ->findOrFail($muscleGroup);

        return response()->json([
            'message' => 'Detail muscle group berhasil diambil.',
            'data' => $group,
        ]);
    }

    public function update(
        Request $request,
        string $muscleGroup
    ): JsonResponse {
        $group = DB::transaction(function () use ($request, $muscleGroup) {
            $group = MuscleGroup::query()
                ->whereKey($muscleGroup)
                ->lockForUpdate()
                ->firstOrFail();

            $validated = $this->validateData($request, $group);

            $group->update($validated);
            $group->loadCount('exercises');

            return $group;
        }, 3);

        return response()->json([
            'message' => 'Muscle group berhasil diperbarui.',
            'data' => $group,
        ]);
    }

    public function destroy(string $muscleGroup): JsonResponse
    {
        DB::transaction(function () use ($muscleGroup) {
            $group = MuscleGroup::query()
                ->whereKey($muscleGroup)
                ->lockForUpdate()
                ->firstOrFail();

            if ($group->exercises()->exists()) {
                throw new HttpResponseException(
                    response()->json([
                        'message' => 'Muscle group masih memiliki exercise aktif. Pindahkan atau hapus exercise tersebut terlebih dahulu.',
                    ], 409)
                );
            }

            $group->delete();
        }, 3);

        return response()->json([
            'message' => 'Muscle group berhasil dihapus.',
        ]);
    }

    private function validateData(
        Request $request,
        ?MuscleGroup $group = null
    ): array {
        $uniqueName = Rule::unique('muscle_groups', 'name');
        $uniqueSlug = Rule::unique('muscle_groups', 'slug');

        if ($group !== null) {
            $uniqueName->ignore($group);
            $uniqueSlug->ignore($group);
        }

        $requiredRules = $group === null
            ? ['required']
            : ['sometimes', 'required'];

        $validated = $request->validate([
            'name' => [
                ...$requiredRules,
                'string',
                'max:80',
                $uniqueName,
            ],
            'slug' => [
                ...$requiredRules,
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $uniqueSlug,
            ],
            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:2000',
            ],
            'id' => ['prohibited'],
            'deleted_at' => ['prohibited'],
        ]);

        $data = Arr::only($validated, [
            'name',
            'slug',
            'description',
        ]);

        if ($data === []) {
            throw ValidationException::withMessages([
                'muscle_group' => [
                    'Kirim minimal satu field yang ingin diubah.',
                ],
            ]);
        }

        return $data;
    }
}