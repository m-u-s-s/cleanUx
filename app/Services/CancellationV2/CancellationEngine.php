<?php

namespace App\Services\CancellationV2;

use App\Models\BookingCancellationV2;
use App\Models\BookingStatusHistory;
use App\Models\CancellationAudit;
use App\Models\User;
use App\Services\Cancellation\CancellationExemptQuota;
use App\Support\ActivityLogger;
use App\Support\Domain\BookingStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
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
    public function __construct(
        protected CancellationPolicyResolver $resolver,
        protected CancellationIntegrationsRunner $integrations,
    ) {}

    /**
     * @param  ?int  $actorUserId  L'auteur, quand on le connaît : sans lui, le plafond d'exemptions
     *                             par personne ne peut pas être consulté et un motif généreux
     *                             exonérerait autant de fois que voulu.
     */
    public function quote(int $bookingId, string $actorRole, ?string $reasonCode = null, ?\DateTimeInterface $at = null, ?int $actorUserId = null): CancellationQuote
    {
        $this->ensureActorRole($actorRole);

        $bookingMeta = $this->fetchBookingMeta($bookingId);
        if (! $bookingMeta) {
            throw ValidationException::withMessages(['booking_id' => 'Booking introuvable.']);
        }

        // B1 — terminal-state guard: a booking that is already completed (delivered + paid)
        // or already cancelled must not be cancelled. Without this, a completed mission could
        // be re-cancelled, triggering a real Stripe refund + loyalty/promo reversal.
        $status = $bookingMeta['status'] ?? null;
        if ($status !== null && in_array($status, BookingStatus::nonCancellableAliases(), true)) {
            throw ValidationException::withMessages([
                'status' => 'Impossible d\'annuler une réservation déjà terminée ou annulée.',
            ]);
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

            /*
             * LE PLAFOND PAR PERSONNE MORD ICI — « pas la première fois, mais si c'est fréquent ».
             *
             * `max_per_user_per_30d` était déclarée sur la table, semée à 2 pour l'urgence médicale,
             * et appliquée par PERSONNE : le motif le plus généreux du barème exonérait donc autant
             * de fois que voulu.
             *
             * Le dépassement retire l'EXEMPTION, pas le motif : `reason_code` reste enregistré,
             * précisément pour qu'on puisse relire qu'une personne l'a invoqué six fois en un mois.
             */
            if ($reason && app(CancellationExemptQuota::class)->exonereEncore($reason, $actorUserId)) {
                $feePercent = 0.0;
                $feeFlat = 0;
                $exemptApplied = true;
            } elseif ($reason) {
                $warnings[] = 'exempt_quota_exceeded';
            }
        }

        $amount = (int) $bookingMeta['amount_cents'];
        $currency = $bookingMeta['currency'] ?? (string) Config::get('cancellation_v2.default_currency', 'EUR');

        $feeAmount = (int) round(($amount * $feePercent) / 100) + $feeFlat;

        // En-route penalty: if a CLIENT cancels once the assigned provider is already en route /
        // on site / mid-mission, charge at least a small penalty (a % of the booking amount, 3–5%)
        // even when the time-based tier would be free — they made the provider travel for nothing.
        // Waived if a valid exempt reason (e.g. medical) was applied.
        $enRoutePenaltyPercent = (float) Config::get('cancellation_v2.en_route_penalty_percent', 0);
        if ($actorRole === 'client'
            && $enRoutePenaltyPercent > 0
            && ! $exemptApplied
            && $this->providerIsEnRoute($bookingId)) {
            $enRoutePenalty = (int) round(($amount * $enRoutePenaltyPercent) / 100);
            if ($feeAmount < $enRoutePenalty) {
                $feeAmount = $enRoutePenalty;
                $warnings[] = 'en_route_penalty_applied';
            }
        }

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

        $quote = $this->quote($bookingId, $actorRole, $reasonCode, $at, $actor->id);

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

                /*
                 * CETTE ÉCRITURE-CI NE PASSE PAS PAR L'OBSERVATEUR.
                 *
                 * `BookingObserver::consignerLeChangementDeStatut()` tient l'historique des statuts,
                 * mais il est accroché aux événements d'Eloquent — et la ligne ci-dessus emploie le
                 * constructeur de requêtes, qui n'en déclenche aucun. Sans cette consignation
                 * explicite, l'annulation serait le SEUL changement de statut absent du journal, et
                 * précisément celui qu'un litige vient interroger.
                 *
                 * On ne convertit pas la mise à jour en Eloquent pour autant : l'observateur émet
                 * aussi des webhooks métier et des événements d'analytique, et les déclencher ici
                 * changerait le comportement observable d'un chemin qui touche à l'argent.
                 */
                BookingStatusHistory::create([
                    'booking_id' => $bookingId,
                    'changed_by' => $actor->id,
                    'from_status' => $statusBefore,
                    'to_status' => $statusAfter,
                    'note' => 'Annulation',
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
            $row = $this->integrations->run($row);

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
     * Le prestataire est-il déjà parti, arrivé ou en cours ? C'est le déclencheur de la pénalité
     * d'annulation tardive.
     *
     * La mission se rattachait à la réservation par DEUX colonnes selon son chemin de création, et
     * cette méthode interrogeait le schéma pour savoir lesquelles existaient. Une seule subsiste.
     */
    protected function providerIsEnRoute(int $bookingId): bool
    {
        if (! Schema::hasTable('missions') || ! Schema::hasColumn('missions', 'status')) {
            return false;
        }

        $enRouteStatuses = ['en_route', 'arrived', 'started', 'in_mission', 'in_progress', 'sur_place'];

        return DB::table('missions')
            ->where('booking_id', $bookingId)
            ->whereIn('status', $enRouteStatuses)
            ->exists();
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
