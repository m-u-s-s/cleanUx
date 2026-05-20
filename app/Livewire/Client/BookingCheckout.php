<?php

namespace App\Livewire\Client;

use App\Models\Booking;
use App\Services\Payments\MissionPaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Page checkout Stripe Elements pour un Booking en attente de paiement.
 *
 * Workflow :
 *   1. mount(bookingId) — charge le booking + crée/retrouve un SetupIntent
 *      OU un PaymentIntent selon que la mission est facturée upfront ou auth-hold
 *   2. Stripe.js (Elements) → confirm le PaymentIntent côté client
 *   3. onPaymentSuccess (wire:click) → mark booking comme "paiement autorisé"
 *      et redirect vers la confirmation
 *   4. Webhook payment_intent.succeeded en background → finalise.
 *
 * Le client_secret est exposé via $this->clientSecret (rendu dans le data attr du form),
 * jamais persisté ni transmis hors du flux Stripe.
 */
class BookingCheckout extends Component
{
    #[Url]
    public ?int $bookingId = null;

    public ?string $clientSecret = null;
    public ?string $stripePublishableKey = null;
    public string $error = '';
    public bool $processing = false;

    public function mount(?int $bookingId = null): void
    {
        $this->bookingId = $bookingId;
        $this->stripePublishableKey = (string) config('services.stripe.key', '');
    }

    public function startPayment(): void
    {
        $this->error = '';
        $user = Auth::user();
        $booking = $this->bookingId ? Booking::find($this->bookingId) : null;

        if (! $booking || (int) $booking->client_id !== (int) $user->id) {
            $this->error = 'Booking introuvable ou non autorisé.';
            return;
        }

        if (! $user->stripe_id) {
            try {
                $user->createAsStripeCustomer();
            } catch (\Throwable $e) {
                $this->error = 'Stripe customer creation failed: ' . $e->getMessage();
                return;
            }
        }

        try {
            $intent = $user->createSetupIntent([
                'payment_method_types' => ['card'],
                'usage' => 'on_session',
                'metadata' => [
                    'booking_id' => $booking->id,
                    'cleanux_action' => 'booking_checkout',
                ],
            ]);
            $this->clientSecret = $intent->client_secret;
        } catch (\Throwable $e) {
            $this->error = 'Impossible de créer le SetupIntent : ' . $e->getMessage();
        }
    }

    public function confirmAuthorization(string $paymentMethodId): void
    {
        $this->processing = true;
        $this->error = '';
        $user = Auth::user();
        $booking = $this->bookingId ? Booking::find($this->bookingId) : null;

        if (! $booking || (int) $booking->client_id !== (int) $user->id) {
            $this->error = 'Booking introuvable.';
            $this->processing = false;
            return;
        }

        try {
            app(MissionPaymentService::class)->authorize($booking, $paymentMethodId);
            $this->dispatch('toast', 'Paiement autorisé. Vous serez débité quand la mission débute.', 'success');
            $this->redirect(route('dashboard.client') . '?booking=' . $booking->id, navigate: true);
        } catch (\Throwable $e) {
            $this->error = 'Erreur paiement : ' . $e->getMessage();
        } finally {
            $this->processing = false;
        }
    }

    public function render(): View
    {
        $user = Auth::user();
        $booking = $this->bookingId
            ? Booking::query()->where('id', $this->bookingId)->where('client_id', $user->id)->first()
            : null;

        return view('livewire.client.booking-checkout', [
            'booking' => $booking,
        ])->layout('layouts.app');
    }
}
