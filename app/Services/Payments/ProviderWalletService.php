<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\ProviderPayout;
use App\Models\ProviderWalletTransaction;
use App\Models\User;
use App\Support\ActivityLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Wallet provider unifié — ledger immuable type comptabilité partie double.
 *
 * Chaque opération crée une `ProviderWalletTransaction` avec idempotency_key
 * pour éviter les doublons sur retry de webhooks.
 *
 * Solde = somme des credits - somme des debits (filtré par status).
 */
class ProviderWalletService
{
    public const MIN_WITHDRAW_AMOUNT = 10.0;

    public function balance(int $providerUserId, string $currency = 'EUR'): array
    {
        $base = ProviderWalletTransaction::query()
            ->forProvider($providerUserId)
            ->where('currency', $currency);

        $availableCredits = (float) (clone $base)
            ->availableBalance()
            ->where('direction', ProviderWalletTransaction::DIRECTION_CREDIT)
            ->sum('amount');

        // TOUT DÉBIT NON ANNULÉ, et pas seulement ceux déjà aboutis : un retrait demandé est de
        // l'argent promis, qui ne peut plus être promis une seconde fois. Voir
        // ProviderWalletTransaction::scopeEngagedDebit().
        $availableDebits = (float) (clone $base)
            ->engagedDebit()
            ->sum('amount');

        $pendingCredits = (float) (clone $base)
            ->where('status', ProviderWalletTransaction::STATUS_PENDING)
            ->where('direction', ProviderWalletTransaction::DIRECTION_CREDIT)
            ->sum('amount');

        $available = round($availableCredits - $availableDebits, 2);
        $pending = round($pendingCredits, 2);

        return [
            'currency' => $currency,
            'available' => max(0.0, $available),
            'pending' => max(0.0, $pending),
            'total' => round(max(0.0, $available) + $pending, 2),
        ];
    }

    /**
     * QUI A RÉELLEMENT FAIT LE TRAVAIL — et donc qui doit être crédité ou repris.
     *
     * Le portefeuille lisait `bookings.employe_id`, le nom resté sur la COMMANDE. Or
     * `completeMission()` désigne le bénéficiaire du VERSEMENT par la mission
     * (`lead_provider_user_id`), et les deux divergent dès qu'une mission est réassignée — la
     * réservation garde l'ancien nom — comme pour toute mission qu'une société confie à un salarié.
     *
     * La ligne comptable nommait alors une personne pendant que le solde retirable allait à une
     * autre. Chacune des deux moitiés, lue seule, semblait juste : c'est ce qui l'a rendue
     * invisible.
     *
     * La réservation reste en repli — pour une mission qui ne désigne personne, elle est la seule
     * information disponible, et les parcours qui n'ont jamais divergé gardent leur comportement.
     */
    protected function intervenant(Booking $booking): int
    {
        return (int) ($booking->missions()->latest('id')->value('lead_provider_user_id')
            ?? $booking->employe_id
            ?? $booking->assigned_provider_user_id
            ?? 0);
    }

