<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MuscleGroup;
use Illuminate\Http\JsonResponse;

class MuscleGroupController extends Controller
{
    public function index(): JsonResponse
    {
        $muscleGroups = MuscleGroup::query()
            ->select('id', 'name', 'slug', 'description')
            ->withCount('exercises')
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Daftar muscle group berhasil diambil.',
            'data' => $muscleGroups,
        ]);
    }
}