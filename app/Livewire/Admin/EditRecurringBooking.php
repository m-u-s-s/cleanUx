<?php

namespace App\Livewire\Admin;

use App\Support\Livewire\Concerns\InteractsWithRecurringSeries;
use App\Models\RendezVous;
use Livewire\Component;

class EditRecurringBooking extends Component
{
    use InteractsWithRecurringSeries;

    public function mount(RendezVous $rendezVous): void
    {
        $this->contextLabel = 'admin';
        $this->mountRecurringSeries($rendezVous);
    }

    public function render()
    {
        return view('livewire.recurring.edit-recurring-booking', [
            'backRoute' => route('admin.dashboard'),
            'title' => 'Gérer une série récurrente',
        ])->layout('layouts.app');
    }
}
