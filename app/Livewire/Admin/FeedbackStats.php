<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\Feedback;

class FeedbackStats extends Component
{
    public $moyenne = 0;
    public $total = 0;
    public ?int $scopeId = null;

    public function mount(?int $scopeId = null)
    {
        $this->scopeId = $scopeId;

        $query = Feedback::query()
            ->when($this->scopeId, fn ($q) => $q->whereHas('rendezVous', fn ($r) => $r->where('service_zone_id', $this->scopeId)));

        $this->moyenne = round((float) ($query->avg('note') ?? 0), 2);
        $this->total = (clone $query)->count();
    }

    public function render()
    {
        return view('livewire.admin.feedback-stats');
    }
}
