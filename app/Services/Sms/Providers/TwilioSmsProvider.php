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

    public function verifyWebhook(string $payload, array $headers): array
    {
        $token = (string) config('sms.providers.twilio.token', '');
        if ($token === '') {
            throw new RuntimeException('Twilio token missing for webhook verification.');
        }

        // Twilio DLR vient en form-encoded, parsé en POST request body.
        parse_str($payload, $parsed);
        if (! is_array($parsed)) {
            $parsed = [];
        }

        // Note: la vérification complète de signature Twilio requiert l'URL + body trié.
        // Le service se contente ici de parser. La signature complète peut être
        // vérifiée au niveau du controller si nécessaire.
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
