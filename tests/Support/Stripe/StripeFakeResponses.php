<?php

namespace Tests\Support\Stripe;

/**
 * Canned, test-mode-shaped Stripe API objects for the fake HTTP client.
 */
class StripeFakeResponses
{
    public static function paymentIntent(string $id, string $status, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'object' => 'payment_intent',
            'status' => $status,
            'amount' => 10000,
            'amount_capturable' => $status === 'requires_capture' ? 10000 : 0,
            'amount_received' => $status === 'succeeded' ? 10000 : 0,
            'currency' => 'eur',
            'capture_method' => 'manual',
            'application_fee_amount' => 2000,
            'transfer_data' => ['destination' => 'acct_provider_test'],
            'latest_charge' => 'ch_test_'.$id,
            'metadata' => [],
        ], $overrides);
    }

    public static function refund(string $id, string $piId, int $amount): array
    {
        return [
            'id' => $id,
            'object' => 'refund',
            'amount' => $amount,
            'currency' => 'eur',
            'payment_intent' => $piId,
            'status' => 'succeeded',
        ];
    }
}
