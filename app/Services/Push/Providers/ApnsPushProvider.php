<?php

namespace App\Services\Push\Providers;

use App\Services\Push\PushProviderInterface;
use App\Services\Push\PushSendRequest;
use App\Services\Push\PushSendResult;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Apple Push Notification service (APNs HTTP/2) — provider iOS natif.
 *
 * Requires:
 *   - APNS_KEY_PATH   (.p8 token-based key)
 *   - APNS_KEY_ID
 *   - APNS_TEAM_ID
 *   - APNS_BUNDLE_ID
 *   - APNS_ENVIRONMENT (production|sandbox)
 *
 * Reasons (APNs response) qui marquent token invalid :
 *   - BadDeviceToken, Unregistered, DeviceTokenNotForTopic
 */
class ApnsPushProvider implements PushProviderInterface
{
    public function name(): string
    {
        return 'apns';
    }

    public function supportsPlatforms(): array
    {
        return ['ios'];
    }

    public function send(PushSendRequest $request): PushSendResult
    {
        $bundleId = (string) Config::get('push.providers.apns.bundle_id', '');
        $keyPath = (string) Config::get('push.providers.apns.key_path', '');
        $keyId = (string) Config::get('push.providers.apns.key_id', '');
        $teamId = (string) Config::get('push.providers.apns.team_id', '');
        $env = (string) Config::get('push.providers.apns.environment', 'production');

        if (! $bundleId || ! $keyPath || ! $keyId || ! $teamId || ! file_exists($keyPath)) {
            return PushSendResult::failed('APNs configuration incomplete', 'apns_config');
        }

        try {
            $jwt = $this->buildProviderToken($keyPath, $keyId, $teamId);
        } catch (\Throwable $e) {
            Log::error('ApnsPushProvider::buildProviderToken failed', ['error' => $e->getMessage()]);
            return PushSendResult::failed('APNs auth failed: ' . $e->getMessage(), 'apns_auth');
        }

        $host = $env === 'sandbox'
            ? 'https://api.sandbox.push.apple.com'
            : 'https://api.push.apple.com';

        $url = "{$host}/3/device/" . $request->token;

        $priority = Config::get('push.categories.' . $request->category . '.priority', 'high');
        $apnsPriority = $priority === 'high' ? 10 : 5;

        $payload = [
            'aps' => [
                'alert' => [
                    'title' => $request->title,
                    'body' => $request->body,
                ],
                'sound' => 'default',
            ],
        ];

        if (! empty($request->data)) {
            $payload['data'] = $request->data;
        }

        $response = Http::withHeaders([
            'authorization' => 'bearer ' . $jwt,
            'apns-topic' => $bundleId,
            'apns-push-type' => 'alert',
            'apns-priority' => (string) $apnsPriority,
        ])->withOptions(['version' => 2.0])
            ->post($url, $payload);

        if ($response->successful() || $response->status() === 200) {
            $externalId = $response->header('apns-id') ?: ('apns_' . uniqid());
            return PushSendResult::accepted($externalId, 'sent', ['status' => $response->status()]);
        }

        $reason = $response->json('reason') ?? 'unknown';
        $tokenInvalid = in_array($reason, [
            'BadDeviceToken',
            'Unregistered',
            'DeviceTokenNotForTopic',
        ], true);

        return PushSendResult::failed(
            $reason,
            'apns_' . strtolower($reason),
            tokenInvalid: $tokenInvalid,
            raw: $response->json() ?? [],
        );
    }

    protected function buildProviderToken(string $keyPath, string $keyId, string $teamId): string
    {
        $now = time();
        $header = ['alg' => 'ES256', 'kid' => $keyId, 'typ' => 'JWT'];
        $claims = ['iss' => $teamId, 'iat' => $now];

        $segments = [
            rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '='),
            rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '='),
        ];
        $signingInput = implode('.', $segments);

        $privateKey = openssl_pkey_get_private((string) file_get_contents($keyPath));
        if (! $privateKey) {
            throw new \RuntimeException('APNs .p8 key load failed');
        }

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('APNs JWT signing failed');
        }

        $segments[] = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return implode('.', $segments);
    }
}
