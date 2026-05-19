<?php

namespace App\Services\CancellationV2;

use App\Models\CancellationPolicy;
use App\Models\CancellationPolicyTier;

/**
 * Immutable DTO représentant une cotation de cancellation avant exécution.
 *
 * Computed by CancellationEngine::quote(). Exposed to client / provider /
 * admin to preview impact (fee, refund) before they commit to cancel.
 */
class CancellationQuote
{
    public function __construct(
        public readonly int $bookingId,
        public readonly string $actorRole,
        public readonly int $bookingAmountCents,
        public readonly string $currency,
        public readonly ?CancellationPolicy $policy,
        public readonly ?CancellationPolicyTier $tier,
        public readonly float $feePercent,
        public readonly int $feeAmountCents,
        public readonly int $refundAmountCents,
        public readonly ?string $reasonCode,
        public readonly bool $exemptApplied,
        public readonly ?string $tierLabel,
        public readonly ?int $hoursBefore,
        public readonly array $warnings = [],
    ) {}

    public function toArray(): array
    {
        return [
            'booking_id' => $this->bookingId,
            'actor_role' => $this->actorRole,
            'booking_amount_cents' => $this->bookingAmountCents,
            'currency' => $this->currency,
            'policy_code' => $this->policy?->code,
            'tier_id' => $this->tier?->id,
            'tier_label' => $this->tierLabel,
            'fee_percent' => $this->feePercent,
            'fee_amount_cents' => $this->feeAmountCents,
            'refund_amount_cents' => $this->refundAmountCents,
            'reason_code' => $this->reasonCode,
            'exempt_applied' => $this->exemptApplied,
            'hours_before' => $this->hoursBefore,
            'warnings' => $this->warnings,
        ];
    }
}
