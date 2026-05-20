<?php

namespace App\Livewire\Client;

use App\Models\Booking;
use App\Services\TripTracking\TripTrackingService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Page client live tracking — affiche carte Leaflet avec position provider.
 * Polling 10s + appel API /api/client/bookings/{id}/tracking côté JS.
 */
class ClientLiveTrackingMap extends Component
{
    #[Url]
    public ?int $bookingId = null;

    public function mount(?int $bookingId = null): void
    {
        $this->bookingId = $bookingId;
    }

    public function render(): View
    {
        $user = Auth::user();
        $booking = $this->bookingId
            ? Booking::query()->where('id', $this->bookingId)->where('client_id', $user->id)->first()
            : null;

        $session = null;
        if ($booking) {
            $session = app(TripTrackingService::class)->activeSessionForBooking((int) $booking->id);
        }

        return view('livewire.client.client-live-tracking-map', [
            'booking' => $booking,
            'session' => $session,
        ])->layout('layouts.app');
    }
}
