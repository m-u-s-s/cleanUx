<?php

namespace App\Services\Insurance;

use App\Models\BookingInsurance;
use App\Models\InsuranceClaim;
use App\Models\InsurancePlan;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * InsuranceService — orchestre la vie d'une police + claims.
 *
 *   - purchase($bookingId, $planCode, $user) → BookingInsurance (status=active)
 *   - cancel($insurance) → marque cancelled, appelle le provider
 *   - fileClaim($insurance, ...) → InsuranceClaim
 *   - applyWebhookUpdate(InsuranceWebhookUpdate) → mute policy ou claim
 *
 * Idempotence : `idempotency_key` UNIQUE sur booking_insurances + insurance_claims.
 */
class InsuranceService
{
    public function __construct(
        protected InsuranceProviderInterface $provider,
        protected InsurancePricingEngine $pricing,
    ) {}

    public function purchase(int $bookingId, string $planCode, ?User $user = null, ?string $idempotencyKey = null): BookingInsurance
    {
        if (! Config::get('insurance.enabled', true)) {
            throw ValidationException::withMessages(['module' => 'Insurance module disabled.']);
        }

        $idempotencyKey ??= "purchase:booking:{$bookingId}:plan:{$planCode}";

        if ($existing = BookingInsurance::query()->where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }

        $plan = InsurancePlan::query()->where('code', $planCode)->active()->first();
        if (! $plan || ! $plan->isWithinValidity()) {
            throw ValidationException::withMessages(['plan_code' => 'Plan not found or not valid.']);
        }

        $meta = $this->pricing->resolveBookingMeta($bookingId);
        if (! $meta) {
            throw ValidationException::withMessages(['booking_id' => 'Booking not found.']);
        }

        if (! $plan->appliesToTrade($meta['trade_code'])) {
            throw ValidationException::withMessages(['plan_code' => 'Plan does not apply to this trade.']);
        }

        $premium = $this->pricing->computePremium($plan, $meta['amount_cents']);

        return DB::transaction(function () use ($plan, $bookingId, $premium, $meta, $user, $idempotencyKey) {
            $insurance = BookingInsurance::create([
                'booking_id' => $bookingId,
                'plan_id' => $plan->id,
                'user_id' => $user?->id ?? $meta['client_id'],
                'provider_user_id' => $meta['provider_user_id'],
                'premium_cents' => $premium,
                'coverage_amount_cents' => $plan->coverage_amount_cents,
                'currency' => $plan->currency,
                'status' => BookingInsurance::STATUS_PROPOSED,
                'external_provider' => $this->provider->name(),
                'purchased_at' => now(),
                'effective_from' => now(),
                'effective_until' => now()->addYear(),
                'idempotency_key' => $idempotencyKey,
                'metadata' => ['booking_meta' => $meta],
            ]);

            try {
                $result = $this->provider->purchase(new InsurancePurchaseRequest(
                    planCode: $plan->code,
                    bookingId: $bookingId,
                    premiumCents: $premium,
                    coverageCents: $plan->coverage_amount_cents,
                    currency: $plan->currency,
                    userId: $insurance->user_id,
                    providerUserId: $insurance->provider_user_id,
                    effectiveFrom: $insurance->effective_from,
                    effectiveUntil: $insurance->effective_until,
                    idempotencyKey: $idempotencyKey,
                ));

                if ($result->accepted) {
                    $insurance->forceFill([
                        'status' => BookingInsurance::STATUS_ACTIVE,
                        'external_id' => $result->externalId,
                        'policy_number' => $result->policyNumber,
                        'metadata' => array_merge((array) $insurance->metadata, ['provider_raw' => $result->raw]),
                    ])->save();
                } else {
                    $insurance->forceFill([
                        'status' => BookingInsurance::STATUS_CANCELLED,
                        'cancelled_at' => now(),
                        'metadata' => array_merge((array) $insurance->metadata, [
                            'failure_code' => $result->failureCode,
                            'failure_reason' => $result->failureReason,
                        ]),
                    ])->save();
                }
            } catch (\Throwable $e) {
                $insurance->forceFill([
                    'status' => BookingInsurance::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'metadata' => array_merge((array) $insurance->metadata, ['error' => $e->getMessage()]),
                ])->save();
                throw $e;
            }

            ActivityLogger::log('insurance.purchased', $insurance->fresh(), [
                'plan_code' => $plan->code,
                'premium_cents' => $premium,
                'status' => $insurance->fresh()->status,
            ]);

            return $insurance->fresh();
        });
    }

