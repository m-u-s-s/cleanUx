<?php

namespace App\Livewire\Employe;

use App\Models\RendezVous;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class HistoriqueEmploye extends Component
{
    use WithPagination;

    public $search = '';
    public $tri = 'desc';

    protected $queryString = [
        'search' => ['except' => ''],
        'tri' => ['except' => 'desc'],
        'page' => ['except' => 1],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingTri()
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = RendezVous::with(['client', 'feedback', 'serviceCatalog', 'postalCode'])
            ->where('employe_id', Auth::id())
            ->where('status', 'termine')
            ->when($this->search, fn ($q) => $q->searchStructured($this->search));

        return view('livewire.employe.historique-employe', [
            'historique' => $query
                ->orderBy('date', $this->tri)
                ->orderBy('heure', $this->tri)
                ->paginate(8),
        ])->layout('layouts.app');
    }
}