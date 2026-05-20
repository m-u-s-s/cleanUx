<?php

namespace App\Services\Tips;

use App\Models\Booking;
use App\Models\BookingTip;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * TipService — création + lifecycle des pourboires post-mission.
 *
 * Workflow :
 *   1. Client → create() : crée un BookingTip status=pending
 *   2. confirmCharge() : payment intent Stripe captured → status=charged
 *   3. payOut() : transfer Stripe Connect au provider → status=paid_out
 *
 * Soft-fail : si modules Stripe Connect absents, on persiste seulement
 * (pour test/dev) — la confirmation manuelle reste possible côté admin.
 */
class TipService
{
    /**
     * Suggestions de montants en fonction du total de la mission.
     * Retourne un tableau de [label, percent, amount_cents].
     */
    public function suggestionsForBooking(Booking $booking): array
    {
        $baseAmount = (int) round(((float) ($booking->devis_estime ?? 0)) * 100);
        if ($baseAmount <= 0) {
            return [];
        }

        $presets = Config::get('tips.presets', [
            ['label' => '10%', 'percent' => 10],
            ['label' => '15%', 'percent' => 15],
            ['label' => '20%', 'percent' => 20],
        ]);

        return collect($presets)->map(function ($p) use ($baseAmount) {
            $amountCents = (int) round($baseAmount * ($p['percent'] / 100));
            return [
                'label' => $p['label'],
                'percent' => (int) $p['percent'],
                'amount_cents' => $amountCents,
                'amount_formatted' => number_format($amountCents / 100, 2, ',', ' ') . ' EUR',
            ];
        })->toArray();
    }

    /**
     * Crée un BookingTip pour cette mission.
     * Throws ValidationException si :
     *  - mission pas terminée
     *  - client n'est pas le client du booking
     *  - amount_cents hors limites (min 100, max 50000 = 500€)
     *  - tip déjà existant non-cancelled
     */
    public function create(
        User $client,
        Booking $booking,
        int $amountCents,
        ?string $presetLabel = null,
        ?int $presetPercent = null,
        ?string $message = null,
    ): BookingTip {
        if ((int) $booking->client_id !== (int) $client->id) {
            throw ValidationException::withMessages([
                'booking' => ['Vous ne pouvez tipper que sur vos propres missions.'],
            ]);
        }

        $allowedStatuses = Config::get('tips.eligible_booking_statuses', ['termine', 'completed', 'closed']);
        if (! in_array($booking->status, $allowedStatuses, true)) {
            throw ValidationException::withMessages([
                'booking' => ['Mission non éligible — terminée requise.'],
            ]);
        }

        $minCents = (int) Config::get('tips.min_amount_cents', 100);
        $maxCents = (int) Config::get('tips.max_amount_cents', 50000);
        if ($amountCents < $minCents || $amountCents > $maxCents) {
            throw ValidationException::withMessages([
                'amount' => ["Montant hors limites ({$minCents} - {$maxCents} cents)."],
            ]);
        }

        // Idempotency : un seul tip non-cancelled par booking par client
        $existing = BookingTip::query()
            ->where('booking_id', $booking->id)
            ->where('client_user_id', $client->id)
            ->whereNotIn('status', [BookingTip::STATUS_CANCELLED, BookingTip::STATUS_FAILED])
            ->first();
        if ($existing) {
            return $existing;
        }

        $providerId = (int) ($booking->employe_id ?? $booking->provider_user_id ?? 0);
        if ($providerId <= 0) {
            throw ValidationException::withMessages([
                'provider' => ['Mission sans prestataire assigné.'],
            ]);
        }

        $bonusPercent = (float) Config::get('tips.client_bonus_points_per_euro', 1.0);
        $bonusPoints = (int) floor(($amountCents / 100) * $bonusPercent);

        return DB::transaction(function () use ($client, $booking, $providerId, $amountCents, $presetLabel, $presetPercent, $message, $bonusPoints) {
            return BookingTip::query()->create([
                'code' => BookingTip::generateCode(),
                'booking_id' => $booking->id,
                'client_user_id' => $client->id,
                'provider_user_id' => $providerId,
                'amount_cents' => $amountCents,
                'currency' => 'EUR',
                'status' => BookingTip::STATUS_PENDING,
                'preset_label' => $presetLabel,
                'preset_percent' => $presetPercent,
                'message' => $message,
                'client_bonus_points' => $bonusPoints,
            ]);
        });
    }

    /**
     * Marque un tip comme chargé (Stripe payment intent succeeded).
     * Soft-fail sur attribution loyalty points (ne bloque pas si module absent).
     */
    public function confirmCharge(BookingTip $tip, ?string $paymentIntentId = null): BookingTip
    {
        if ($tip->status === BookingTip::STATUS_CHARGED || $tip->status === BookingTip::STATUS_PAID_OUT) {
            return $tip;
        }
        if ($tip->status !== BookingTip::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Tip non chargeable dans cet état.'],
            ]);
        }

        $tip->update([
            'status' => BookingTip::STATUS_CHARGED,
            'stripe_payment_intent_id' => $paymentIntentId ?? $tip->stripe_payment_intent_id,
            'charged_at' => now(),
        ]);

        // Crédite les bonus points loyalty au client (soft-fail)
        if ($tip->client_bonus_points > 0) {
            $this->awardLoyaltyBonus($tip);
        }

        return $tip->fresh();
    }

    /**
     * Marque un tip comme payé au provider (Stripe transfer).
     */
    public function markPaidOut(BookingTip $tip, ?string $transferId = null): BookingTip
    {
        if ($tip->status === BookingTip::STATUS_PAID_OUT) {
            return $tip;
        }
        if ($tip->status !== BookingTip::STATUS_CHARGED) {
            throw ValidationException::withMessages([
                'status' => ['Tip doit être chargé avant payout.'],
            ]);
        }

        $tip->update([
            'status' => BookingTip::STATUS_PAID_OUT,
            'stripe_transfer_id' => $transferId ?? $tip->stripe_transfer_id,
            'paid_out_at' => now(),
        ]);
        return $tip->fresh();
    }

    public function markFailed(BookingTip $tip, string $reason): BookingTip
    {
        $meta = $tip->metadata ?? [];
        $meta['failure_reason'] = $reason;
        $meta['failed_at'] = now()->toIso8601String();
        $tip->update([
            'status' => BookingTip::STATUS_FAILED,
            'metadata' => $meta,
        ]);
        return $tip->fresh();
    }

    public function cancel(BookingTip $tip): BookingTip
    {
        if ($tip->status !== BookingTip::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Seul un tip pending peut être annulé.'],
            ]);
        }
        $tip->update(['status' => BookingTip::STATUS_CANCELLED]);
        return $tip->fresh();
    }

    protected function awardLoyaltyBonus(BookingTip $tip): void
    {
        try {
            if (! class_exists(\App\Services\Loyalty\LoyaltyService::class)) {
                return;
            }
            $loyalty = app(\App\Services\Loyalty\LoyaltyService::class);
            $loyalty->award(
                user: $tip->client,
                type: 'earn_adjustment',
                points: $tip->client_bonus_points,
                source: $tip,
                idempotencyKey: 'tip_bonus_' . $tip->id,
                reason: "Bonus pourboire mission #{$tip->booking_id}",
            );
        } catch (\Throwable $e) {
            Log::warning('[tips] loyalty bonus failed', [
                'tip_id' => $tip->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
