<?php

namespace App\Http\Controllers\Api\Provider;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Provider — Profile
 * @authenticated
 */
class ProviderProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'   => 'sometimes|string|max:255',
            'phone'  => 'sometimes|nullable|string|max:30',
            'locale' => 'sometimes|string|in:fr,nl,en',
        ]);

        $request->user()->update($data);

        return response()->json(['ok' => true, 'user' => $request->user()->fresh()]);
    }
}
