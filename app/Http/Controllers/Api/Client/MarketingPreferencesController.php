<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Services\Marketing\OptOutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints client pour gérer ses préférences marketing (RGPD).
 *
 *   - GET    /api/client/marketing/preferences
 *   - POST   /api/client/marketing/opt-out   { channel: email|sms|push|all }
 *   - POST   /api/client/marketing/opt-in    { channel: email|sms|push|all }
 */
class MarketingPreferencesController extends Controller
{
    public function __construct(protected OptOutService $optOut)
    {
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'preferences' => $this->optOut->preferences($request->user()),
        ]);
    }

    public function optOut(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:email,sms,push,all'],
            'reason' => ['nullable', 'string', 'max:191'],
        ]);

        $this->optOut->optOut(
            $request->user(),
            $data['channel'],
            $data['reason'] ?? null,
            $request->ip(),
        );

        return response()->json([
            'ok' => true,
            'preferences' => $this->optOut->preferences($request->user()),
        ]);
    }

    public function optIn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channel' => ['required', 'in:email,sms,push,all'],
        ]);

        $this->optOut->optIn($request->user(), $data['channel']);

        return response()->json([
            'ok' => true,
            'preferences' => $this->optOut->preferences($request->user()),
        ]);
    }
}
