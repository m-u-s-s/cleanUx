<?php

namespace App\Services\CancellationV2;

use App\Models\BookingCancellationV2;
use App\Models\CancellationAudit;
use App\Models\CancellationExemptReason;
use App\Models\CancellationPolicy;
use App\Models\User;
use App\Support\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * CancellationEngine v2 — calcule un quote puis exécute la cancellation.
 *
 *   - quote() : sans side-effects, retourne un CancellationQuote DTO
 *   - execute() : commit BookingCancellationV2 + ajuste statut booking + dispatch
 *     intégrations (Stripe refund, Loyalty forfeit, Promo restore, Insurance cancel)
 *   - override() : admin annule le fee (e.g. raison exceptionnelle hors policy)
 *
 * Soft-fail sur intégrations : si Stripe refund échoue, la cancellation reste
 * persistée + audit row 'refund_failed' + Log warning. Le flow ne casse pas.
 */
class CancellationEngine
{
    public function __construct(protected CancellationPolicyResolver $resolver)
    {
    }

    public function quote(int $bookingId, string $actorRole, ?string $reasonCode = null, ?\DateTimeInterface $at = null): CancellationQuote
    {
        $this->ensureActorRole($actorRole);

        $bookingMeta = $this->fetchBookingMeta($bookingId);
        if (! $bookingMeta) {
            throw ValidationException::withMessages(['booking_id' => 'Booking introuvable.']);
        }

        $now = $at ? CarbonImmutable::instance($at) : now()->toImmutable();
        $scheduled = $bookingMeta['scheduled_at'];
        $hoursBefore = $scheduled
            ? max(0, (int) floor($now->diffInHours($scheduled, false)))
            : 0;

        $resolved = $this->resolver->resolveForBooking($bookingId, $actorRole, $hoursBefore, $now);
        $policy = $resolved['policy'];
        $tier = $resolved['tier'];

        $warnings = [];
        if (! $policy) {
            $warnings[] = 'no_policy_matched';
        } elseif (! $tier) {
            $warnings[] = 'no_tier_matched';
        }

        $exemptApplied = false;
        $feePercent = 0.0;
        $feeFlat = 0;
        if ($tier) {
            $feePercent = (float) $tier->fee_percent;
            $feeFlat = (int) $tier->fee_flat_cents;
        }

        if ($reasonCode && $policy) {
            $reason = $policy->exemptReasons()
                ->where('reason_code', $reasonCode)
                ->where('is_active', true)
                ->first();
            if ($reason) {
                $feePercent = 0.0;
                $feeFlat = 0;
                $exemptApplied = true;
            }
        }

        $amount = (int) $bookingMeta['amount_cents'];
        $currency = $bookingMeta['currency'] ?? (string) Config::get('cancellation_v2.default_currency', 'EUR');

        $feeAmount = (int) round(($amount * $feePercent) / 100) + $feeFlat;
        if ($feeAmount > $amount) {
            $feeAmount = $amount;
        }
        $refundAmount = max(0, $amount - $feeAmount);

        $tierLabel = $tier?->description ?? ($tier ? sprintf('≥%dh : %.0f%%', $tier->min_hours_before, (float) $tier->fee_percent) : null);

        return new CancellationQuote(
            bookingId: $bookingId,
            actorRole: $actorRole,
            bookingAmountCents: $amount,
            currency: $currency,
            policy: $policy,
            tier: $tier,
            feePercent: $feePercent,
            feeAmountCents: $feeAmount,
            refundAmountCents: $refundAmount,
            reasonCode: $reasonCode,
            exemptApplied: $exemptApplied,
            tierLabel: $tierLabel,
            hoursBefore: $hoursBefore,
            warnings: $warnings,
        );
    }

