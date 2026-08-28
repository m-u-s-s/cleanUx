<?php

namespace App\Livewire\Client;

use App\Models\Booking;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * La page d'un rendez-vous : son detail, et le suivi de mission qui encombrait chaque ligne
 * de la liste. Le cockpit `client.mission-tracking` s'y monte des qu'une mission existe.
 */
class RendezVousDetail extends Component
{
    #[Locked]
    public int $bookingId;

    public function mount(Booking $booking): void
    {
        // Une page Livewire est une porte HTTP a part entiere : la garde vit ici.
        abort_unless((int) $booking->client_id === (int) Auth::id(), 403);

        $this->bookingId = $booking->id;
    }

    public function render(): View
    {
        return view('livewire.client.rendez-vous-detail', [
            'reservation' => Booking::query()->with(['employe', 'mission'])->findOrFail($this->bookingId),
        ]);
    }
}
