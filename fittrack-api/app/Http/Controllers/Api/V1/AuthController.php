<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:72',
                'confirmed',
            ],
            'device_name' => ['required', 'string', 'max:100'],
            'role' => ['prohibited'],
            'user_id' => ['prohibited'],
        ]);

        return DB::transaction(function () use ($validated) {
            $user = new User();

            $user->name = $validated['name'];
            $user->email = $validated['email'];

            // Otomatis di-hash oleh cast password pada model User.
            $user->password = $validated['password'];

            // Role ditetapkan server.
            $user->role = 'user';

            $user->save();
            $user->refresh();

            return $this->tokenResponse(
                $user,
                $validated['device_name'],
                'Registrasi berhasil.',
                201
            );
        });
    }

    public function login(Request $request): JsonResponse
    {
        $this->normalizeEmail($request);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:72'],
            'device_name' => ['required', 'string', 'max:100'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Email atau password salah.',
            ], 401);
        }

        return $this->tokenResponse(
            $user,
            $validated['device_name'],
            'Login berhasil.'
        );
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'message' => 'Profil berhasil diambil.',
            'data' => $this->userData($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        // Endpoint ini untuk logout autentikasi token mobile.
        if (! $token instanceof PersonalAccessToken) {
            return response()->json([
                'message' => 'Token autentikasi diperlukan.',
            ], 401);
        }

        $token->delete();

        return response()->json([
            'message' => 'Logout berhasil.',
        ]);
    }

    private function tokenResponse(
        User $user,
        string $deviceName,
        string $message,
        int $status = 200
    ): JsonResponse {
        $expiresAt = now()->addDays(30);

        $token = $user->createToken(
            $deviceName,
            ['*'],
            $expiresAt
        );

        return response()->json([
            'message' => $message,
            'data' => [
                'user' => $this->userData($user),
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt->toIso8601String(),
            ],
        ], $status);
    }

    private function userData(User $user): array
    {
        return $user->only([
            'id',
            'name',
            'email',
            'role',
            'timezone',
            'weight_unit',
            'theme',
            'workout_preference',
            'created_at',
        ]);
    }

    private function normalizeEmail(Request $request): void
    {
        $email = $request->input('email');

        if (is_string($email)) {
            $request->merge([
                'email' => mb_strtolower(trim($email)),
            ]);
        }
    }
}