    public function execute(
        int $bookingId,
        User $actor,
        string $actorRole,
        ?string $reasonCode = null,
        ?string $reasonText = null,
        ?string $idempotencyKey = null,
        ?\DateTimeInterface $at = null,
    ): BookingCancellationV2 {
        $this->ensureActorRole($actorRole);

        $idempotencyKey ??= "cancel:booking:{$bookingId}:actor:{$actor->id}";

        if ($existing = BookingCancellationV2::query()->where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }

        $quote = $this->quote($bookingId, $actorRole, $reasonCode, $at);

        $bookingMeta = $this->fetchBookingMeta($bookingId);
        $statusBefore = $bookingMeta['status'] ?? null;
        $statusAfter = (string) Config::get("cancellation_v2.booking_status_after_cancel.{$actorRole}", 'annule');

        return DB::transaction(function () use ($bookingId, $actor, $actorRole, $reasonCode, $reasonText, $idempotencyKey, $quote, $statusBefore, $statusAfter) {
            $row = BookingCancellationV2::create([
                'booking_id' => $bookingId,
                'cancelled_by_user_id' => $actor->id,
                'actor_role' => $actorRole,
                'policy_id' => $quote->policy?->id,
                'tier_id' => $quote->tier?->id,
                'reason_code' => $reasonCode,
                'reason_text' => $reasonText,
                'fee_percent_applied' => $quote->feePercent,
                'fee_amount_cents' => $quote->feeAmountCents,
                'refund_amount_cents' => $quote->refundAmountCents,
                'currency' => $quote->currency,
                'refund_method' => $this->resolveRefundMethod($quote),
                'exempt_applied' => $quote->exemptApplied,
                'booking_status_before' => $statusBefore,
                'booking_status_after' => $statusAfter,
                'idempotency_key' => $idempotencyKey,
                'cancelled_at' => now(),
                'integrations_log' => [],
                'metadata' => ['quote' => $quote->toArray()],
            ]);

            // Update booking status (schema-defensive)
            if (Schema::hasTable('bookings') && Schema::hasColumn('bookings', 'status')) {
                DB::table('bookings')->where('id', $bookingId)->update([
                    'status' => $statusAfter,
                    'cancelled_at' => now(),
                ]);
            }

            CancellationAudit::create([
                'cancellation_id' => $row->id,
                'actor_user_id' => $actor->id,
                'action' => CancellationAudit::ACTION_CREATED,
                'before_state' => ['booking_status' => $statusBefore],
                'after_state' => ['booking_status' => $statusAfter],
                'occurred_at' => now(),
            ]);

            // Dispatch integrations (best-effort, soft-fail)
            $row = $this->runIntegrations($row);

            ActivityLogger::log('cancellation_v2.executed', $row, [
                'booking_id' => $bookingId,
                'actor_role' => $actorRole,
                'fee_amount_cents' => $row->fee_amount_cents,
                'refund_amount_cents' => $row->refund_amount_cents,
            ]);

            return $row->fresh();
        });
    }

    public function override(BookingCancellationV2 $cancellation, User $admin, string $reason): BookingCancellationV2
    {
        if (mb_strlen(trim($reason)) < 10) {
            throw ValidationException::withMessages(['reason' => 'Raison de l\'override trop courte (10 caractères min).']);
        }

        $before = [
            'fee_amount_cents' => $cancellation->fee_amount_cents,
            'refund_amount_cents' => $cancellation->refund_amount_cents,
        ];

        $newRefund = (int) $cancellation->fee_amount_cents + (int) $cancellation->refund_amount_cents;

        $cancellation->forceFill([
            'fee_percent_applied' => 0,
            'fee_amount_cents' => 0,
            'refund_amount_cents' => $newRefund,
            'exempt_applied' => true,
            'override_admin_user_id' => $admin->id,
            'override_reason' => mb_substr($reason, 0, 2000),
        ])->save();

        CancellationAudit::create([
            'cancellation_id' => $cancellation->id,
            'actor_user_id' => $admin->id,
            'action' => CancellationAudit::ACTION_OVERRIDDEN,
            'before_state' => $before,
            'after_state' => [
                'fee_amount_cents' => 0,
                'refund_amount_cents' => $newRefund,
            ],
            'notes' => $reason,
            'occurred_at' => now(),
        ]);

        ActivityLogger::log('cancellation_v2.overridden', $cancellation, [
            'admin_id' => $admin->id,
            'new_refund_cents' => $newRefund,
        ]);

        return $cancellation->fresh();
    }

