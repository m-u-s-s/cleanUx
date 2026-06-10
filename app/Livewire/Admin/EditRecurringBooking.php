<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use App\Support\Livewire\Concerns\InteractsWithRecurringSeries;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class EditRecurringBooking extends Component
{
    use EnforcesAdminAccess;
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