    public function cancel(BookingInsurance $insurance): BookingInsurance
    {
        if (in_array($insurance->status, [BookingInsurance::STATUS_CANCELLED, BookingInsurance::STATUS_EXPIRED], true)) {
            return $insurance;
        }

        if ($insurance->external_id) {
            try {
                $this->provider->cancelPolicy($insurance->external_id);
            } catch (\Throwable $e) {
                // Log mais continue : on annule côté DB même si provider down
                \Log::warning('Insurance cancel: provider call failed', ['error' => $e->getMessage()]);
            }
        }

        $insurance->forceFill([
            'status' => BookingInsurance::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ])->save();

        ActivityLogger::log('insurance.cancelled', $insurance, []);

        return $insurance->fresh();
    }

    public function fileClaim(
        BookingInsurance $insurance,
        User $claimant,
        string $incidentType,
        string $description,
        \DateTimeInterface $incidentDate,
        int $amountClaimedCents,
        array $evidence = [],
        ?string $idempotencyKey = null,
    ): InsuranceClaim {
        if (! $insurance->isActive()) {
            throw ValidationException::withMessages(['insurance' => 'Policy is not active.']);
        }

        $windowDays = (int) Config::get('insurance.claims.filing_window_days', 30);
        if ($incidentDate < now()->subDays($windowDays)) {
            throw ValidationException::withMessages([
                'incident_date' => "Incident date exceeds filing window of {$windowDays} days.",
            ]);
        }

        $maxFactor = (int) Config::get('insurance.claims.max_amount_factor', 50);
        $maxAllowed = $insurance->premium_cents * $maxFactor;
        if ($amountClaimedCents > $maxAllowed && $maxAllowed > 0) {
            throw ValidationException::withMessages([
                'amount_claimed_cents' => "Claim amount exceeds maximum ({$maxFactor}× premium).",
            ]);
        }
        if ($amountClaimedCents > $insurance->coverage_amount_cents) {
            throw ValidationException::withMessages([
                'amount_claimed_cents' => 'Claim amount exceeds coverage.',
            ]);
        }

        $idempotencyKey ??= "claim:insurance:{$insurance->id}:" . hash('sha256', $description . ':' . $incidentDate->format('Y-m-d'));

        if ($existing = InsuranceClaim::query()->where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }

        return DB::transaction(function () use ($insurance, $claimant, $incidentType, $description, $incidentDate, $amountClaimedCents, $evidence, $idempotencyKey) {
            $claim = InsuranceClaim::create([
                'booking_insurance_id' => $insurance->id,
                'claimant_user_id' => $claimant->id,
                'status' => InsuranceClaim::STATUS_FILED,
                'incident_type' => $incidentType,
                'incident_description' => $description,
                'incident_date' => $incidentDate,
                'amount_claimed_cents' => $amountClaimedCents,
                'filed_at' => now(),
                'evidence' => $evidence,
                'idempotency_key' => $idempotencyKey,
            ]);

            if ($insurance->external_id) {
                try {
                    $result = $this->provider->fileClaim(new ClaimFilingRequest(
                        policyExternalId: $insurance->external_id,
                        incidentType: $incidentType,
                        incidentDescription: $description,
                        incidentDate: $incidentDate,
                        amountClaimedCents: $amountClaimedCents,
                        currency: $insurance->currency,
                        evidence: $evidence,
                        idempotencyKey: $idempotencyKey,
                    ));

                    if ($result->accepted) {
                        $claim->forceFill([
                            'external_claim_id' => $result->externalClaimId,
                            'status' => $result->status === InsuranceClaim::STATUS_REJECTED
                                ? InsuranceClaim::STATUS_REJECTED
                                : InsuranceClaim::STATUS_UNDER_REVIEW,
                            'reviewed_at' => $result->status === InsuranceClaim::STATUS_FILED ? null : now(),
                            'metadata' => ['provider_raw' => $result->raw],
                        ])->save();
                    }
                } catch (\Throwable $e) {
                    \Log::warning('Insurance fileClaim provider call failed', ['error' => $e->getMessage()]);
                }
            }

            $insurance->forceFill(['status' => BookingInsurance::STATUS_CLAIMED])->save();

            ActivityLogger::log('insurance.claim_filed', $claim, [
                'insurance_id' => $insurance->id,
                'amount_claimed_cents' => $amountClaimedCents,
            ]);

            return $claim->fresh();
        });
    }

