<?php

namespace App\Livewire\Admin;

use Livewire\Component;
use App\Models\RendezVous;
use App\Models\User;
use Carbon\Carbon;

class AgendaHebdomadaire extends Component
{
    public $semaine;
    public $employe_id = '';
    public $priorite = '';

    public function mount()
    {
        $this->semaine = now()->startOfWeek()->format('Y-m-d');
    }

    public function semainePrecedente()
    {
        $this->semaine = Carbon::parse($this->semaine)
            ->subWeek()
            ->startOfWeek()
            ->format('Y-m-d');
    }

    public function semaineSuivante()
    {
        $this->semaine = Carbon::parse($this->semaine)
            ->addWeek()
            ->startOfWeek()
            ->format('Y-m-d');
    }

    public function render()
    {
        $start = Carbon::parse($this->semaine)->startOfWeek();
        $end = $start->copy()->endOfWeek();

        $rdvs = RendezVous::with('employe', 'client')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->when($this->employe_id, fn($q) => $q->where('employe_id', $this->employe_id))
            ->when($this->priorite, fn($q) => $q->where('priorite', $this->priorite))
            ->orderBy('date')
            ->orderBy('heure')
            ->get();

        $rdvsGrouped = $rdvs->groupBy('date');

        $jours = collect();

        foreach (range(0, 6) as $i) {
            $jour = $start->copy()->addDays($i);
            $rdvsJour = $rdvsGrouped[$jour->toDateString()] ?? collect();

            $totalMinutes = $rdvsJour->sum(function ($rdv) {
                return ($rdv->duree ?? $rdv->duree_estimee ?? 90) + 30;
            });

            $jours->push([
                'label' => $jour->translatedFormat('l d/m'),
                'date' => $jour->toDateString(),
                'rdvs' => $rdvsJour,
                'total_minutes' => $totalMinutes,
                'total_hours' => round($totalMinutes / 60, 1),
            ]);
        }

        $employes = User::where('role', 'employe')->get();

        return view('livewire.admin.agenda-hebdomadaire', compact('jours', 'employes'));
    }
}