<?php

namespace App\Services\Sms\Providers;

use App\Services\Sms\SmsProviderInterface;
use App\Services\Sms\SmsSendRequest;
use App\Services\Sms\SmsSendResult;
use App\Services\Sms\SmsStatusUpdate;
use RuntimeException;
use Twilio\Rest\Client;

/**
 * Provider SMS Twilio.
 *
 * Webhook DLR : Twilio envoie un POST sur l'URL configurée avec MessageStatus
 * (queued|sending|sent|delivered|failed|undelivered).
 *
 * Signature : X-Twilio-Signature (HMAC-SHA1 du URL + body sorted, avec auth token).
 */
class TwilioSmsProvider implements SmsProviderInterface
{
    public function name(): string
    {
        return 'twilio';
    }

    public function send(SmsSendRequest $request): SmsSendResult
    {
        $sid = (string) config('sms.providers.twilio.sid', '');
        $token = (string) config('sms.providers.twilio.token', '');
        $from = $request->fromPhone ?: (string) config('sms.providers.twilio.from', '');

        if ($sid === '' || $token === '' || $from === '') {
            throw new RuntimeException('Twilio SMS not configured (sms.providers.twilio.*).');
        }

        try {
            $client = new Client($sid, $token);
            $msg = $client->messages->create($request->toPhone, [
                'from' => $from,
                'body' => $request->body,
            ]);

            return SmsSendResult::accepted(
                externalId: $msg->sid,
                status: $msg->status ?? 'sent',
                raw: ['sid' => $msg->sid, 'status' => $msg->status ?? null],
            );
        } catch (\Throwable $e) {
            return SmsSendResult::failed($e->getMessage(), 'twilio_send_error');
        }
    }

    /**
     * Vérifie la signature Twilio HMAC SHA1 (X-Twilio-Signature header).
     *
     * Algo Twilio :
     *   1. base = URL complete (incl. query) + params POST triés par clé
     *   2. HMAC SHA1 avec auth token
     *   3. base64 encode
     *   4. compare en time-safe avec X-Twilio-Signature
     *
     * Le caller (controller) doit fournir headers['X-Twilio-Signature'] et headers['_url'] (URL pleine).
     */
    public function verifyWebhook(string $payload, array $headers): array
    {
        $token = (string) config('sms.providers.twilio.token', '');
        if ($token === '') {
            throw new RuntimeException('Twilio token missing for webhook verification.');
        }

        parse_str($payload, $parsed);
        if (! is_array($parsed)) {
            $parsed = [];
        }

        // Skip verification only if explicitly disabled (dev mode)
        if (! (bool) config('sms.providers.twilio.verify_signature', true)) {
            return $parsed;
        }

        $providedSignature = $headers['x-twilio-signature']
            ?? $headers['X-Twilio-Signature']
            ?? ($headers['X_TWILIO_SIGNATURE'] ?? null);

        if (! $providedSignature) {
            throw new RuntimeException('Missing X-Twilio-Signature header.');
        }

        $url = $headers['_url'] ?? '';
        if (! $url) {
            throw new RuntimeException('Missing _url header for Twilio signature verification.');
        }

        // Concat params sorted by key (Twilio spec)
        $sortedParams = $parsed;
        ksort($sortedParams);
        $data = $url;
        foreach ($sortedParams as $k => $v) {
            $data .= $k . (is_array($v) ? json_encode($v) : (string) $v);
        }

        $expected = base64_encode(hash_hmac('sha1', $data, $token, true));

        if (! hash_equals($expected, (string) $providedSignature)) {
            throw new RuntimeException('Invalid Twilio webhook signature.');
        }

        return $parsed;
    }

    public function mapWebhookEvent(array $payload): ?SmsStatusUpdate
    {
        $sid = $payload['MessageSid'] ?? $payload['SmsSid'] ?? null;
        $messageStatus = $payload['MessageStatus'] ?? $payload['SmsStatus'] ?? null;

        if (! $sid || ! $messageStatus) {
            return null;
        }

        $status = match ($messageStatus) {
            'delivered' => 'delivered',
            'failed' => 'failed',
            'undelivered' => 'undelivered',
            'sent' => 'sent',
            default => $messageStatus,
        };

        return new SmsStatusUpdate(
            externalId: (string) $sid,
            status: $status,
            failureCode: $payload['ErrorCode'] ?? null,
            failureReason: $payload['ErrorMessage'] ?? null,
            raw: $payload,
        );
    }
}
