<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExerciseResource;
use App\Models\Exercise;
use App\Models\MuscleGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class ExerciseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'muscle_group_id' => ['nullable', 'integer', 'min:1'],
            'difficulty' => [
                'nullable',
                Rule::in(['beginner', 'intermediate', 'advanced']),
            ],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ]);

        $query = Exercise::query()->with('muscleGroup');

        if (! empty($validated['search'])) {
            $query->where('name', 'like', '%' . $validated['search'] . '%');
        }

        if (! empty($validated['muscle_group_id'])) {
            $query->where('muscle_group_id', $validated['muscle_group_id']);
        }

        if (! empty($validated['difficulty'])) {
            $query->where('difficulty', $validated['difficulty']);
        }

        $exercises = $query
            ->orderBy('name')
            ->orderBy('id')
            ->paginate((int) ($validated['per_page'] ?? 15))
            ->withQueryString();

        return ExerciseResource::collection($exercises)
            ->additional([
                'message' => 'Daftar exercise berhasil diambil.',
            ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateData($request);

        $exercise = DB::transaction(function () use ($validated) {
            $this->lockMuscleGroup((int) $validated['muscle_group_id']);

            return Exercise::create($validated);
        }, 3);

        return $this->exerciseResponse(
            $exercise,
            'Exercise berhasil ditambahkan.',
            201
        );
    }

    public function show(string $exercise): JsonResponse
    {
        $record = Exercise::query()->findOrFail($exercise);

        return $this->exerciseResponse(
            $record,
            'Detail exercise berhasil diambil.'
        );
    }

    public function update(
        Request $request,
        string $exercise
    ): JsonResponse {
        $record = DB::transaction(function () use ($request, $exercise) {
            $record = Exercise::query()
                ->whereKey($exercise)
                ->lockForUpdate()
                ->firstOrFail();

            $validated = $this->validateData($request, $record);

            $groupId = (int) (
                $validated['muscle_group_id'] ?? $record->muscle_group_id
            );

            // Sinkron dengan proses penghapusan muscle group.
            $this->lockMuscleGroup($groupId);

            $record->update($validated);

            return $record;
        }, 3);

        return $this->exerciseResponse(
            $record,
            'Exercise berhasil diperbarui.'
        );
    }

    public function destroy(string $exercise): JsonResponse
    {
        DB::transaction(function () use ($exercise) {
            $record = Exercise::query()
                ->whereKey($exercise)
                ->lockForUpdate()
                ->firstOrFail();

            // Soft delete: relasi dan riwayat workout tetap tersimpan.
            $record->delete();
        }, 3);

        return response()->json([
            'message' => 'Exercise berhasil diarsipkan.',
        ]);
    }

    public function uploadImage(
        Request $request,
        string $exercise
    ): JsonResponse {
        $request->validate([
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
                'dimensions:max_width=4096,max_height=4096',
            ],
        ]);

        // Pastikan exercise tersedia sebelum menyimpan file.
        Exercise::query()->findOrFail($exercise);

        $path = $request->file('image')->store('exercises', 'public');

        if ($path === false) {
            throw new RuntimeException('Gambar gagal disimpan.');
        }

        try {
            [$record, $oldPath] = DB::transaction(
                function () use ($exercise, $path) {
                    $record = Exercise::query()
                        ->whereKey($exercise)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $oldPath = $record->image_path;

                    $record->image_path = $path;
                    $record->save();

                    return [$record, $oldPath];
                },
                3
            );
        } catch (Throwable $exception) {
            $this->deleteImageFile($path);

            throw $exception;
        }

        // Gambar lama dibersihkan setelah perubahan database berhasil.
        $this->deleteImageFile($oldPath);

        return $this->exerciseResponse(
            $record,
            'Gambar exercise berhasil diperbarui.'
        );
    }

    private function validateData(
        Request $request,
        ?Exercise $exercise = null
    ): array {
        $required = $exercise === null
            ? ['required']
            : ['sometimes', 'required'];

        $uniqueSlug = Rule::unique('exercises', 'slug');

        if ($exercise !== null) {
            $uniqueSlug->ignore($exercise);
        }

        $validated = $request->validate([
            'muscle_group_id' => [
                ...$required,
                'integer',
                'min:1',
                Rule::exists('muscle_groups', 'id')
                    ->whereNull('deleted_at'),
            ],
            'name' => [
                ...$required,
                'string',
                'max:150',
            ],
            'slug' => [
                ...$required,
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                $uniqueSlug,
            ],
            'equipment' => [
                ...$required,
                'string',
                'max:80',
            ],
            'difficulty' => [
                ...$required,
                Rule::in(['beginner', 'intermediate', 'advanced']),
            ],
            'description' => [
                ...$required,
                'string',
                'max:5000',
            ],
            'instructions' => [
                ...$required,
                'array',
                'list',
                'min:1',
                'max:30',
            ],
            'instructions.*' => [
                'required',
                'string',
                'max:1000',
            ],
            'id' => ['prohibited'],
            'image_path' => ['prohibited'],
            'image' => ['prohibited'],
            'deleted_at' => ['prohibited'],
        ]);

        $data = Arr::only($validated, [
            'muscle_group_id',
            'name',
            'slug',
            'equipment',
            'difficulty',
            'description',
            'instructions',
        ]);

        if ($data === []) {
            throw ValidationException::withMessages([
                'exercise' => [
                    'Kirim minimal satu field yang ingin diubah.',
                ],
            ]);
        }

        return $data;
    }

    private function lockMuscleGroup(int $id): void
    {
        $group = MuscleGroup::query()
            ->whereKey($id)
            ->lockForUpdate()
            ->first();

        if ($group === null) {
            throw ValidationException::withMessages([
                'muscle_group_id' => [
                    'Muscle group sudah tidak tersedia.',
                ],
            ]);
        }
    }

    private function exerciseResponse(
        Exercise $exercise,
        string $message,
        int $status = 200
    ): JsonResponse {
        $exercise->load('muscleGroup');

        return (new ExerciseResource($exercise))
            ->additional(['message' => $message])
            ->response()
            ->setStatusCode($status);
    }

    private function deleteImageFile(?string $path): void
    {
        if ($path === null) {
            return;
        }

        try {
            if (! Storage::disk('public')->delete($path)) {
                report(new RuntimeException(
                    'Gagal membersihkan gambar exercise: ' . $path
                ));
            }
        } catch (Throwable $exception) {
            // Kegagalan cleanup dicatat tanpa membatalkan update yang sukses.
            report($exception);
        }
    }
}