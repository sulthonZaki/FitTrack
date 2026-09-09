<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WorkoutSession;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WorkoutSetController extends Controller
{
    public function upsert(
        Request $request,
        string $workoutSession,
        string $sessionExerciseId,
        string $setNumber
    ): JsonResponse {
        [$set, $created] = DB::transaction(function () use (
            $request,
            $workoutSession,
            $sessionExerciseId,
            $setNumber
        ) {
            $session = $request->user()
                ->workoutSessions()
                ->lockForUpdate()
                ->findOrFail($workoutSession);

            // Exercise harus berada dalam sesi ini.
            $sessionExercise = $session->sessionExercises()
                ->findOrFail($sessionExerciseId);

            $this->ensureActive($session);

            $number = $this->validateSetNumber($setNumber);

            $validated = $request->validate([
                'reps' => ['required', 'integer', 'between:1,1000'],

                'weight_kg' => [
                    'required',
                    'numeric',
                    'min:0',
                    'max:99999.999',
                    'decimal:0,3',
                ],

                'completed_at' => ['prohibited'],
                'set_number' => ['prohibited'],
                'workout_session_exercise_id' => ['prohibited'],
                'user_id' => ['prohibited'],
            ]);

            // Pasangan exercise sesi + nomor set menentukan satu hasil.
            $set = $sessionExercise->sets()->firstOrNew([
                'set_number' => $number,
            ]);

            $created = ! $set->exists;

            $set->reps = $validated['reps'];
            $set->weight_kg = $validated['weight_kg'];

            // Edit atau retry tidak mengubah waktu pencatatan awal.
            if ($created) {
                $set->completed_at = now();
            }

            $set->save();

            return [$set, $created];
        }, 3);

        return response()->json([
            'message' => $created
                ? 'Set berhasil dicatat.'
                : 'Set berhasil diperbarui.',

            'data' => [
                'id' => $set->id,
                'workout_session_exercise_id' =>
                    $set->workout_session_exercise_id,
                'set_number' => $set->set_number,
                'reps' => $set->reps,
                'weight_kg' => $set->weight_kg,
                'completed_at' => $set->completed_at
                    ?->toIso8601String(),
            ],
        ], $created ? 201 : 200);
    }

    public function destroy(
        Request $request,
        string $workoutSession,
        string $sessionExerciseId,
        string $setNumber
    ): JsonResponse {
        DB::transaction(function () use (
            $request,
            $workoutSession,
            $sessionExerciseId,
            $setNumber
        ) {
            $session = $request->user()
                ->workoutSessions()
                ->lockForUpdate()
                ->findOrFail($workoutSession);

            $sessionExercise = $session->sessionExercises()
                ->findOrFail($sessionExerciseId);

            $this->ensureActive($session);

            $number = $this->validateSetNumber($setNumber);

            $set = $sessionExercise->sets()
                ->where('set_number', $number)
                ->firstOrFail();

            $set->delete();

            // Nomor set lain tidak diubah.
        }, 3);

        return response()->json([
            'message' => 'Set berhasil dihapus.',
        ]);
    }

    private function ensureActive(WorkoutSession $session): void
    {
        if ($session->status !== WorkoutSession::STATUS_IN_PROGRESS) {
            throw new HttpResponseException(
                response()->json([
                    'message' => 'Set hanya dapat diubah saat workout masih berjalan.',
                ], 409)
            );
        }
    }

    private function validateSetNumber(string $setNumber): int
    {
        $validated = Validator::make(
            ['set_number' => $setNumber],
            ['set_number' => ['required', 'integer', 'between:1,100']]
        )->validate();

        return (int) $validated['set_number'];
    }
}