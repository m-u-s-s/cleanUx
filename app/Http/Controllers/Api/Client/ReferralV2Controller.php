<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Services\Promotion\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @group Client — Referrals v2
 * @authenticated
 *
 * Referral V2 — Viral sharing endpoints.
 *
 * Endpoints (all require auth:sanctum):
 *   GET  /client/referral/my-code  → code + invite URL + share message
 *   GET  /client/referral/stats    → full stats + tier progress
 *   POST /client/referral/share    → localised share message
 */
class ReferralV2Controller extends Controller
{
    public function __construct(private readonly ReferralService $service) {}

    /**
     * Returns the authenticated user's referral code + shareable invite URL
     * with a pre-filled share message in their locale.
     */
    public function myCode(Request $request): JsonResponse
    {
        $locale = $request->user()->locale ?? 'fr';
        $data = $this->service->getShareData($request->user(), $locale);

        return response()->json(['data' => $data]);
    }

    /**
     * Returns aggregated referral statistics + tier progress.
     */
    public function stats(Request $request): JsonResponse
    {
        $locale = $request->user()->locale ?? 'fr';
        $data = $this->service->getShareData($request->user(), $locale);

        return response()->json([
            'data' => [
                'referral_code' => $data['referral_code'],
                'invite_url' => $data['invite_url'],
                'rewards' => $data['rewards'],
                'stats' => $data['stats'],
                'current_tier' => $data['current_tier'],
                'next_tier' => $data['next_tier'],
            ],
        ]);
    }

    /**
     * Returns a localised share message for the requested locale.
     * Accepts: { "locale": "fr|nl|en" }
     */
    public function share(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['sometimes', 'string', 'in:fr,nl,en'],
        ]);

        $locale = $validated['locale'] ?? $request->user()->locale ?? 'fr';
        $data = $this->service->getShareData($request->user(), $locale);

        return response()->json([
            'data' => [
                'referral_code' => $data['referral_code'],
                'invite_url' => $data['invite_url'],
                'share_message' => $data['share_message'],
            ],
        ]);
    }
}
