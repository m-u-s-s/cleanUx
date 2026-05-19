<?php

namespace App\Services\SubscriptionsV2;

class BillingResult
{
    public function __construct(
        public readonly bool $success,
        public readonly int $amountCents,
        public readonly string $currency,
        public readonly ?string $reference = null,
        public readonly ?string $error = null,
        public readonly array $raw = [],
        public readonly string $provider = 'mock',
    ) {}

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'amount_cents' => $this->amountCents,
            'currency' => $this->currency,
            'reference' => $this->reference,
            'error' => $this->error,
            'provider' => $this->provider,
        ];
    }
}