    public function recordEarning(Booking $booking, ?array $intent = null): ?ProviderWalletTransaction
    {
        $providerId = $this->intervenant($booking);
        if (! $providerId) {
            return null;
        }

        $providerAmount = $booking->provider_amount_cents !== null
            ? round((float) $booking->provider_amount_cents / 100, 2)
            : (float) $booking->devis_estime;

        $platformFee = $booking->platform_fee_cents !== null
            ? round((float) $booking->platform_fee_cents / 100, 2)
            : 0.0;

        $currency = strtoupper((string) ($booking->currency ?? 'EUR'));
        $idempotencyKey = sprintf('earning:booking:%d:pi:%s', $booking->id, $intent['id'] ?? $booking->stripe_payment_intent_id ?? 'n/a');

        $existing = ProviderWalletTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($booking, $providerId, $providerAmount, $platformFee, $currency, $idempotencyKey, $intent) {
            $earning = ProviderWalletTransaction::create([
                'provider_user_id' => $providerId,
                'type' => ProviderWalletTransaction::TYPE_EARNING,
                'direction' => ProviderWalletTransaction::DIRECTION_CREDIT,
                'amount' => $providerAmount,
                'currency' => $currency,
                'status' => ProviderWalletTransaction::STATUS_AVAILABLE,
                'source_type' => 'booking',
                'source_id' => $booking->id,
                'stripe_payment_intent_id' => $intent['id'] ?? $booking->stripe_payment_intent_id,
                'idempotency_key' => $idempotencyKey,
                'description' => 'Mission '.($booking->booking_reference ?? '#'.$booking->id),
                'occurred_at' => now(),
            ]);

            // M4 — do NOT debit the platform fee here. provider_amount_cents is already the NET
            // (total − commission), which is exactly what Stripe transfers to the Connect account
            // via the destination charge. The previous extra TYPE_PLATFORM_FEE debit made the
            // wallet balance = net − fee = total − 2×commission, systematically under-crediting the
            // provider. Crediting net only keeps the internal wallet consistent with the real payout.

            ActivityLogger::log('wallet.earning_recorded', $booking, [
                'provider_user_id' => $providerId,
                'amount' => $providerAmount,
                'platform_fee' => $platformFee,
            ]);

            return $earning;
        });
    }

    public function recordTip(Booking $booking, float $amount, ?string $sourceRef = null): ?ProviderWalletTransaction
    {
        $providerId = $this->intervenant($booking);
        if (! $providerId || $amount <= 0) {
            return null;
        }

        $idempotencyKey = sprintf('tip:booking:%d:%s', $booking->id, $sourceRef ?? md5((string) $amount));

        $existing = ProviderWalletTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        return ProviderWalletTransaction::create([
            'provider_user_id' => $providerId,
            'type' => ProviderWalletTransaction::TYPE_TIP,
            'direction' => ProviderWalletTransaction::DIRECTION_CREDIT,
            'amount' => round($amount, 2),
            'currency' => strtoupper((string) ($booking->currency ?? 'EUR')),
            'status' => ProviderWalletTransaction::STATUS_AVAILABLE,
            'source_type' => 'booking',
            'source_id' => $booking->id,
            'idempotency_key' => $idempotencyKey,
            'description' => 'Pourboire',
            'occurred_at' => now(),
        ]);
    }

    public function recordRefundClawback(Booking $booking, float $amount, ?string $stripeChargeId = null): ?ProviderWalletTransaction
    {
        $providerId = $this->intervenant($booking);
        if (! $providerId || $amount <= 0) {
            return null;
        }

        $idempotencyKey = sprintf('refund:booking:%d:%s', $booking->id, $stripeChargeId ?? 'manual');

        $existing = ProviderWalletTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        return ProviderWalletTransaction::create([
            'provider_user_id' => $providerId,
            'type' => ProviderWalletTransaction::TYPE_REFUND_CLAWBACK,
            'direction' => ProviderWalletTransaction::DIRECTION_DEBIT,
            'amount' => round($amount, 2),
            'currency' => strtoupper((string) ($booking->currency ?? 'EUR')),
            'status' => ProviderWalletTransaction::STATUS_AVAILABLE,
            'source_type' => 'booking',
            'source_id' => $booking->id,
            'idempotency_key' => $idempotencyKey,
            'description' => 'Refund clawback',
            'occurred_at' => now(),
            'metadata' => ['stripe_charge_id' => $stripeChargeId],
        ]);
    }

    public function recordPayout(ProviderPayout $payout): ProviderWalletTransaction
    {
        $idempotencyKey = sprintf('payout:%d', $payout->id);

        $existing = ProviderWalletTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        return ProviderWalletTransaction::create([
            'provider_user_id' => $payout->provider_user_id,
            'type' => ProviderWalletTransaction::TYPE_PAYOUT,
            'direction' => ProviderWalletTransaction::DIRECTION_DEBIT,
            'amount' => (float) $payout->amount,
            'currency' => strtoupper((string) ($payout->currency ?? 'EUR')),
            'status' => ProviderWalletTransaction::STATUS_PROCESSING,
            'source_type' => 'provider_payout',
            'source_id' => $payout->id,
            'stripe_payout_id' => $payout->provider_payout_id,
            'idempotency_key' => $idempotencyKey,
            'description' => 'Retrait Stripe',
            'occurred_at' => now(),
        ]);
    }

    /**
     * LES FRAIS D'UN VIREMENT EXPRESS, ÉCRITS COMME TELS.
     *
     * `ExpressPayoutService` calculait ses frais et les rangeait dans les métadonnées de la ligne
     * de versement. Une métadonnée ne se totalise pas, ne se rapproche d'aucun relevé et n'entre
     * dans aucun export comptable : la plateforme prélevait donc une somme que ses propres
     * comptes ignoraient, pendant que le prestataire était débité du brut.
     *
     * NE PAS CONFONDRE AVEC LE DÉFAUT M4. La commission ordinaire est déduite À LA SOURCE — le
     * gain est crédité NET — et lui ajouter un débit `platform_fee` déduisait deux fois, ce qu'un
     * test voisin interdit désormais explicitement. Les frais express sont l'inverse : un
     * prélèvement RÉEL et distinct, sur un montant déjà crédité, qui doit donc exister comme
     * écriture. Le `source_type` `provider_payout` les distingue sans ambiguïté.
     */
    public function recordExpressFee(ProviderPayout $payout, int $feeCents): ?ProviderWalletTransaction
    {
        if ($feeCents <= 0) {
            return null;
        }

        $idempotencyKey = sprintf('express_fee:payout:%d', $payout->id);

        $existing = ProviderWalletTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        return ProviderWalletTransaction::create([
            'provider_user_id' => $payout->provider_user_id,
            'type' => ProviderWalletTransaction::TYPE_PLATFORM_FEE,
            'direction' => ProviderWalletTransaction::DIRECTION_DEBIT,
            'amount' => round($feeCents / 100, 2),
            'currency' => strtoupper((string) ($payout->currency ?? 'EUR')),
            'status' => ProviderWalletTransaction::STATUS_AVAILABLE,
            'source_type' => 'provider_payout',
            'source_id' => $payout->id,
            'idempotency_key' => $idempotencyKey,
            'description' => 'Frais de virement express',
            'occurred_at' => now(),
        ]);
    }

    public function markPayoutCleared(ProviderPayout $payout, ?string $stripePayoutId = null): void
    {
        ProviderWalletTransaction::query()
            ->where('source_type', 'provider_payout')
            ->where('source_id', $payout->id)
            ->where('type', ProviderWalletTransaction::TYPE_PAYOUT)
            ->update([
                'status' => ProviderWalletTransaction::STATUS_CLEARED,
                'stripe_payout_id' => $stripePayoutId ?? $payout->provider_payout_id,
            ]);
    }

    public function reversePayout(ProviderPayout $payout, string $reason): void
    {
        ProviderWalletTransaction::query()
            ->where('source_type', 'provider_payout')
            ->where('source_id', $payout->id)
            ->where('type', ProviderWalletTransaction::TYPE_PAYOUT)
            ->update([
                'status' => ProviderWalletTransaction::STATUS_REVERSED,
                'metadata' => ['reversed_reason' => $reason],
            ]);
    }

    public function requestWithdraw(User $provider, float $amount, string $currency = 'EUR'): ProviderPayout
    {
        if ($amount < self::MIN_WITHDRAW_AMOUNT) {
            throw ValidationException::withMessages([
                'amount' => sprintf('Le montant minimum de retrait est %.2f %s.', self::MIN_WITHDRAW_AMOUNT, $currency),
            ]);
        }

        $balance = $this->balance($provider->id, $currency);
        if ($balance['available'] < $amount) {
            throw ValidationException::withMessages([
                'amount' => sprintf('Solde insuffisant (disponible : %.2f %s).', $balance['available'], $currency),
            ]);
        }

        $profile = $provider->providerProfile;
        if (! $profile || ! $profile->isStripeConnected()) {
            throw ValidationException::withMessages([
                'amount' => 'Votre compte Stripe Connect n\'est pas actif. Complétez votre onboarding.',
            ]);
        }

        return DB::transaction(function () use ($provider, $amount, $currency) {
            $payout = ProviderPayout::create([
                'provider_user_id' => $provider->id,
                'amount' => round($amount, 2),
                'currency' => strtoupper($currency),
                'status' => ProviderPayout::STATUS_PENDING,
                'provider' => 'stripe_connect',
                'period_start' => Carbon::now()->startOfDay()->toDateString(),
                'period_end' => Carbon::now()->endOfDay()->toDateString(),
                'metadata' => ['source' => 'on_demand_withdraw'],
            ]);

            $this->recordPayout($payout);

            ActivityLogger::log('wallet.withdraw_requested', $payout, [
                'provider_user_id' => $provider->id,
                'amount' => $amount,
            ]);

            return $payout;
        });
    }

    public function transactionHistory(int $providerUserId, int $limit = 50)
    {
        return ProviderWalletTransaction::query()
            ->forProvider($providerUserId)
            ->latest('occurred_at')
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
