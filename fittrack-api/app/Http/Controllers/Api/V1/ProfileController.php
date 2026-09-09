<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        // Samakan format email dengan register dan login.
        if (is_string($request->input('email'))) {
            $request->merge([
                'email' => strtolower(trim($request->input('email'))),
            ]);
        }

        $user = DB::transaction(function () use ($request) {
            $user = User::query()
                ->whereKey($request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            $validated = $request->validate([
                'name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:100',
                ],
                'email' => [
                    'sometimes',
                    'required',
                    'string',
                    'email',
                    'max:255',
                    Rule::unique('users', 'email')->ignore($user),
                ],
                'timezone' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:64',
                    'timezone',
                ],
                'weight_unit' => [
                    'sometimes',
                    'required',
                    Rule::in(['kg', 'lb']),
                ],
                'theme' => [
                    'sometimes',
                    'required',
                    Rule::in(['system', 'light', 'dark']),
                ],
                'workout_preference' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:100',
                ],
                'current_password' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:72',
                ],

                // Field ini tidak boleh diubah melalui profile.
                'id' => ['prohibited'],
                'user_id' => ['prohibited'],
                'role' => ['prohibited'],
                'password' => ['prohibited'],
                'email_verified_at' => ['prohibited'],
            ]);

            $data = Arr::only($validated, [
                'name',
                'email',
                'timezone',
                'weight_unit',
                'theme',
                'workout_preference',
            ]);

            if ($data === []) {
                throw ValidationException::withMessages([
                    'profile' => [
                        'Kirim minimal satu field profile yang ingin diubah.',
                    ],
                ]);
            }

            $emailChanged = array_key_exists('email', $data)
                && $data['email'] !== $user->email;

            // Perubahan email login memerlukan password saat ini.
            if ($emailChanged) {
                $password = $validated['current_password'] ?? '';

                if (! Hash::check($password, $user->password)) {
                    throw ValidationException::withMessages([
                        'current_password' => [
                            'Masukkan password saat ini yang benar untuk mengganti email.',
                        ],
                    ]);
                }
            }

            $user->fill($data);

            if ($emailChanged) {
                $user->email_verified_at = null;
            }

            $user->save();

            return $user;
        }, 3);

        return response()->json([
            'message' => 'Profile berhasil diperbarui.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'timezone' => $user->timezone,
                'weight_unit' => $user->weight_unit,
                'theme' => $user->theme,
                'workout_preference' => $user->workout_preference,
                'created_at' => $user->created_at?->toISOString(),
            ],
        ]);
    }
}