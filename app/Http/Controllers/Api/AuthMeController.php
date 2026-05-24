<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/auth/me
 *
 * Returns the currently authenticated user. Extracted from a closure so
 * that this route is compatible with `php artisan route:cache`.
 */
class AuthMeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
