<?php

namespace App\Services\Payments;

use App\Models\RendezVous;
use RuntimeException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class MissionPaymentService
{
    public function __construct()
    {
        Stripe::setApiKey(config('cashier.secret'));
    }

    public function authorize(RendezVous $rendezVous, string $paymentMethodId): PaymentIntent
    {
        $rendezVous->loadMissing(['client', 'employe']);

        $employee = $rendezVous->employe;

        if (! $employee || ! $employee->canReceiveStripeConnectPayments()) {
            throw new RuntimeException('Le prestataire ne peut pas encore recevoir de paiements Stripe Connect.');
        }

        if (! $rendezVous->client?->stripe_id) {
            $rendezVous->client?->createAsStripeCustomer();
            $rendezVous->refresh()->loadMissing('client');
        }

        $amount = (int) round(((float) $rendezVous->devis_estime) * 100);
        $feePercent = (float) env('CLEANUX_PLATFORM_FEE_PERCENT', 20);

        $platformFee = (int) round($amount * ($feePercent / 100));
        $providerAmount = $amount - $platformFee;

        $intent = PaymentIntent::create([
            'amount' => $amount,
            'currency' => strtolower($rendezVous->pricing_snapshot['currency'] ?? 'eur'),
            'customer' => $rendezVous->client->stripe_id,
            'payment_method' => $paymentMethodId,
            'confirm' => true,
            'capture_method' => 'manual',
            'application_fee_amount' => $platformFee,
            'transfer_data' => [
                'destination' => $employee->stripe_connect_account_id,
            ],
            'metadata' => [
                'rendez_vous_id' => $rendezVous->id,
                'booking_reference' => $rendezVous->booking_reference,
                'client_id' => $rendezVous->client_id,
                'employee_id' => $employee->id,
                'platform_fee_cents' => $platformFee,
                'provider_amount_cents' => $providerAmount,
            ],
        ]);

        $rendezVous->update([
            'stripe_payment_intent_id' => $intent->id,
            'stripe_connect_account_id' => $employee->stripe_connect_account_id,
            'payment_amount_cents' => $amount,
            'platform_fee_cents' => $platformFee,
            'provider_amount_cents' => $providerAmount,
            'payment_status' => 'authorized',
            'payment_authorized_at' => now(),
        ]);

        return $intent;
    }

    public function capture(RendezVous $rendezVous): ?PaymentIntent
    {
        if (! $rendezVous->stripe_payment_intent_id) {
            return null;
        }

        if ($rendezVous->payment_status !== 'authorized') {
            return null;
        }

        $intent = PaymentIntent::retrieve($rendezVous->stripe_payment_intent_id);
        $intent->capture();

        $rendezVous->update([
            'payment_status' => 'captured',
            'payment_captured_at' => now(),
        ]);

        return $intent;
    }

    public function markFailed(RendezVous $rendezVous): void
    {
        $rendezVous->update([
            'payment_status' => 'failed',
            'payment_failed_at' => now(),
        ]);
    }
}