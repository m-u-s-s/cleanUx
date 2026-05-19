<?php

namespace Tests\Feature\WebhooksV2;

use App\Services\WebhooksV2\WebhookSigner;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class WebhookSignerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('webhooks_v2.signature_algo', 'sha256');
        Config::set('webhooks_v2.signature_version', 'v1');
        Config::set('webhooks_v2.signature_tolerance_seconds', 300);
    }

    public function test_sign_produces_t_and_v1_with_hmac_sha256(): void
    {
        $signer = new WebhookSigner();
        $sig = $signer->sign('{"hello":"world"}', 'topsecret', 1_700_000_000);

        $this->assertStringStartsWith('t=1700000000,v1=', $sig);
        $expected = hash_hmac('sha256', '1700000000.{"hello":"world"}', 'topsecret');
        $this->assertStringContainsString('v1=' . $expected, $sig);
    }

    public function test_verify_returns_true_for_valid_signature_within_tolerance(): void
    {
        $signer = new WebhookSigner();
        $body = '{"event":"booking.created"}';
        $secret = 'whsec_test';
        $now = 1_700_000_500;
        $sig = $signer->sign($body, $secret, $now);

        $this->assertTrue($signer->verify($body, $sig, $secret, $now + 60));
    }

    public function test_verify_rejects_outside_tolerance(): void
    {
        $signer = new WebhookSigner();
        $sig = $signer->sign('payload', 'k', 1_700_000_000);
        $this->assertFalse($signer->verify('payload', $sig, 'k', 1_700_000_000 + 999));
    }

    public function test_verify_rejects_tampered_body(): void
    {
        $signer = new WebhookSigner();
        $sig = $signer->sign('original', 'k', 1_700_000_000);
        $this->assertFalse($signer->verify('tampered', $sig, 'k', 1_700_000_000));
    }

    public function test_verify_rejects_wrong_secret(): void
    {
        $signer = new WebhookSigner();
        $sig = $signer->sign('body', 'right', 1_700_000_000);
        $this->assertFalse($signer->verify('body', $sig, 'wrong', 1_700_000_000));
    }
}
