<?php

namespace App\Services\Push\Providers;

use App\Services\Push\PushProviderInterface;
use App\Services\Push\PushSendRequest;
use App\Services\Push\PushSendResult;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Firebase Cloud Messaging (FCM v1 HTTP API) provider.
 *
 * Requires:
 *   - FCM_CREDENTIALS_PATH (service account JSON)
 *   - FCM_PROJECT_ID
 *
 * Token errors that mean "delete this token from DB":
 *   - UNREGISTERED, NOT_FOUND, INVALID_ARGUMENT (token format)
 */
class FcmPushProvider implements PushProviderInterface
{
    public function name(): string
    {
        return 'fcm';
    }

    public function supportsPlatforms(): array
    {
        return ['ios', 'android', 'web'];
    }

    public function send(PushSendRequest $request): PushSendResult
    {
        $projectId = (string) Config::get('push.providers.fcm.project_id', '');
        if ($projectId === '') {
            return PushSendResult::failed('FCM project_id missing', 'fcm_config');
        }

        try {
            $accessToken = $this->getAccessToken();
        } catch (\Throwable $e) {
            Log::error('FcmPushProvider::getAccessToken failed', ['error' => $e->getMessage()]);
            return PushSendResult::failed('FCM auth failed: ' . $e->getMessage(), 'fcm_auth');
        }

        $payload = $this->buildPayload($request);

        $response = Http::withToken($accessToken)
            ->timeout((int) Config::get('push.providers.fcm.http_timeout', 10))
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => $payload,
            ]);

        if ($response->successful()) {
            $body = $response->json();
            return PushSendResult::accepted(
                $body['name'] ?? ('fcm_' . uniqid()),
                'sent',
                $body,
            );
        }

        $errorCode = $response->json('error.details.0.errorCode')
            ?? $response->json('error.status')
            ?? 'fcm_unknown';

        $tokenInvalid = in_array($errorCode, [
            'UNREGISTERED',
            'NOT_FOUND',
            'INVALID_ARGUMENT',
        ], true);

        return PushSendResult::failed(
            $response->json('error.message') ?? 'FCM error',
            (string) $errorCode,
            tokenInvalid: $tokenInvalid,
            raw: $response->json() ?? [],
        );
    }

    protected function buildPayload(PushSendRequest $request): array
    {
        $notification = [];
        if ($request->title) {
            $notification['title'] = $request->title;
        }
        $notification['body'] = $request->body;

        $message = [
            'token' => $request->token,
            'notification' => $notification,
        ];

        if (! empty($request->data)) {
            $message['data'] = array_map('strval', $request->data);
        }

        $priority = Config::get('push.categories.' . $request->category . '.priority', 'high');
        $message['android'] = [
            'priority' => $priority === 'high' ? 'high' : 'normal',
        ];

        return $message;
    }

    /**
     * Obtain an OAuth2 access token for FCM HTTP v1.
     * Caches in-memory for the request; in prod use Cache::remember.
     */
    protected function getAccessToken(): string
    {
        $credentialsPath = (string) Config::get('push.providers.fcm.credentials_path', '');
        if (! $credentialsPath || ! file_exists($credentialsPath)) {
            throw new \RuntimeException('FCM credentials file not found: ' . $credentialsPath);
        }

        // In production, prefer google/auth ServiceAccountCredentials.
        // Minimal JWT-bearer flow inline below to avoid hard dependency.
        $sa = json_decode((string) file_get_contents($credentialsPath), true);
        if (! is_array($sa) || ! isset($sa['client_email'], $sa['private_key'])) {
            throw new \RuntimeException('FCM credentials malformed');
        }

        $now = time();
        $jwt = $this->signJwt([
            'iss' => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ], $sa['private_key']);

        $resp = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $resp->successful() || ! $resp->json('access_token')) {
            throw new \RuntimeException('FCM token exchange failed: ' . $resp->body());
        }

        return (string) $resp->json('access_token');
    }

    protected function signJwt(array $claims, string $privateKey): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $segments = [
            rtrim(strtr(base64_encode(json_encode($header)), '+/', '-_'), '='),
            rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '='),
        ];

        $signingInput = implode('.', $segments);

        $signature = '';
        if (! openssl_sign($signingInput, $signature, $privateKey, 'sha256WithRSAEncryption')) {
            throw new \RuntimeException('JWT signing failed');
        }

        $segments[] = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return implode('.', $segments);
    }
}
