<?php

namespace Tests\Feature\Push;

use App\Services\Push\Providers\FcmPushProvider;
use App\Services\Push\PushSendRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FcmPushProviderCoverageBatch6Test extends TestCase
{
    private const TEST_PRIVATE_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQCsoz1cyFPLOtz0
XdkGdvD0ouamTtUhfdLZhcL2iWD9TWnH2iQ95TgcPogXRN/mB5IRatzQKCNzMIPS
a5XfIfQkzMSfWGbr+GTb3gVdZ/qztPz8ZxdbNWkNc9hzQLhWVafXMeP2SdZwzjTu
HIBXVAurGem5niXYzfzjukSG79SSwS7HnjXYRAvFy9gsieJ6U7Rk/UAuNZaxsUrU
SbT+9N4HrAsYyy/wyOPEX8JW/xXrK0SpUJjU8NIIiSUEqivKqUv91/J8S7tcDdcb
coarNnLVXXRMkzUTZXo+Srz5vGjMlY7+tF2xQXha1MZeJQwc3FQu/flgmrXMtaHr
ux7753wRAgMBAAECggEAI45zgvqXl5IGFCaIHna85gXvL22pI/7AQKD2KMTevw0E
rm7VbBIb3mVarkA3RS9l/ERgOlcqBf2GCP6znYNmv3WVQaR5fjxouYge3sAduImc
WXf6LYTwoB6uA/7eeJmnugDCjOVkT1OJP0mLfXEH/jOWGe7iqKa0YUrp9kjLJXZh
yEJEaLuS7cV6zleqNUffTiT4KHW8nUFkLUEQJKJ6qEI6gQUk3p11vMhwySOSPs00
dheHP44uwehzHLoTV9wrjEjQSusOdAcLfS+kfToGA/nWvxshlXlLiVf7sAkFEbn+
rcneZNgs83phjhLGC9ZkadKfmahL7GH6YesPh8mdyQKBgQDWqksOwuOoM/AxSpPa
QkyZhjQEytl2O5ul6l9bhmUufuYu8kndgGAJUfQVMewpGmKqwGCu2oGHqIMQg8RA
BUXn/xF9pPbOcNZ9p3BoyDZ98vJPig2ZSKJe0h54ATX1/iAQqQQupv+KSJHbqbGT
kmVAsa35UEWwzzGFpw7xurZVEwKBgQDN4T2E3ocfm90760iF1IgjECrwGtY1RFF5
Fy6jQOdll3k5SV71SKasz4oHnpmOSHfSLPyOxTCLurutTrEx3qjuK9S+43twGn0J
8brZesrKehwK6RnBj3VdwLGKkyifcBCp7BOPmap5Iov0ELt2cWwIhfD0ado+N1Na
l3zZQhSiywKBgCb8GlF991ZOyGpLPvq+W6buBnhwVnnwbV3+aH74s1t0VF1mRx/g
9o/6wDcxL9BvKEgWU/itWiTG57aSF4wA6Scu3YBR+ziWqX18cR+2bJ8HBhaH3dxe
oo5R3pKwtZoNIsmjyEyoq3PzpfmcodFJHvZWzl+ewmlP15CMPFyOKhrdAoGAZ0yz
pF5PKDH5cF4ell/MKuOq16xvfGyaAIr8MJeZQyUcgvzoc2QPlTfRBv+yBm0gCcne
svgH4qRAFLYePGp/EnnVli3nupjf0kSCvypYY/2e4m6RKMiFWBQeQOyTfmQpSEkW
i3/LxFnsJPrcRJKmZSRngQs3oO37mcHWt9/rIL8CgYEAjVUpF02dYkncGeL9Ddj4
fg8M6CPqIRoy56nLZ7H5CfwQqcHinS0UwghTdWYD7qOrqYRs3wwSesadDaHG+xEd
Wxf95ODr3Bsj2qL1Xi5wypHYz45pqO3t+tOzsCnakmHobcb+lpFRskLxIRx/cbIf
NNJIwLt3WJJum4OpHL4bmpo=
-----END PRIVATE KEY-----
PEM;

    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_string($file) && file_exists($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];
        parent::tearDown();
    }

    private function writeCredentialsFile(array $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fcm_sa_').'.json';
        file_put_contents($path, json_encode($contents));
        $this->tempFiles[] = $path;

        return $path;
    }

    private function validCredentialsFile(): string
    {
        return $this->writeCredentialsFile([
            'client_email' => 'svc@test.iam.gserviceaccount.com',
            'private_key' => self::TEST_PRIVATE_KEY,
        ]);
    }

    private function makeRequest(array $overrides = []): PushSendRequest
    {
        return new PushSendRequest(
            token: $overrides['token'] ?? 'device_token_abc',
            platform: $overrides['platform'] ?? 'android',
            title: $overrides['title'] ?? 'Hello',
            body: $overrides['body'] ?? 'World',
            data: $overrides['data'] ?? ['booking_id' => 7],
            category: $overrides['category'] ?? 'transactional',
        );
    }

    public function test_name_and_supported_platforms(): void
    {
        $provider = new FcmPushProvider;

        $this->assertSame('fcm', $provider->name());
        $this->assertSame(['ios', 'android', 'web'], $provider->supportsPlatforms());
    }

    public function test_send_fails_when_project_id_missing(): void
    {
        Config::set('push.providers.fcm.project_id', '');

        $result = (new FcmPushProvider)->send($this->makeRequest());

        $this->assertFalse($result->accepted);
        $this->assertSame('fcm_config', $result->failureCode);
        $this->assertSame('failed', $result->status);
    }

    public function test_send_fails_auth_when_credentials_file_missing(): void
    {
        Config::set('push.providers.fcm.project_id', 'my-project');
        Config::set('push.providers.fcm.credentials_path', '/no/such/file.json');

        $result = (new FcmPushProvider)->send($this->makeRequest());

        $this->assertFalse($result->accepted);
        $this->assertSame('fcm_auth', $result->failureCode);
        $this->assertStringContainsString('FCM auth failed', (string) $result->failureReason);
    }

    public function test_send_fails_auth_when_credentials_malformed(): void
    {
        Config::set('push.providers.fcm.project_id', 'my-project');
        Config::set('push.providers.fcm.credentials_path', $this->writeCredentialsFile(['foo' => 'bar']));

        $result = (new FcmPushProvider)->send($this->makeRequest());

        $this->assertFalse($result->accepted);
        $this->assertSame('fcm_auth', $result->failureCode);
        $this->assertStringContainsString('malformed', (string) $result->failureReason);
    }

    public function test_send_fails_auth_when_token_exchange_rejected(): void
    {
        Config::set('push.providers.fcm.project_id', 'my-project');
        Config::set('push.providers.fcm.credentials_path', $this->validCredentialsFile());

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant'], 401),
        ]);

        $result = (new FcmPushProvider)->send($this->makeRequest());

        $this->assertFalse($result->accepted);
        $this->assertSame('fcm_auth', $result->failureCode);
        $this->assertStringContainsString('token exchange failed', (string) $result->failureReason);
    }

    public function test_send_success_returns_accepted_with_external_id(): void
    {
        Config::set('push.providers.fcm.project_id', 'my-project');
        Config::set('push.providers.fcm.credentials_path', $this->validCredentialsFile());

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test_token', 'expires_in' => 3600]),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/my-project/messages/0:123']),
        ]);

        $result = (new FcmPushProvider)->send($this->makeRequest());

        $this->assertTrue($result->accepted);
        $this->assertSame('projects/my-project/messages/0:123', $result->externalId);
        $this->assertSame('sent', $result->status);

        Http::assertSent(function ($req) {
            if (! str_contains($req->url(), 'fcm.googleapis.com')) {
                return false;
            }
            $message = $req->data()['message'] ?? [];

            return ($message['token'] ?? null) === 'device_token_abc'
                && ($message['notification']['title'] ?? null) === 'Hello'
                && ($message['notification']['body'] ?? null) === 'World'
                && ($message['data']['booking_id'] ?? null) === '7'
                && ($message['android']['priority'] ?? null) === 'high';
        });
    }

    public function test_send_success_without_title_uses_normal_priority_for_marketing(): void
    {
        Config::set('push.providers.fcm.project_id', 'my-project');
        Config::set('push.providers.fcm.credentials_path', $this->validCredentialsFile());

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test_token']),
            'fcm.googleapis.com/*' => Http::response([]),
        ]);

        $request = new PushSendRequest(
            token: 'device_token_abc',
            platform: 'android',
            title: null,
            body: 'World',
            data: [],
            category: 'marketing',
        );

        $result = (new FcmPushProvider)->send($request);

        $this->assertTrue($result->accepted);
        $this->assertStringStartsWith('fcm_', (string) $result->externalId);

        Http::assertSent(function ($req) {
            if (! str_contains($req->url(), 'fcm.googleapis.com')) {
                return false;
            }
            $message = $req->data()['message'] ?? [];

            return ! isset($message['notification']['title'])
                && ! isset($message['data'])
                && ($message['android']['priority'] ?? null) === 'normal';
        });
    }

    public function test_send_marks_token_invalid_on_unregistered(): void
    {
        Config::set('push.providers.fcm.project_id', 'my-project');
        Config::set('push.providers.fcm.credentials_path', $this->validCredentialsFile());

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test_token']),
            'fcm.googleapis.com/*' => Http::response([
                'error' => [
                    'status' => 'NOT_FOUND',
                    'message' => 'Requested entity was not found.',
                    'details' => [
                        ['errorCode' => 'UNREGISTERED'],
                    ],
                ],
            ], 404),
        ]);

        $result = (new FcmPushProvider)->send($this->makeRequest());

        $this->assertFalse($result->accepted);
        $this->assertTrue($result->tokenInvalid);
        $this->assertSame('UNREGISTERED', $result->failureCode);
        $this->assertSame('invalid_token', $result->status);
        $this->assertSame('Requested entity was not found.', $result->failureReason);
    }

    public function test_send_generic_error_uses_status_and_is_not_token_invalid(): void
    {
        Config::set('push.providers.fcm.project_id', 'my-project');
        Config::set('push.providers.fcm.credentials_path', $this->validCredentialsFile());

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test_token']),
            'fcm.googleapis.com/*' => Http::response([
                'error' => [
                    'status' => 'INTERNAL',
                    'message' => 'Internal server error',
                ],
            ], 500),
        ]);

        $result = (new FcmPushProvider)->send($this->makeRequest());

        $this->assertFalse($result->accepted);
        $this->assertFalse($result->tokenInvalid);
        $this->assertSame('INTERNAL', $result->failureCode);
        $this->assertSame('Internal server error', $result->failureReason);
    }

    public function test_send_error_with_empty_body_falls_back_to_defaults(): void
    {
        Config::set('push.providers.fcm.project_id', 'my-project');
        Config::set('push.providers.fcm.credentials_path', $this->validCredentialsFile());

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.test_token']),
            'fcm.googleapis.com/*' => Http::response(null, 503),
        ]);

        $result = (new FcmPushProvider)->send($this->makeRequest());

        $this->assertFalse($result->accepted);
        $this->assertSame('fcm_unknown', $result->failureCode);
        $this->assertSame('FCM error', $result->failureReason);
        $this->assertSame([], $result->raw);
    }
}
