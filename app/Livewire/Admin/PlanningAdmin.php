<?php

namespace App\Livewire\Admin;

use App\Models\RendezVous;
use App\Models\User;
use Livewire\Component;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;

class PlanningAdmin extends Component
{
    public $filtreEmploye = '';
    public $filtreDate = '';
    public $filtreStatus = '';

    public function getStatsProperty()
    {
        $query = RendezVous::query()
            ->when($this->filtreEmploye, fn ($q) => $q->where('employe_id', $this->filtreEmploye))
            ->when($this->filtreDate, fn ($q) => $q->whereDate('date', $this->filtreDate))
            ->when($this->filtreStatus, fn ($q) => $q->where('status', $this->filtreStatus));

        return [
            'total' => (clone $query)->count(),
            'confirme' => (clone $query)->where('status', 'confirme')->count(),
            'attente' => (clone $query)->where('status', 'en_attente')->count(),
            'termine' => (clone $query)->where('status', 'termine')->count(),
        ];
    }

    public function getEmployesProperty()
    {
        return User::where('role', 'employe')->orderBy('name')->get();
    }

    public function render(): View
    {
        return view('livewire.admin.planning-admin', [
            'stats' => $this->stats,
            'employes' => $this->employes,
        ]);
    }
}