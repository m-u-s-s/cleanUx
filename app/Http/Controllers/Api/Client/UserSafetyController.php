<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Safety\UserSafetyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserSafetyController extends Controller
{
    public function block(Request $request, User $user, UserSafetyService $service): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        try {
            $block = $service->block($request->user(), $user, $data['reason'] ?? null);
            return response()->json(['data' => ['blocked' => true, 'block_id' => $block->id]], 201);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'validation_failed', 'errors' => $e->errors()], 422);
        }
    }

    public function unblock(Request $request, User $user, UserSafetyService $service): JsonResponse
    {
        $service->unblock($request->user(), $user);
        return response()->json(['data' => ['blocked' => false]]);
    }

    public function report(Request $request, User $user, UserSafetyService $service): JsonResponse
    {
        $data = $request->validate([
            'category' => ['required', 'string'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'evidence' => ['nullable', 'array', 'max:10'],
        ]);
        try {
            $report = $service->report(
                $request->user(),
                $user,
                $data['category'],
                $data['description'],
                $data['evidence'] ?? [],
            );
            return response()->json(['data' => [
                'code' => $report->code,
                'status' => $report->status,
            ]], 201);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'validation_failed', 'errors' => $e->errors()], 422);
        }
    }
}
