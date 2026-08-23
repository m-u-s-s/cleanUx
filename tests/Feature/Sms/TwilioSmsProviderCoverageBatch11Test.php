<?php

namespace Tests\Feature\Sms;

use App\Services\Sms\Providers\TwilioSmsProvider;
use App\Services\Sms\SmsSendRequest;
use App\Services\Sms\SmsStatusUpdate;
use RuntimeException;
use Tests\TestCase;

class TwilioSmsProviderCoverageBatch11Test extends TestCase
{
    private function provider(): TwilioSmsProvider
    {
        return new TwilioSmsProvider;
    }

    private function signTwilio(string $url, array $sortedParams, string $token): string
    {
        $data = $url;
        foreach ($sortedParams as $k => $v) {
            $data .= $k.(is_array($v) ? json_encode($v) : (string) $v);
        }

        return base64_encode(hash_hmac('sha1', $data, $token, true));
    }

    public function test_name_is_twilio(): void
    {
        $this->assertSame('twilio', $this->provider()->name());
    }

    public function test_send_throws_when_not_configured(): void
    {
        config()->set('sms.providers.twilio.sid', '');
        config()->set('sms.providers.twilio.token', '');
        config()->set('sms.providers.twilio.from', '');

        $this->expectException(RuntimeException::class);

        $this->provider()->send(new SmsSendRequest(
            toPhone: '+32412345678',
            body: 'hello',
        ));
    }

    public function test_verify_webhook_throws_when_token_missing(): void
    {
        config()->set('sms.providers.twilio.token', '');

        $this->expectException(RuntimeException::class);

        $this->provider()->verifyWebhook('MessageSid=SM1', []);
    }

    public function test_verify_webhook_skips_when_signature_disabled(): void
    {
        config()->set('sms.providers.twilio.token', 'tok_abc');
        config()->set('sms.providers.twilio.verify_signature', false);

        $parsed = $this->provider()->verifyWebhook('MessageSid=SM1&MessageStatus=sent', []);

        $this->assertSame('SM1', $parsed['MessageSid']);
        $this->assertSame('sent', $parsed['MessageStatus']);
    }

    public function test_verify_webhook_throws_when_signature_header_missing(): void
    {
        config()->set('sms.providers.twilio.token', 'tok_abc');
        config()->set('sms.providers.twilio.verify_signature', true);

        $this->expectException(RuntimeException::class);

        $this->provider()->verifyWebhook('MessageSid=SM1', []);
    }

    public function test_verify_webhook_throws_when_url_missing(): void
    {
        config()->set('sms.providers.twilio.token', 'tok_abc');
        config()->set('sms.providers.twilio.verify_signature', true);

        $this->expectException(RuntimeException::class);

        $this->provider()->verifyWebhook('MessageSid=SM1', [
            'x-twilio-signature' => 'whatever',
        ]);
    }

    public function test_verify_webhook_throws_on_invalid_signature(): void
    {
        config()->set('sms.providers.twilio.token', 'tok_abc');
        config()->set('sms.providers.twilio.verify_signature', true);

        $this->expectException(RuntimeException::class);

        $this->provider()->verifyWebhook('MessageSid=SM1&MessageStatus=sent', [
            'x-twilio-signature' => 'definitely-wrong',
            '_url' => 'https://example.test/webhooks/sms/twilio',
        ]);
    }

    public function test_verify_webhook_accepts_valid_signature(): void
    {
        $token = 'tok_abc';
        $url = 'https://example.test/webhooks/sms/twilio';
        config()->set('sms.providers.twilio.token', $token);
        config()->set('sms.providers.twilio.verify_signature', true);

        $payload = 'MessageStatus=delivered&MessageSid=SM999';
        // parsed + ksorted -> MessageSid, MessageStatus
        $signature = $this->signTwilio($url, [
            'MessageSid' => 'SM999',
            'MessageStatus' => 'delivered',
        ], $token);

        $parsed = $this->provider()->verifyWebhook($payload, [
            'X-Twilio-Signature' => $signature,
            '_url' => $url,
        ]);

        $this->assertSame('SM999', $parsed['MessageSid']);
        $this->assertSame('delivered', $parsed['MessageStatus']);
    }

    public function test_verify_webhook_accepts_valid_signature_with_array_param(): void
    {
        $token = 'tok_xyz';
        $url = 'https://example.test/webhooks/sms/twilio';
        config()->set('sms.providers.twilio.token', $token);
        config()->set('sms.providers.twilio.verify_signature', true);

        $payload = 'Tags[]=a&Tags[]=b&MessageSid=SM7';
        // ksorted keys -> MessageSid, Tags
        $signature = $this->signTwilio($url, [
            'MessageSid' => 'SM7',
            'Tags' => ['a', 'b'],
        ], $token);

        $parsed = $this->provider()->verifyWebhook($payload, [
            'X_TWILIO_SIGNATURE' => $signature,
            '_url' => $url,
        ]);

        $this->assertSame('SM7', $parsed['MessageSid']);
        $this->assertSame(['a', 'b'], $parsed['Tags']);
    }

    public function test_map_webhook_event_returns_null_without_sid_or_status(): void
    {
        $this->assertNull($this->provider()->mapWebhookEvent([]));
        $this->assertNull($this->provider()->mapWebhookEvent(['MessageSid' => 'SM1']));
        $this->assertNull($this->provider()->mapWebhookEvent(['MessageStatus' => 'sent']));
    }

    public function test_map_webhook_event_maps_known_statuses(): void
    {
        // Les quatre statuts releves ensemble : une correspondance de webhook mal branchee
        // l'est sur plusieurs statuts, et chacun est un accuse de reception perdu.
        $ecarts = [];

        foreach (['delivered', 'failed', 'undelivered', 'sent'] as $status) {
            $update = $this->provider()->mapWebhookEvent([
                'MessageSid' => 'SM_'.$status,
                'MessageStatus' => $status,
            ]);

            if (! $update instanceof SmsStatusUpdate) {
                $ecarts[] = "{$status} : rend ".get_debug_type($update);

                continue;
            }

            if ($update->externalId !== 'SM_'.$status) {
                $ecarts[] = "{$status} : identifiant « {$update->externalId} »";
            }

            if ($update->status !== $status) {
                $ecarts[] = "{$status} : statut rendu « {$update->status} »";
            }
        }

        $this->assertSame([], $ecarts, 'Ces statuts de webhook ne sont pas correctement traduits.');
    }

    public function test_map_webhook_event_passes_through_unknown_status_and_errors(): void
    {
        $update = $this->provider()->mapWebhookEvent([
            'SmsSid' => 'SM_queued',
            'SmsStatus' => 'queued',
            'ErrorCode' => '30008',
            'ErrorMessage' => 'Unknown error',
        ]);

        $this->assertInstanceOf(SmsStatusUpdate::class, $update);
        $this->assertSame('SM_queued', $update->externalId);
        $this->assertSame('queued', $update->status);
        $this->assertSame('30008', $update->failureCode);
        $this->assertSame('Unknown error', $update->failureReason);
        $this->assertSame('queued', $update->raw['SmsStatus']);
    }
}
