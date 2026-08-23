<?php

namespace App\Services\Payments;

use App\Models\Booking;
use App\Models\ProviderPayout;
use App\Models\ProviderWalletTransaction;
use App\Models\User;
use App\Support\ActivityLogger;
use App\Support\International\Devise;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Wallet provider unifié — ledger immuable type comptabilité partie double. */
class ProviderWalletService
{
    public const MIN_WITHDRAW_AMOUNT = 10.0;

    /** LE SOLDE D'UN PRESTATAIRE, DANS SA MONNAIE — PLUS DANS LA NOTRE. */
    public function balance(int $providerUserId, ?string $currency = null): array
    {
        $currency = Devise::normaliser($currency) ?? $this->deviseDuPortefeuille($providerUserId);

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

    /** QUI A RÉELLEMENT FAIT LE TRAVAIL — et donc qui doit être crédité ou repris. */
    protected function intervenant(Booking $booking): int
    {
        return (int) ($booking->intervenantId() ?? 0);
    }

    /** LA MONNAIE DANS LAQUELLE CE PRESTATAIRE EST REELLEMENT PAYE. */
    public function deviseDuPortefeuille(int $providerUserId): string
    {
        $derniere = ProviderWalletTransaction::query()
            ->forProvider($providerUserId)
            ->latest('id')
            ->value('currency');

        return Devise::premiereRenseignee(is_string($derniere) ? $derniere : null);
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

        // L'IDEMPOTENCE PASSE AVANT LE PLAFOND, et l'ordre n'est pas cosmetique.
        $existing = ProviderWalletTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing) {
            return $existing;
        }

        // ON NE REPREND PAS CE QU'ON N'A JAMAIS VERSÉ.
        $creditee = (float) ProviderWalletTransaction::query()
            ->where('source_type', 'booking')
            ->where('source_id', $booking->id)
            ->where('provider_user_id', $providerId)
            ->where('type', ProviderWalletTransaction::TYPE_EARNING)
            ->sum('amount');

        $dejaReprise = (float) ProviderWalletTransaction::query()
            ->where('source_type', 'booking')
            ->where('source_id', $booking->id)
            ->where('provider_user_id', $providerId)
            ->where('type', ProviderWalletTransaction::TYPE_REFUND_CLAWBACK)
            ->sum('amount');

        $reprenable = round($creditee - $dejaReprise, 2);

        if ($reprenable <= 0) {
            return null;
        }

        $amount = min($amount, $reprenable);

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

    /** LES FRAIS D'UN VIREMENT EXPRESS, ÉCRITS COMME TELS. */
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

    /** LE RETRAIT SE FAIT DANS LA MONNAIE DU PORTEFEUILLE. */
    public function requestWithdraw(User $provider, float $amount, ?string $currency = null): ProviderPayout
    {
        $currency = Devise::normaliser($currency) ?? $this->deviseDuPortefeuille((int) $provider->id);

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
