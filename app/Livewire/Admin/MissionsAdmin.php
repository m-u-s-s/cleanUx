<?php

namespace App\Livewire\Admin;

use App\Models\RendezVous;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use App\Services\Booking\SmartDispatchService;
use App\Services\Missions\MissionFromRendezVousSyncService;

class MissionsAdmin extends Component
{
    use WithPagination;

    public $search = '';
    public $filtreEmploye = '';
    public $filtreStatus = '';
    public $filtrePriorite = '';
    public $tri = 'desc';
    public ?int $dispatchPreviewRdvId = null;
    public array $dispatchPreview = [];

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

    public function dispatchRendezVous(int $rdvId): void
    {
        $rdv = \App\Models\RendezVous::with([
            'client',
            'serviceZone',
            'employe',
            'mission',
        ])->findOrFail($rdvId);

        $employee = app(SmartDispatchService::class)->assignBestEmployee($rdv);

        if (! $employee) {
            $this->dispatch('toast', 'Aucun employé disponible pour ce rendez-vous.', 'error');
            return;
        }

        $oldEmployeeId = $rdv->employe_id;

        $rdv->update([
            'employe_id' => $employee->id,
            'status' => \App\Support\Domain\BookingStatus::CONFIRME,
        ]);

        app(MissionFromRendezVousSyncService::class)->syncFromRendezVous($rdv->fresh());

        \App\Support\ActivityLogger::log('rdv_auto_dispatched', $rdv, [
            'old_employee_id' => $oldEmployeeId,
            'new_employee_id' => $employee->id,
            'new_employee_name' => $employee->name,
        ]);

        $this->dispatch('toast', 'Rendez-vous assigné à ' . $employee->name . '.', 'success');
    }



    public function previewDispatch(int $rdvId): void
    {
        $rdv = \App\Models\RendezVous::with([
            'client',
            'serviceZone',
            'employe',
        ])->findOrFail($rdvId);

        $this->dispatchPreviewRdvId = $rdv->id;

        $this->dispatchPreview = app(\App\Services\Booking\SmartDispatchService::class)
            ->explainScores($rdv)
            ->toArray();
    }

    public function closeDispatchPreview(): void
    {
        $this->dispatchPreviewRdvId = null;
        $this->dispatchPreview = [];
    }

    public function render(): View
    {
        $query = RendezVous::with(['client', 'employe', 'serviceCatalog', 'postalCode'])
            ->when($this->search, fn($q) => $q->searchStructured($this->search))
            ->when($this->filtreEmploye, fn($q) => $q->where('employe_id', $this->filtreEmploye))
            ->when($this->filtreStatus, fn($q) => $q->where('status', $this->filtreStatus))
            ->when($this->filtrePriorite, fn($q) => $q->where('priorite', $this->filtrePriorite));

        return view('livewire.admin.missions-admin', [
            'missions' => $query->orderBy('date', $this->tri)->orderBy('heure', $this->tri)->paginate(10),
            'employes' => $this->employes,
        ]);
    }
}
