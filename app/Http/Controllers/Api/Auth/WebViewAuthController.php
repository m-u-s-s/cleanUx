<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        /** @var User $user */
        $user = $request->user();
        $path = $this->validateInternalPath($data['target_path']);
        $tokenId = $user->currentAccessToken()->id;
        $ticket = $this->tickets->issue($user, $data['device_id'] ?? 'unknown', $path, $tokenId);

        return response()->json([
            'ok' => true,
            'url' => url('/m/enter').'?ticket='.$ticket,
        ]);
    }

    /**
     * Reject anything that is not a same-origin absolute path. Guards against
     * open-redirect via protocol-relative (`//host`), backslash (`/\host`),
     * scheme (`scheme://`), control characters, and percent-encoded variants
     * of the above. Checks the raw and the URL-decoded form.
     */
    private function validateInternalPath(string $path): string
    {
        foreach ([$path, rawurldecode($path)] as $candidate) {
            if (
                ! str_starts_with($candidate, '/')
                || str_starts_with($candidate, '//')
                || str_starts_with($candidate, '/\\')
                || str_contains($candidate, '://')
                || preg_match('/[\x00-\x1F]/', $candidate) === 1
            ) {
                throw ValidationException::withMessages([
                    'target_path' => 'target_path must be an internal absolute path.',
                ]);
            }
        }

        return $path;
    }
}