    protected function runIntegrations(BookingCancellationV2 $row): BookingCancellationV2
    {
        $integrationsCfg = (array) Config::get('cancellation_v2.integrations', []);
        $log = (array) ($row->integrations_log ?? []);

        // Stripe refund (best-effort)
        if (! empty($integrationsCfg['stripe_refund']) && $row->refund_amount_cents > 0 && $row->refund_method === 'stripe') {
            try {
                $log['stripe_refund'] = $this->tryStripeRefund($row);
                CancellationAudit::create([
                    'cancellation_id' => $row->id,
                    'actor_user_id' => $row->cancelled_by_user_id,
                    'action' => CancellationAudit::ACTION_REFUNDED,
                    'after_state' => $log['stripe_refund'],
                    'occurred_at' => now(),
                ]);
            } catch (\Throwable $e) {
                $log['stripe_refund_error'] = $e->getMessage();
                CancellationAudit::create([
                    'cancellation_id' => $row->id,
                    'actor_user_id' => $row->cancelled_by_user_id,
                    'action' => CancellationAudit::ACTION_REFUND_FAILED,
                    'notes' => $e->getMessage(),
                    'occurred_at' => now(),
                ]);
                Log::warning('CancellationEngine: stripe refund failed', [
                    'cancellation_id' => $row->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Loyalty forfeit (if module available)
        if (! empty($integrationsCfg['loyalty_forfeit'])
            && class_exists(\App\Services\Loyalty\LoyaltyService::class)) {
            try {
                $log['loyalty'] = ['notified' => true];
                // Hook : si la cancellation est ≤ window, on devrait forfeit les points
                // gagnés via cette booking. Skeleton call ici, à câbler selon flow exact.
            } catch (\Throwable $e) {
                $log['loyalty_error'] = $e->getMessage();
            }
        }

        // Promo restore
        if (! empty($integrationsCfg['promo_restore'])
            && Schema::hasTable('promo_code_redemptions')) {
            try {
                $restored = DB::table('promo_code_redemptions')
                    ->where('booking_id', $row->booking_id)
                    ->update(['status' => 'reversed', 'updated_at' => now()]);
                $log['promo_restore'] = ['rows_reversed' => $restored];
            } catch (\Throwable $e) {
                $log['promo_restore_error'] = $e->getMessage();
            }
        }

        // Insurance cancel (auto-cancel related insurance policies)
        if (! empty($integrationsCfg['insurance_cancel'])
            && class_exists(\App\Services\Insurance\InsuranceService::class)
            && class_exists(\App\Models\BookingInsurance::class)) {
            try {
                $svc = app(\App\Services\Insurance\InsuranceService::class);
                $insurances = \App\Models\BookingInsurance::query()
                    ->where('booking_id', $row->booking_id)
                    ->whereIn('status', [
                        \App\Models\BookingInsurance::STATUS_ACTIVE,
                        \App\Models\BookingInsurance::STATUS_PROPOSED,
                    ])
                    ->get();
                $cancelled = 0;
                foreach ($insurances as $insurance) {
                    $svc->cancel($insurance);
                    $cancelled++;
                }
                $log['insurance_cancel'] = ['cancelled_count' => $cancelled];
            } catch (\Throwable $e) {
                $log['insurance_cancel_error'] = $e->getMessage();
            }
        }

        $row->forceFill(['integrations_log' => $log])->save();
        return $row;
    }

    protected function tryStripeRefund(BookingCancellationV2 $row): array
    {
        // Skeleton implementation. À câbler selon ton intégration Stripe :
        //   - récupérer payment_intent_id ou charge_id depuis bookings
        //   - appeler Stripe\Refund::create(['payment_intent' => ..., 'amount' => $row->refund_amount_cents])
        //   - retourner ['refund_id' => ..., 'status' => 'succeeded']
        return [
            'skeleton' => true,
            'refund_amount_cents' => $row->refund_amount_cents,
            'currency' => $row->currency,
            'note' => 'Stripe refund integration is a skeleton — wire to Stripe SDK in prod.',
        ];
    }

    protected function ensureActorRole(string $actorRole): void
    {
        $allowed = (array) Config::get('cancellation_v2.actor_roles', ['client', 'provider', 'admin']);
        if (! in_array($actorRole, $allowed, true)) {
            throw ValidationException::withMessages(['actor_role' => "Actor role '{$actorRole}' non supporté."]);
        }
    }

    protected function resolveRefundMethod(CancellationQuote $quote): ?string
    {
        if ($quote->refundAmountCents <= 0) {
            return 'none';
        }
        return (string) Config::get('cancellation_v2.default_refund_method', 'stripe');
    }

    /**
     * @return array{amount_cents:int, currency:?string, scheduled_at:?CarbonImmutable, status:?string}|null
     */
    protected function fetchBookingMeta(int $bookingId): ?array
    {
        if (! Schema::hasTable('bookings')) {
            return null;
        }
        $row = DB::table('bookings')->where('id', $bookingId)->first();
        if (! $row) {
            return null;
        }

        $amountCents = 0;
        foreach (['payment_amount_cents', 'final_price', 'estimated_price', 'devis_estime'] as $col) {
            if (Schema::hasColumn('bookings', $col) && isset($row->{$col}) && $row->{$col} !== null) {
                $val = (float) $row->{$col};
                $amountCents = $col === 'payment_amount_cents' ? (int) $val : (int) round($val * 100);
                if ($amountCents > 0) {
                    break;
                }
            }
        }

        $scheduledAt = null;
        foreach (['scheduled_at', 'date', 'planned_start_at'] as $col) {
            if (Schema::hasColumn('bookings', $col) && isset($row->{$col})) {
                try {
                    $scheduledAt = CarbonImmutable::parse((string) $row->{$col});
                    break;
                } catch (\Throwable $e) {
                    continue;
                }
            }
        }

        return [
            'amount_cents' => $amountCents,
            'currency' => $row->currency ?? null,
            'scheduled_at' => $scheduledAt,
            'status' => $row->status ?? null,
        ];
    }
}
