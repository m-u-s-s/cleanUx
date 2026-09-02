<?php

namespace App\Livewire\Admin;

use App\Models\Booking;
use App\Models\User;
use App\Services\Booking\SmartDispatchService;
use App\Services\Dispatch\DispatchEngine;
use App\Services\FaceCheck\FaceCheckGate;
use App\Support\ActivityLogger;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class MissionsAdmin extends Component
{
    use EnforcesAdminAccess;
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
        return User::query()->providers()->orderBy('name')->get();
    }

    public function dispatchRendezVous(int $rdvId): void
    {
        $rdv = Booking::with([
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

        $this->affecter($rdv, $employee, 'rdv_auto_dispatched');
    }

    /**
     * CHOISIR UN PRESTATAIRE DEPUIS LE SCORING. L'administrateur voit les scores ; il peut
     * désormais désigner quelqu’un d’autre que le premier — le moteur propose, il décide.
     */
    public function choisirPrestataire(int $rdvId, int $employeeId): void
    {
        $rdv = Booking::with([
            'client',
            'serviceZone',
            'employe',
            'mission',
        ])->findOrFail($rdvId);

        // LE CHOIX NE S’AFFRANCHIT PAS DU CLASSEMENT : on ne retient que les candidats que
        // le scoring a réellement proposés pour CE rendez-vous. Un identifiant envoyé à la
        // main depuis le navigateur ne désigne donc personne.
        $propose = collect(app(SmartDispatchService::class)->explainScores($rdv))
            ->contains(fn (array $ligne) => (int) ($ligne['employee_id'] ?? 0) === $employeeId);

        if (! $propose) {
            $this->dispatch('toast', 'Ce prestataire ne fait pas partie des candidats proposés.', 'error');

            return;
        }

        $employee = User::find($employeeId);

        if (! $employee) {
            $this->dispatch('toast', 'Prestataire introuvable.', 'error');

            return;
        }

        if ($this->affecter($rdv, $employee, 'rdv_dispatch_choisi')) {
            $this->closeDispatchPreview();
        }
    }

    /**
     * Les gardes, l’écriture et la trace — communes aux deux chemins.
     *
     * `DispatchEngine::createOffer()` refuse individuellement un prestataire sur son KYC et
     * sur le verdict du contrôle facial. Ce chemin écrivait sans repasser ni l’un ni l’autre.
     */
    private function affecter(Booking $rdv, User $employee, string $evenement): bool
    {
        if (! $employee->hasClearedKyc()) {
            $this->dispatch('toast', "Affectation refusée : l'identité de {$employee->name} n'est pas vérifiée.", 'error');

            return false;
        }

        if (! app(FaceCheckGate::class)->inspectForBooking($employee, $rdv)->allowed()) {
            $this->dispatch('toast', "Affectation refusée : {$employee->name} doit passer son contrôle facial.", 'error');

            return false;
        }

        $oldEmployeeId = $rdv->employe_id;

        // LA CHAINE ECRIT, PAS CET ECRAN. Il posait `employe_id` et `CONFIRME` en direct :
        // la mission n’avait alors AUCUNE ligne d’assignation, et son historique restait vide
        // — un rendez-vous affecté depuis ici était invisible de la chaîne d’offres.
        $moteur = app(DispatchEngine::class);
        $mission = $moteur->ensureMission($rdv);

        if (! $mission) {
            $this->dispatch('toast', 'Mission introuvable pour ce rendez-vous.', 'error');

            return false;
        }

        if (! $moteur->imposerCePrestataire($mission, $employee)) {
            $this->dispatch('toast', "Affectation refusée : {$employee->name} ne remplit pas les conditions.", 'error');

            return false;
        }

        ActivityLogger::log($evenement, $rdv, [
            'old_employee_id' => $oldEmployeeId,
            'new_employee_id' => $employee->id,
            'new_employee_name' => $employee->name,
        ]);

        $this->dispatch('toast', 'Rendez-vous assigné à '.$employee->name.'.', 'success');

        return true;
    }

    public function previewDispatch(int $rdvId): void
    {
        $rdv = Booking::with([
            'client',
            'serviceZone',
            'employe',
        ])->findOrFail($rdvId);

        $this->dispatchPreviewRdvId = $rdv->id;

        $this->dispatchPreview = app(SmartDispatchService::class)
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
        $query = Booking::with(['client', 'employe', 'serviceCatalog', 'postalCode'])
            ->when($this->search, fn ($q) => $q->searchStructured($this->search))
            ->when($this->filtreEmploye, fn ($q) => $q->intervenantEst((int) $this->filtreEmploye))
            ->when($this->filtreStatus, fn ($q) => $q->where('status', $this->filtreStatus))
            ->when($this->filtrePriorite, fn ($q) => $q->where('priorite', $this->filtrePriorite));

        return view('livewire.admin.missions-admin', [
            'missions' => $query->orderBy('date', $this->tri)->orderBy('heure', $this->tri)->paginate(10),
            'employes' => $this->employes,
        ]);
    }
}
