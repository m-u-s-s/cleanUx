<?php

namespace App\Support\Livewire\Concerns\Booking;

use Illuminate\Support\Facades\Auth;

trait HandlesPublicBookingAuthentication
{
        protected function publicBookingEntryRouteName(): string
        {
            return Auth::check() && Auth::user()?->isClient()
                ? 'client.rendezvous.create'
                : 'booking.create';
        }

        protected function queueBookingAuthenticationRedirect(string $target = 'register')
        {
            $this->normalizeBookingState();
            $this->normalizeRecurringInputs();
            $this->persistPublicBookingDraft();

            session(['url.intended' => route('booking.create', ['resume' => 1])]);
            session()->flash('booking_auth_required', 'Créez un compte ou connectez-vous pour confirmer votre demande.');

            return $this->redirectRoute($target === 'login' ? 'login' : 'register', navigate: true);
        }

        public function redirectToAuthentication(string $target = 'register')
        {
            return $this->queueBookingAuthenticationRedirect($target);
        }
}
