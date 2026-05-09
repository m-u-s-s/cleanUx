<?php

namespace App\Livewire\Admin;

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
        $this->contextLabel = 'admin';
        $this->mountRecurringSeries($rendezVous);
    }

    public function render(): View
    {
        return view('livewire.recurring.edit-recurring-booking', [
            'backRoute' => route('admin.dashboard'),
            'title' => 'Gérer une série récurrente',
        ]);
    }
}
