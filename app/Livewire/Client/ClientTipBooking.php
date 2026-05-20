<?php

namespace App\Livewire\Client;

use App\Models\Booking;
use App\Models\BookingTip;
use App\Services\Tips\TipService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Attributes\Url;

class ClientTipBooking extends Component
{
    #[Url]
    public ?int $bookingId = null;

    public ?int $selectedAmountCents = null;
    public ?string $selectedPresetLabel = null;
    public ?int $selectedPresetPercent = null;
    public string $message = '';
    public string $customAmount = '';

    public function mount(?int $bookingId = null): void
    {
        $this->bookingId = $bookingId;
    }

    public function selectPreset(int $amountCents, string $label, int $percent): void
    {
        $this->selectedAmountCents = $amountCents;
        $this->selectedPresetLabel = $label;
        $this->selectedPresetPercent = $percent;
        $this->customAmount = '';
    }

    public function useCustom(): void
    {
        $cents = (int) round(((float) str_replace(',', '.', $this->customAmount)) * 100);
        if ($cents > 0) {
            $this->selectedAmountCents = $cents;
            $this->selectedPresetLabel = 'custom';
            $this->selectedPresetPercent = null;
        }
    }

    public function submit(): void
    {
        $user = Auth::user();
        $booking = Booking::findOrFail($this->bookingId);

        if (! $this->selectedAmountCents) {
            $this->dispatch('toast', 'Sélectionnez un montant.', 'error');
            return;
        }

        try {
            $tip = app(TipService::class)->create(
                client: $user,
                booking: $booking,
                amountCents: (int) $this->selectedAmountCents,
                presetLabel: $this->selectedPresetLabel,
                presetPercent: $this->selectedPresetPercent,
                message: $this->message ?: null,
            );

            // En prod : créer Stripe PaymentIntent.
            //   - PaymentIntent::create avec metadata.tip_id pour wire dans webhook
            //   - Webhook payment_intent.succeeded → confirmCharge (cf. StripeWebhookEventProcessor)
            // En dev (non-prod) : confirmCharge immédiate pour faciliter test UX.
            if (config('app.env') === 'production' && class_exists(\Stripe\PaymentIntent::class)
                && config('services.stripe.secret')) {
                try {
                    \Stripe\Stripe::setApiKey((string) config('services.stripe.secret'));
                    $intent = \Stripe\PaymentIntent::create([
                        'amount' => (int) $tip->amount_cents,
                        'currency' => strtolower($tip->currency ?: 'eur'),
                        'customer' => $user->stripe_id ?? null,
                        'metadata' => [
                            'tip_id' => $tip->id,
                            'booking_id' => $tip->booking_id,
                            'cleanux_kind' => 'tip',
                        ],
                        'description' => "CleanUx tip booking #{$tip->booking_id}",
                    ], [
                        'idempotency_key' => 'tip_' . $tip->id,
                    ]);
                    $tip->update(['stripe_payment_intent_id' => $intent->id]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('[tips] stripe PI create failed', [
                        'tip_id' => $tip->id, 'error' => $e->getMessage(),
                    ]);
                }
            } else {
                app(TipService::class)->confirmCharge($tip, 'pi_dev_' . $tip->id);
            }

            $this->dispatch('toast', 'Merci pour votre pourboire !', 'success');
            $this->reset(['selectedAmountCents', 'selectedPresetLabel', 'selectedPresetPercent', 'message', 'customAmount']);
            $this->redirect(route('dashboard.client'));
        } catch (ValidationException $e) {
            $first = collect($e->errors())->flatten()->first();
            $this->dispatch('toast', $first ?? 'Échec.', 'error');
        }
    }

    public function render(): View
    {
        $user = Auth::user();

        if (! $this->bookingId) {
            return view('livewire.client.client-tip-booking', [
                'booking' => null,
                'suggestions' => [],
                'existingTip' => null,
            ])->layout('layouts.app');
        }

        $booking = Booking::query()
            ->where('id', $this->bookingId)
            ->where('client_id', $user->id)
            ->first();

        $suggestions = $booking ? app(TipService::class)->suggestionsForBooking($booking) : [];

        $existingTip = $booking
            ? BookingTip::query()
                ->where('booking_id', $booking->id)
                ->where('client_user_id', $user->id)
                ->whereNotIn('status', [BookingTip::STATUS_CANCELLED, BookingTip::STATUS_FAILED])
                ->first()
            : null;

        return view('livewire.client.client-tip-booking', [
            'booking' => $booking,
            'suggestions' => $suggestions,
            'existingTip' => $existingTip,
        ])->layout('layouts.app');
    }
}
