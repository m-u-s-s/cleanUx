<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Services\WebView\WebViewTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @group Authentication
 *
 * @authenticated
 *
 * POST /api/auth/webview-ticket
 *
 * Issues a single-use handoff URL the mobile WebView opens to land in an
 * authenticated web session at the requested internal path.
 */
class WebViewAuthController extends Controller
{
    public function __construct(private readonly WebViewTicketService $tickets) {}

    public function ticket(Request $request): JsonResponse
    {
        $data = $request->validate([
            'target_path' => ['required', 'string', 'max:2000'],
            'device_id' => ['nullable', 'string', 'max:255'],
        ]);

        $path = $this->sanitizeInternalPath($data['target_path']);
        $ticket = $this->tickets->issue($request->user(), $data['device_id'] ?? 'unknown', $path);

        return response()->json([
            'ok' => true,
            'url' => url('/m/enter').'?ticket='.$ticket,
        ]);
    }

    /**
     * Reject anything that is not a same-origin absolute path. Prevents the
     * handoff from being abused as an open redirect.
     */
    private function sanitizeInternalPath(string $path): string
    {
        if (
            ! str_starts_with($path, '/')
            || str_starts_with($path, '//')
            || str_contains($path, '://')
            || str_contains($path, "\n")
            || str_contains($path, "\r")
        ) {
            throw ValidationException::withMessages([
                'target_path' => 'target_path must be an internal absolute path.',
            ]);
        }

        return $path;
    }
}
