<?php

namespace App\Services\Integrations;

use App\Models\GoogleCalendarConnection;
use App\Models\Parametre;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use RuntimeException;

class GoogleCalendarOAuthService
{
    private const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const USERINFO_ENDPOINT = 'https://www.googleapis.com/oauth2/v2/userinfo';

    public function __construct(
        private readonly Client $http = new Client(['timeout' => 20])
    ) {
    }

    public function isConfigured(): bool
    {
        return filled($this->clientId())
            && filled($this->clientSecret())
            && filled($this->redirectUri());
    }

    public function redirectUri(): string
    {
        return (string) Parametre::getValeur(
            'google_calendar_redirect_uri',
            config('app.url') . '/integrations/google-agenda/callback'
        );
    }

    public function clientId(): string
    {
        return (string) Parametre::getValeur('google_calendar_client_id', '');
    }

    public function clientSecret(): string
    {
        return (string) Parametre::getValeur('google_calendar_client_secret', '');
    }

    public function scopes(): string
    {
        return trim((string) Parametre::getValeur(
            'google_calendar_scopes',
            'openid email profile https://www.googleapis.com/auth/calendar'
        ));
    }

    public function buildAuthorizationUrl(string $state): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Google Calendar n’est pas configuré.');
        }

        return self::AUTH_ENDPOINT . '?' . http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'scope' => $this->scopes(),
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code): array
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Google Calendar n’est pas configuré.');
        }

        $response = $this->http->post(self::TOKEN_ENDPOINT, [
            'form_params' => [
                'code' => $code,
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
            ],
        ]);

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function refreshAccessToken(GoogleCalendarConnection $connection): GoogleCalendarConnection
    {
        if (blank($connection->refresh_token)) {
            throw new RuntimeException('Aucun refresh token Google n’est disponible pour cet utilisateur.');
        }

        $response = $this->http->post(self::TOKEN_ENDPOINT, [
            'form_params' => [
                'client_id' => $this->clientId(),
                'client_secret' => $this->clientSecret(),
                'refresh_token' => $connection->refresh_token,
                'grant_type' => 'refresh_token',
            ],
        ]);

        $payload = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        $connection->forceFill([
            'access_token' => Arr::get($payload, 'access_token', $connection->access_token),
            'token_expires_at' => now()->addSeconds((int) Arr::get($payload, 'expires_in', 3600) - 60),
            'scope' => Arr::get($payload, 'scope', $connection->scope),
            'last_sync_error' => null,
        ])->save();

        return $connection->refresh();
    }

    public function accessTokenFor(GoogleCalendarConnection $connection): string
    {
        if ($connection->tokenExpired()) {
            $connection = $this->refreshAccessToken($connection);
        }

        if (blank($connection->access_token)) {
            throw new RuntimeException('Aucun access token Google disponible.');
        }

        return (string) $connection->access_token;
    }

    public function fetchUserProfile(string $accessToken): array
    {
        $response = $this->http->get(self::USERINFO_ENDPOINT, [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'Accept' => 'application/json',
            ],
        ]);

        return json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);
    }

    public function normalizeConnectionPayload(array $tokenPayload, array $profile): array
    {
        return [
            'google_email' => Arr::get($profile, 'email'),
            'google_user_id' => Arr::get($profile, 'id'),
            'access_token' => Arr::get($tokenPayload, 'access_token'),
            'refresh_token' => Arr::get($tokenPayload, 'refresh_token'),
            'token_expires_at' => Carbon::now()->addSeconds((int) Arr::get($tokenPayload, 'expires_in', 3600) - 60),
            'calendar_id' => (string) Parametre::getValeur('google_calendar_default_calendar_id', 'primary'),
            'scope' => Arr::get($tokenPayload, 'scope', $this->scopes()),
            'sync_enabled' => true,
            'last_sync_status' => 'connected',
            'last_sync_error' => null,
            'meta' => [
                'profile' => $profile,
            ],
        ];
    }
}
