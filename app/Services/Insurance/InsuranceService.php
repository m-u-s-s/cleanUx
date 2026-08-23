<?php

namespace App\Services\Insurance;

use App\Models\BookingInsurance;
use App\Models\InsuranceClaim;
use App\Models\InsurancePlan;
use App\Models\User;
use App\Support\Accounting\BookingAutoPoster;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** InsuranceService — facade: orchestre la vie d'une police + délègue les claims. */
class InsuranceService
{
    public function __construct(
        protected InsuranceProviderInterface $provider,
        protected InsurancePricingEngine $pricing,
        protected InsuranceClaimsService $claims,
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

            $fresh = $insurance->fresh();

            // Audit MEDIUM — écriture GL de la prime (dette assureur) si police active.
            if ($fresh->status === BookingInsurance::STATUS_ACTIVE) {
                BookingAutoPoster::postInsurance($fresh);
            }

            return $fresh;
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
        return $this->claims->fileClaim(
            $insurance,
            $claimant,
            $incidentType,
            $description,
            $incidentDate,
            $amountClaimedCents,
            $evidence,
            $idempotencyKey,
        );
    }

    public function updateClaimStatus(InsuranceClaim $claim, string $newStatus, ?string $notes = null): InsuranceClaim
    {
        return $this->claims->updateClaimStatus($claim, $newStatus, $notes);
    }

    public function applyWebhookUpdate(InsuranceWebhookUpdate $update): ?object
    {
        return $this->claims->applyWebhookUpdate($update);
    }

    public function provider(): InsuranceProviderInterface
    {
        return $this->provider;
    }
}
