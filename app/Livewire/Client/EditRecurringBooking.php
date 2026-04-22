<?php

namespace App\Livewire\Client;

use App\Support\Livewire\Concerns\InteractsWithRecurringSeries;
use App\Models\RendezVous;
use Livewire\Component;

class EditRecurringBooking extends Component
{
    use InteractsWithRecurringSeries;

    public function mount(RendezVous $rendezVous): void
    {
        $this->contextLabel = 'client';
        $this->mountRecurringSeries($rendezVous);
    }

    public function render()
    {
        return view('livewire.recurring.edit-recurring-booking', [
            'backRoute' => route('client.rendezvous.index'),
            'title' => 'Gérer ma série récurrente',
        ])->layout('layouts.app');
    }
}
