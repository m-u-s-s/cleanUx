<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserThemeController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme_preference' => 'required|string|in:light,dark,auto',
        ]);

        $user = $request->user();
        abort_unless($user, 401);

        $settings = $user->settings ?? [];
        $settings['theme_preference'] = $validated['theme_preference'];
        $user->settings = $settings;
        $user->save();

        return response()->json([
            'ok' => true,
            'theme_preference' => $validated['theme_preference'],
        ]);
    }
}
