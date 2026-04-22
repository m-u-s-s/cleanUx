<?php

namespace App\Livewire\Employe;

use App\Models\Feedback;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class FeedbackStats extends Component
{
    public $moyenne = 0;
    public $total = 0;

    public function mount(): void
    {
        $average = Feedback::whereHas(
            'rendezVous',
            fn ($q) => $q->where('employe_id', Auth::id())
        )->avg('note');

        $this->moyenne = $average !== null ? round((float) $average, 2) : 0;

        $this->total = Feedback::whereHas(
            'rendezVous',
            fn ($q) => $q->where('employe_id', Auth::id())
        )->count();
    }

    public function render()
    {
        return view('livewire.employe.feedback-stats');
    }
}
