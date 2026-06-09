<?php

namespace Tests\Feature\Push;

use App\Services\Push\Providers\ApnsPushProvider;
use App\Services\Push\PushSendRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ApnsPushProviderCoverageBatch11Test extends TestCase
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

    private function writeKeyFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'apns_key_').'.p8';
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function configureValid(string $environment = 'production'): void
    {
        Config::set('push.providers.apns.bundle_id', 'com.example.app');
        Config::set('push.providers.apns.key_path', $this->writeKeyFile(self::TEST_PRIVATE_KEY));
        Config::set('push.providers.apns.key_id', 'KEY123456');
        Config::set('push.providers.apns.team_id', 'TEAM123456');
        Config::set('push.providers.apns.environment', $environment);
    }

    private function makeRequest(array $overrides = []): PushSendRequest
    {
        return new PushSendRequest(
            token: $overrides['token'] ?? 'device_token_abc',
            platform: $overrides['platform'] ?? 'ios',
            title: $overrides['title'] ?? 'Hello',
            body: $overrides['body'] ?? 'World',
            data: $overrides['data'] ?? ['booking_id' => 7],
            category: $overrides['category'] ?? 'transactional',
        );
    }

    public function test_name_and_supported_platforms(): void
    {
        $provider = new ApnsPushProvider;

        $this->assertSame('apns', $provider->name());
        $this->assertSame(['ios'], $provider->supportsPlatforms());
    }

    public function test_send_fails_when_configuration_incomplete(): void
    {
        Config::set('push.providers.apns.bundle_id', '');
        Config::set('push.providers.apns.key_path', '');
        Config::set('push.providers.apns.key_id', '');
        Config::set('push.providers.apns.team_id', '');

        $result = (new ApnsPushProvider)->send($this->makeRequest());

        $this->assertFalse($result->accepted);
        $this->assertSame('apns_config', $result->failureCode);
        $this->assertSame('failed', $result->status);
        $this->assertSame('APNs configuration incomplete', $result->failureReason);
    }

    public function test_send_fails_when_key_file_missing(): void
    {
        Config::set('push.providers.apns.bundle_id', 'com.example.app');
        Config::set('push.providers.apns.key_path', '/no/such/key.p8');
        Config::set('push.providers.apns.key_id', 'KEY123456');
        Config::set('push.providers.apns.team_id', 'TEAM123456');

        $result = (new ApnsPushProvider)->send($this->makeRequest());

        $this->assertFalse($result->accepted);
        $this->assertSame('apns_config', $result->failureCode);
    }

    public function test_send_fails_auth_when_key_is_invalid(): void
    {
        Config::set('push.providers.apns.bundle_id', 'com.example.app');
        Config::set('push.providers.apns.key_path', $this->writeKeyFile('not a real pem key'));
        Config::set('push.providers.apns.key_id', 'KEY123456');
        Config::set('push.providers.apns.team_id', 'TEAM123456');

        $result = (new ApnsPushProvider)->send($this->makeRequest());

        $this->assertFalse($result->accepted);
        $this->assertSame('apns_auth', $result->failureCode);
        $this->assertStringContainsString('APNs auth failed', (string) $result->failureReason);
    }

    public function test_send_success_returns_accepted_with_apns_id_header(): void
    {
        $this->configureValid();

        Http::fake([
            'api.push.apple.com/*' => Http::response('', 200, ['apns-id' => 'ABCDEF-APNS-ID']),
        ]);

        $result = (new ApnsPushProvider)->send($this->makeRequest());

        $this->assertTrue($result->accepted);
        $this->assertSame('ABCDEF-APNS-ID', $result->externalId);
        $this->assertSame('sent', $result->status);
        $this->assertSame(200, $result->raw['status']);

        Http::assertSent(function ($req) {
            $body = $req->data();

            return str_contains($req->url(), 'api.push.apple.com/3/device/device_token_abc')
                && $req->hasHeader('apns-topic', 'com.example.app')
                && $req->hasHeader('apns-push-type', 'alert')
                && $req->hasHeader('apns-priority', '10')
                && ($body['aps']['alert']['title'] ?? null) === 'Hello'
                && ($body['aps']['alert']['body'] ?? null) === 'World'
                && ($body['aps']['sound'] ?? null) === 'default'
                && ($body['data']['booking_id'] ?? null) === 7;
        });
    }

    public function test_send_success_without_apns_id_header_uses_fallback_and_sandbox_host(): void
    {
        $this->configureValid('sandbox');

        Http::fake([
            'api.sandbox.push.apple.com/*' => Http::response('', 200),
        ]);

        $request = $this->makeRequest([
            'data' => [],
            'category' => 'marketing',
        ]);

        $result = (new ApnsPushProvider)->send($request);

        $this->assertTrue($result->accepted);
        $this->assertStringStartsWith('apns_', (string) $result->externalId);

        Http::assertSent(function ($req) {
            $body = $req->data();

            return str_contains($req->url(), 'api.sandbox.push.apple.com')
                && $req->hasHeader('apns-priority', '5')
                && ! isset($body['data']);
        });
    }

    public function test_send_marks_token_invalid_on_bad_device_token(): void
    {
        $this->configureValid();

        Http::fake([
            'api.push.apple.com/*' => Http::response(['reason' => 'BadDeviceToken'], 400),
        ]);

        $result = (new ApnsPushProvider)->send($this->makeRequest());

        $this->assertFalse($result->accepted);
        $this->assertTrue($result->tokenInvalid);
        $this->assertSame('apns_baddevicetoken', $result->failureCode);
        $this->assertSame('invalid_token', $result->status);
        $this->assertSame('BadDeviceToken', $result->failureReason);
        $this->assertSame('BadDeviceToken', $result->raw['reason']);
    }

    public function test_send_generic_error_is_not_token_invalid(): void
    {
        $this->configureValid();

        Http::fake([
            'api.push.apple.com/*' => Http::response(['reason' => 'PayloadTooLarge'], 413),
        ]);

        $result = (new ApnsPushProvider)->send($this->makeRequest());

        $this->assertFalse($result->accepted);
        $this->assertFalse($result->tokenInvalid);
        $this->assertSame('apns_payloadtoolarge', $result->failureCode);
        $this->assertSame('failed', $result->status);
        $this->assertSame('PayloadTooLarge', $result->failureReason);
    }

    public function test_send_error_with_no_reason_falls_back_to_unknown(): void
    {
        $this->configureValid();

        Http::fake([
            'api.push.apple.com/*' => Http::response([], 500),
        ]);

        $result = (new ApnsPushProvider)->send($this->makeRequest());

        $this->assertFalse($result->accepted);
        $this->assertSame('apns_unknown', $result->failureCode);
        $this->assertSame('unknown', $result->failureReason);
        $this->assertFalse($result->tokenInvalid);
    }
}
