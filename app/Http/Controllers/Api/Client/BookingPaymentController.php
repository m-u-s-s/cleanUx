<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\PaymentIntent;
use Stripe\Stripe;

/**
 * @group Client — Booking Payment
 *
 * @authenticated
 */
class BookingPaymentController extends Controller
{
    private function boot(): void
    {
        Stripe::setApiKey(config('cashier.secret'));
    }

    public function createPaymentIntent(Request $request, Booking $booking): JsonResponse
    {
        $user = $request->user();

        // Booking uses client_id (not user_id) — Uber-like convention
        if ((int) $booking->client_id !== (int) $user->id) {
            return response()->json(['error' => 'forbidden'], 403);
        }

        $amount = (float) ($booking->devis_estime ?? 0);

        if ($amount <= 0) {
            return response()->json(['error' => 'Booking has no price set.'], 422);
        }

        $this->boot();

        // Create Stripe customer if not yet created (Cashier convention)
        if (! $user->stripe_id) {
            $user->createAsStripeCustomer();
            $user->refresh();
        }

        $currency = strtolower($booking->pricing_snapshot['currency'] ?? 'eur');
        $amountCents = (int) round($amount * 100);

        $intent = PaymentIntent::create([
            'amount' => $amountCents,
            'currency' => $currency,
            'customer' => $user->stripe_id,
            'capture_method' => 'automatic',
            'metadata' => [
                'booking_id' => $booking->id,
                'client_id' => $user->id,
                'booking_reference' => $booking->booking_reference ?? null,
            ],
        ]);

        return response()->json([
            'client_secret' => $intent->client_secret,
            'payment_intent_id' => $intent->id,
            'amount' => $amount,
            'amount_cents' => $amountCents,
            'currency' => $currency,
        ]);
    }
}
