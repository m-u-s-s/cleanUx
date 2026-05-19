<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Services\Push\DeviceTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DeviceTokenController extends Controller
{
    public function __construct(protected DeviceTokenService $service)
    {
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4000'],
            'platform' => ['required', 'in:ios,android,web'],
            'provider' => ['required', 'in:fcm,apns,mock'],
            'app_version' => ['nullable', 'string', 'max:32'],
            'locale' => ['nullable', 'string', 'max:8'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'device_model' => ['nullable', 'string', 'max:64'],
        ]);

        try {
            $token = $this->service->register(
                user: $request->user(),
                token: $data['token'],
                platform: $data['platform'],
                provider: $data['provider'],
                appVersion: $data['app_version'] ?? null,
                locale: $data['locale'] ?? null,
                timezone: $data['timezone'] ?? null,
                deviceModel: $data['device_model'] ?? null,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'device_token_id' => $token->id,
            'platform' => $token->platform,
            'provider' => $token->provider,
            'preferences' => $token->preferences,
        ], 201);
    }

    public function unregister(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4000'],
        ]);

        $ok = $this->service->unregister($request->user(), $data['token']);

        return response()->json(['ok' => $ok], $ok ? 200 : 404);
    }

    public function index(Request $request): JsonResponse
    {
        $tokens = DeviceToken::query()
            ->active()
            ->forUser($request->user()->id)
            ->orderByDesc('last_used_at')
            ->get(['id', 'platform', 'provider', 'app_version', 'device_model', 'locale', 'last_used_at', 'preferences']);

        return response()->json(['data' => $tokens]);
    }

    public function updatePreferences(Request $request, DeviceToken $deviceToken): JsonResponse
    {
        if ($deviceToken->user_id !== $request->user()->id) {
            return response()->json(['ok' => false, 'error' => 'forbidden'], 403);
        }

        $data = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.transactional' => ['sometimes', 'boolean'],
            'preferences.verification' => ['sometimes', 'boolean'],
            'preferences.reminder' => ['sometimes', 'boolean'],
            'preferences.marketing' => ['sometimes', 'boolean'],
        ]);

        $updated = $this->service->updatePreferences($deviceToken, $data['preferences']);

        return response()->json([
            'ok' => true,
            'preferences' => $updated->preferences,
        ]);
    }
}
