<?php

namespace App\Services\Insurance;

use App\Models\BookingInsurance;
use App\Models\InsuranceClaim;
use App\Models\User;
use App\Services\Notifications\SmsService;
use App\Support\ActivityLogger;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** InsuranceClaimsService — lifecycle of insurance claims. */
class InsuranceClaimsService
{
    public function __construct(
        protected InsuranceProviderInterface $provider,
    ) {}

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

        $idempotencyKey ??= "claim:insurance:{$insurance->id}:".hash('sha256', $description.':'.$incidentDate->format('Y-m-d'));

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

    /** Transitions d'état d'une réclamation avec machine à états explicite. */
    public function updateClaimStatus(InsuranceClaim $claim, string $newStatus, ?string $notes = null): InsuranceClaim
    {
        $validTransitions = [
            InsuranceClaim::STATUS_FILED => [InsuranceClaim::STATUS_UNDER_REVIEW, InsuranceClaim::STATUS_CANCELLED],
            InsuranceClaim::STATUS_UNDER_REVIEW => [InsuranceClaim::STATUS_ACCEPTED, InsuranceClaim::STATUS_REJECTED, InsuranceClaim::STATUS_INFO_REQUESTED],
            InsuranceClaim::STATUS_INFO_REQUESTED => [InsuranceClaim::STATUS_UNDER_REVIEW, InsuranceClaim::STATUS_CANCELLED],
            InsuranceClaim::STATUS_ACCEPTED => [InsuranceClaim::STATUS_PAID],
            InsuranceClaim::STATUS_REJECTED => [],
            InsuranceClaim::STATUS_PAID => [],
            InsuranceClaim::STATUS_CANCELLED => [],
        ];

        $allowed = $validTransitions[$claim->status] ?? [];
        if (! in_array($newStatus, $allowed, true)) {
            throw new \InvalidArgumentException(
                "Cannot transition claim #{$claim->id} from '{$claim->status}' to '{$newStatus}'. "
                .'Allowed: ['.implode(', ', $allowed).']'
            );
        }

        $claim->forceFill([
            'status' => $newStatus,
            'decision_reason' => $notes ?? $claim->decision_reason,
            'reviewed_at' => $claim->reviewed_at ?? (in_array($newStatus, [
                InsuranceClaim::STATUS_ACCEPTED, InsuranceClaim::STATUS_REJECTED, InsuranceClaim::STATUS_INFO_REQUESTED,
            ], true) ? now() : $claim->reviewed_at),
            'decided_at' => in_array($newStatus, [
                InsuranceClaim::STATUS_ACCEPTED, InsuranceClaim::STATUS_REJECTED,
            ], true) ? now() : $claim->decided_at,
            'paid_at' => $newStatus === InsuranceClaim::STATUS_PAID ? now() : $claim->paid_at,
        ])->save();

        ActivityLogger::log('insurance.claim_status_updated', $claim, [
            'from_status' => $claim->getOriginal('status'),
            'to_status' => $newStatus,
            'notes' => $notes,
        ]);

        $this->notifyClaimStatusChange($claim->fresh(), $newStatus);

        return $claim->fresh();
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

    private function notifyClaimStatusChange(InsuranceClaim $claim, string $newStatus): void
    {
        try {
            $claimant = $claim->claimant;
            if (! $claimant) {
                return;
            }

            $messages = [
                InsuranceClaim::STATUS_UNDER_REVIEW => "Votre réclamation assurance #{$claim->id} est en cours d'examen.",
                InsuranceClaim::STATUS_ACCEPTED => "Bonne nouvelle ! Votre réclamation assurance #{$claim->id} a été acceptée.",
                InsuranceClaim::STATUS_REJECTED => "Votre réclamation assurance #{$claim->id} a été refusée.",
                InsuranceClaim::STATUS_PAID => "Le remboursement de votre réclamation assurance #{$claim->id} a été effectué.",
                InsuranceClaim::STATUS_INFO_REQUESTED => "Des informations supplémentaires sont requises pour votre réclamation #{$claim->id}.",
                InsuranceClaim::STATUS_CANCELLED => "Votre réclamation assurance #{$claim->id} a été annulée.",
            ];

            $msg = $messages[$newStatus] ?? "Mise à jour réclamation #{$claim->id} : {$newStatus}";

            // DB notification (Laravel notifications table)
            DB::table('notifications')->insert([
                'id' => Str::uuid()->toString(),
                'type' => 'insurance_claim_status',
                'notifiable_type' => get_class($claimant),
                'notifiable_id' => $claimant->id,
                'data' => json_encode([
                    'title' => "Réclamation #{$claim->id}",
                    'body' => $msg,
                    'claim_id' => $claim->id,
                    'status' => $newStatus,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // SMS best-effort
            if ($claimant->phone) {
                app(SmsService::class)->send($claimant->phone, "Brio: {$msg}");
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("Insurance claim notification failed: {$e->getMessage()}", [
                'claim_id' => $claim->id,
                'to_status' => $newStatus,
            ]);
        }
    }
}
