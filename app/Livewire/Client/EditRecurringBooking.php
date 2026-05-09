<?php

namespace App\Livewire\Client;

use App\Support\Livewire\Concerns\InteractsWithRecurringSeries;
use App\Models\Booking;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;

class EditRecurringBooking extends Component
{
    use InteractsWithRecurringSeries;

    public function mount(Booking $rendezVous): void
    {
        $this->contextLabel = 'client';
        $this->mountRecurringSeries($rendezVous);
    }

    public function render(): View
    {
        return view('livewire.recurring.edit-recurring-booking', [
            'backRoute' => route('client.rendezvous.index'),
            'title' => 'Gérer ma série récurrente',
        ]);
    }
}
