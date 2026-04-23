<?php

namespace App\Livewire\Admin;

use App\Models\RendezVous;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;

class MissionsAdmin extends Component
{
    use WithPagination;

    public $search = '';
    public $filtreEmploye = '';
    public $filtreStatus = '';
    public $filtrePriorite = '';
    public $tri = 'desc';

    protected $queryString = ['search', 'filtreEmploye', 'filtreStatus', 'filtrePriorite', 'page'];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFiltreEmploye()
    {
        $this->resetPage();
    }

    public function updatingFiltreStatus()
    {
        $this->resetPage();
    }

    public function updatingFiltrePriorite()
    {
        $this->resetPage();
    }

    public function getEmployesProperty()
    {
        return User::where('role', 'employe')->orderBy('name')->get();
    }

    public function render(): View
    {
        $query = RendezVous::with(['client', 'employe', 'serviceCatalog', 'postalCode'])
            ->when($this->search, fn ($q) => $q->searchStructured($this->search))
            ->when($this->filtreEmploye, fn ($q) => $q->where('employe_id', $this->filtreEmploye))
            ->when($this->filtreStatus, fn ($q) => $q->where('status', $this->filtreStatus))
            ->when($this->filtrePriorite, fn ($q) => $q->where('priorite', $this->filtrePriorite));

        return view('livewire.admin.missions-admin', [
            'missions' => $query->orderBy('date', $this->tri)->orderBy('heure', $this->tri)->paginate(10),
            'employes' => $this->employes,
        ]);
    }
}