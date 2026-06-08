<?php

namespace Tests\Unit;

use App\Models\GoogleCalendarConnection;
use App\Models\Parametre;
use App\Models\User;
use App\Services\Integrations\GoogleCalendarOAuthService;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class GoogleCalendarOAuthServiceCoverageBatch7Test extends TestCase
{
    use RefreshDatabase;

    /**
     * Build an OAuth service whose Guzzle client replays the queued responses.
     *
     * @param  array<int, Response>  $responses
     */
    private function makeService(array $responses = []): GoogleCalendarOAuthService
    {
        $http = new Client([
            'handler' => HandlerStack::create(new MockHandler($responses)),
        ]);

        return new GoogleCalendarOAuthService($http);
    }

    private function configureGoogle(): void
    {
        Parametre::setValeur('google_calendar_client_id', 'client-abc');
        Parametre::setValeur('google_calendar_client_secret', 'secret-xyz');
        Parametre::setValeur('google_calendar_redirect_uri', 'https://app.test/callback');
    }

    public function test_is_configured_reflects_parameter_presence(): void
    {
        $service = $this->makeService();

        $this->assertFalse($service->isConfigured());

        $this->configureGoogle();

        $this->assertTrue($service->isConfigured());
        $this->assertSame('client-abc', $service->clientId());
        $this->assertSame('secret-xyz', $service->clientSecret());
        $this->assertSame('https://app.test/callback', $service->redirectUri());
    }

    public function test_redirect_uri_and_scopes_fall_back_to_defaults(): void
    {
        $service = $this->makeService();

        $this->assertStringContainsString('/integrations/google-agenda/callback', $service->redirectUri());
        $this->assertStringContainsString('https://www.googleapis.com/auth/calendar', $service->scopes());

        Parametre::setValeur('google_calendar_scopes', '  openid email  ');
        $this->assertSame('openid email', $service->scopes());
    }

    public function test_build_authorization_url_throws_when_not_configured(): void
    {
        $this->expectException(RuntimeException::class);

        $this->makeService()->buildAuthorizationUrl('state-token');
    }

    public function test_build_authorization_url_contains_expected_parameters(): void
    {
        $this->configureGoogle();
        $service = $this->makeService();

        $url = $service->buildAuthorizationUrl('state-token');

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);
        $this->assertStringContainsString('client_id=client-abc', $url);
        $this->assertStringContainsString('state=state-token', $url);
        $this->assertStringContainsString('access_type=offline', $url);
        $this->assertStringContainsString('prompt=consent', $url);
    }

    public function test_exchange_code_throws_when_not_configured(): void
    {
        $this->expectException(RuntimeException::class);

        $this->makeService()->exchangeCode('auth-code');
    }

    public function test_exchange_code_returns_decoded_token_payload(): void
    {
        $this->configureGoogle();
        $service = $this->makeService([
            new Response(200, [], json_encode([
                'access_token' => 'at-1',
                'refresh_token' => 'rt-1',
                'expires_in' => 3600,
                'scope' => 'openid email',
            ])),
        ]);

        $payload = $service->exchangeCode('auth-code');

        $this->assertSame('at-1', $payload['access_token']);
        $this->assertSame('rt-1', $payload['refresh_token']);
    }

    public function test_refresh_access_token_throws_without_refresh_token(): void
    {
        $user = User::factory()->employe()->create();
        $connection = GoogleCalendarConnection::factory()->create([
            'user_id' => $user->id,
            'refresh_token' => null,
        ]);

        $this->expectException(RuntimeException::class);

        $this->makeService()->refreshAccessToken($connection);
    }

    public function test_refresh_access_token_updates_connection(): void
    {
        $this->configureGoogle();
        $user = User::factory()->employe()->create();
        $connection = GoogleCalendarConnection::factory()->expired()->create([
            'user_id' => $user->id,
            'refresh_token' => 'rt-existing',
            'last_sync_error' => 'previous boom',
        ]);

        $service = $this->makeService([
            new Response(200, [], json_encode([
                'access_token' => 'fresh-access-token',
                'expires_in' => 3600,
                'scope' => 'openid email profile',
            ])),
        ]);

        $updated = $service->refreshAccessToken($connection);

        $this->assertSame('fresh-access-token', $updated->access_token);
        $this->assertSame('openid email profile', $updated->scope);
        $this->assertNull($updated->last_sync_error);
        $this->assertFalse($updated->tokenExpired());
    }

    public function test_access_token_for_refreshes_when_expired(): void
    {
        $this->configureGoogle();
        $user = User::factory()->employe()->create();
        $connection = GoogleCalendarConnection::factory()->expired()->create([
            'user_id' => $user->id,
            'refresh_token' => 'rt-existing',
        ]);

        $service = $this->makeService([
            new Response(200, [], json_encode([
                'access_token' => 'refreshed-token',
                'expires_in' => 3600,
            ])),
        ]);

        $this->assertSame('refreshed-token', $service->accessTokenFor($connection));
    }

    public function test_access_token_for_returns_existing_valid_token(): void
    {
        $user = User::factory()->employe()->create();
        $connection = GoogleCalendarConnection::factory()->create([
            'user_id' => $user->id,
            'access_token' => 'valid-token',
            'token_expires_at' => now()->addHour(),
        ]);

        $service = $this->makeService();

        $this->assertSame('valid-token', $service->accessTokenFor($connection));
    }

    public function test_access_token_for_throws_when_token_blank(): void
    {
        $user = User::factory()->employe()->create();
        $connection = GoogleCalendarConnection::factory()->create([
            'user_id' => $user->id,
            'access_token' => null,
            'token_expires_at' => now()->addHour(),
        ]);

        $this->expectException(RuntimeException::class);

        $this->makeService()->accessTokenFor($connection);
    }

    public function test_fetch_user_profile_returns_decoded_payload(): void
    {
        $service = $this->makeService([
            new Response(200, [], json_encode([
                'id' => 'google-uid-1',
                'email' => 'person@example.com',
            ])),
        ]);

        $profile = $service->fetchUserProfile('bearer-token');

        $this->assertSame('google-uid-1', $profile['id']);
        $this->assertSame('person@example.com', $profile['email']);
    }

    public function test_normalize_connection_payload_maps_token_and_profile(): void
    {
        $this->configureGoogle();
        $service = $this->makeService();

        $payload = $service->normalizeConnectionPayload(
            [
                'access_token' => 'at-2',
                'refresh_token' => 'rt-2',
                'expires_in' => 3600,
                'scope' => 'openid email',
            ],
            [
                'id' => 'uid-2',
                'email' => 'me@example.com',
            ]
        );

        $this->assertSame('me@example.com', $payload['google_email']);
        $this->assertSame('uid-2', $payload['google_user_id']);
        $this->assertSame('at-2', $payload['access_token']);
        $this->assertSame('rt-2', $payload['refresh_token']);
        $this->assertSame('primary', $payload['calendar_id']);
        $this->assertSame('openid email', $payload['scope']);
        $this->assertTrue($payload['sync_enabled']);
        $this->assertSame('connected', $payload['last_sync_status']);
        $this->assertNull($payload['last_sync_error']);
        $this->assertSame(['id' => 'uid-2', 'email' => 'me@example.com'], $payload['meta']['profile']);
    }
}