    public function applyWebhookUpdate(InsuranceWebhookUpdate $update): ?object
    {
        if ($update->target === InsuranceWebhookUpdate::TARGET_POLICY) {
            $policy = BookingInsurance::query()
                ->where('external_provider', $this->provider->name())
                ->where('external_id', $update->externalId)
                ->first();

            if (! $policy) {
                return null;
            }

            $newStatus = match ($update->newStatus) {
                'active', 'policy.active' => BookingInsurance::STATUS_ACTIVE,
                'cancelled', 'policy.cancelled', 'policy.cancel' => BookingInsurance::STATUS_CANCELLED,
                'expired', 'policy.expired' => BookingInsurance::STATUS_EXPIRED,
                default => $policy->status,
            };

            $policy->forceFill([
                'status' => $newStatus,
                'cancelled_at' => $newStatus === BookingInsurance::STATUS_CANCELLED ? now() : $policy->cancelled_at,
                'metadata' => array_merge((array) $policy->metadata, ['webhook_raw' => $update->raw]),
            ])->save();

            return $policy->fresh();
        }

        if ($update->target === InsuranceWebhookUpdate::TARGET_CLAIM) {
            $claim = InsuranceClaim::query()
                ->where('external_claim_id', $update->externalId)
                ->first();

            if (! $claim) {
                return null;
            }

            $newStatus = match ($update->newStatus) {
                'accepted', 'claim.accepted' => InsuranceClaim::STATUS_ACCEPTED,
                'rejected', 'claim.rejected' => InsuranceClaim::STATUS_REJECTED,
                'paid', 'claim.paid' => InsuranceClaim::STATUS_PAID,
                'under_review', 'claim.under_review' => InsuranceClaim::STATUS_UNDER_REVIEW,
                'info_requested', 'claim.info_requested' => InsuranceClaim::STATUS_INFO_REQUESTED,
                default => $claim->status,
            };

            $claim->forceFill([
                'status' => $newStatus,
                'amount_settled_cents' => $update->amountSettledCents ?? $claim->amount_settled_cents,
                'decision_reason' => $update->reason ?? $claim->decision_reason,
                'decided_at' => in_array($newStatus, [
                    InsuranceClaim::STATUS_ACCEPTED, InsuranceClaim::STATUS_REJECTED, InsuranceClaim::STATUS_PAID,
                ], true) ? now() : $claim->decided_at,
                'paid_at' => $newStatus === InsuranceClaim::STATUS_PAID ? now() : $claim->paid_at,
                'metadata' => array_merge((array) $claim->metadata, ['webhook_raw' => $update->raw]),
            ])->save();

            return $claim->fresh();
        }

        return null;
    }

    public function provider(): InsuranceProviderInterface
    {
        return $this->provider;
    }
}
