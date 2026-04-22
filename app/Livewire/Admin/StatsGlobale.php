<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\RendezVous;
use App\Models\User;
use App\Models\Feedback;

class StatsGlobale extends Component
{
    public $year;
    public $employe_id = '';

    public function mount()
    {
        $this->year = now()->year;
    }

    public function render()
    {
        $rdvs = RendezVous::whereYear('date', $this->year)
            ->when($this->employe_id, fn($q) =>
            $q->where('employe_id', $this->employe_id))
            ->get();

        $feedbacks = Feedback::whereYear('created_at', $this->year)
            ->when(
                $this->employe_id,
                fn($q) =>
                $q->whereHas(
                    'rendezVous',
                    fn($r) =>
                    $r->where('employe_id', $this->employe_id)
                )
            )
            ->get();

        $dataMonthly = collect(range(1, 12))->map(function ($month) {
            return RendezVous::whereYear('date', $this->year)
                ->whereMonth('date', $month)
                ->when($this->employe_id, fn($q) =>
                $q->where('employe_id', $this->employe_id))
                ->count();
        });

        $noteAverage = round($feedbacks->avg('note'), 2);
        $feedbackCount = $feedbacks->count();

        $employes = User::where('role', 'employe')->get();

        return view('livewire.admin.stats-globale', [
            'dataMonthly' => $dataMonthly,
            'noteAverage' => $noteAverage,
            'feedbackCount' => $feedbackCount,
            'employes' => $employes
        ]);
    }
}
