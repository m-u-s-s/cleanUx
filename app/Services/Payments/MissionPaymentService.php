<?php

namespace App\Services\Payments;

use App\Models\RendezVous;
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
        $amount = (int) round(((float) $rendezVous->devis_estime) * 100);

        $intent = PaymentIntent::create([
            'amount' => $amount,
            'currency' => strtolower($rendezVous->pricing_snapshot['currency'] ?? 'eur'),
            'payment_method' => $paymentMethodId,
            'customer' => $rendezVous->client?->stripe_id,
            'confirm' => true,
            'capture_method' => 'manual',
            'description' => 'CleanUx mission '.$rendezVous->booking_reference,
            'metadata' => [
                'rendez_vous_id' => $rendezVous->id,
                'booking_reference' => $rendezVous->booking_reference,
                'client_id' => $rendezVous->client_id,
            ],
        ]);

        $rendezVous->update([
            'stripe_payment_intent_id' => $intent->id,
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

    public function cancel(RendezVous $rendezVous): ?PaymentIntent
    {
        if (! $rendezVous->stripe_payment_intent_id) {
            return null;
        }

        if ($rendezVous->payment_status !== 'authorized') {
            return null;
        }

        $intent = PaymentIntent::retrieve($rendezVous->stripe_payment_intent_id);
        $intent->cancel();

        $rendezVous->update([
            'payment_status' => 'cancelled',
            'payment_cancelled_at' => now(),
        ]);

        return $intent;
    }
}