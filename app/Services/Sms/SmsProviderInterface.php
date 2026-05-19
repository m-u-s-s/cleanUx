<?php

namespace App\Services\Sms;

interface SmsProviderInterface
{
    /**
     * Identifiant du provider ('mock', 'twilio', 'vonage').
     */
    public function name(): string;

    /**
     * Envoie un SMS via le provider externe.
     */
    public function send(SmsSendRequest $request): SmsSendResult;

    /**
     * Vérifie la signature d'un webhook (DLR / status callback).
     *
     * @return array<string,mixed> Payload parsé
     */
    public function verifyWebhook(string $payload, array $headers): array;

    /**
     * Mappe un payload webhook en SmsStatusUpdate exploitable.
     */
    public function mapWebhookEvent(array $payload): ?SmsStatusUpdate;
}
