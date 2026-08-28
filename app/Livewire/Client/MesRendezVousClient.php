<?php

namespace App\Livewire\Client;

use App\Livewire\Concerns\AnnuleUnRendezVousClient;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Services\Booking\EmployeeAvailabilityService;
use App\Services\Missions\MissionFromRendezVousSyncService;
use App\Support\ActivityLogger;
use App\Support\Domain\BookingStatus;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

class MesRendezVousClient extends Component
{
    use AnnuleUnRendezVousClient;
    use WithPagination;

    public string $tri = 'asc';

    public string $filtreStatus = '';

    public string $search = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public $editRdvId = null;

    public $editDate = null;

    public $editHeure = null;

    public array $creneauxDisponibles = [];

    public ?string $impactDevisMessage = null;

    public ?string $employeReplanificationMessage = null;

    protected $queryString = [
        'filtreStatus' => ['except' => ''],
        'search' => ['except' => ''],
        'tri' => ['except' => 'asc'],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFiltreStatus(): void
    {
        $this->resetPage();
    }

    public function updatingTri(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function modifier($id): void
    {
        $rdv = Booking::with(['serviceZone', 'employe'])->findOrFail($id);

        Gate::authorize('update', $rdv);

        if (! $rdv->canStillBeEditedByClient()) {
            $this->dispatch('toast', message: 'Ce rendez-vous ne peut plus être modifié.', type: 'error');

            return;
        }

        $this->editRdvId = $rdv->id;
        $this->editDate = $rdv->date?->format('Y-m-d') ?? $rdv->date;
        $this->editHeure = substr((string) $rdv->heure, 0, 5);

        $this->impactDevisMessage = 'Le devis reste inchangé pour ce changement de créneau.';
        $this->employeReplanificationMessage = null;

        $this->chargerCreneauxDisponibles();
    }

    public function chargerCreneauxDisponibles(): void
    {
        if (! $this->editRdvId || ! $this->editDate) {
            $this->creneauxDisponibles = [];

            return;
        }

        $rdv = Booking::with(['serviceZone'])->findOrFail($this->editRdvId);

        $availability = app(EmployeeAvailabilityService::class);

        $slots = [];

        foreach (['08:00', '09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'] as $heure) {
            $currentEmployeeAvailable = $rdv->employe_id
                ? $availability->employeeIsAvailableForSlot(
                    $rdv->employe_id,
                    $this->editDate,
                    $heure,
                    $rdv->serviceZone,
                    (int) ($rdv->estimated_duration_minutes ?: $rdv->duree ?: 90),
                    $rdv->id
                )
                : false;

            $bestEmployee = $currentEmployeeAvailable
                ? $rdv->employe
                : $availability->resolveBestAvailableEmployeeForSlot(
                    $this->editDate,
                    $heure,
                    $rdv->serviceZone,
                    (int) ($rdv->estimated_duration_minutes ?: $rdv->duree ?: 90),
                    $rdv->id
                );

            if ($bestEmployee) {
                $slots[] = [
                    'heure' => $heure,
                    'employe_id' => $bestEmployee->id,
                    'employe_name' => $bestEmployee->name,
                    'same_employee' => $rdv->employe_id === $bestEmployee->id,
                ];
            }
        }

        $this->creneauxDisponibles = $slots;
    }

    public function updatedEditDate(): void
    {
        $this->chargerCreneauxDisponibles();
    }

    public function fermerEdition(): void
    {
        $this->editRdvId = null;
        $this->editDate = null;
        $this->editHeure = null;
    }

    public function enregistrerModif(): void
    {
        $rdv = Booking::with(['serviceZone', 'employe', 'mission'])
            ->where('id', $this->editRdvId)
            ->where('client_id', Auth::id())
            ->firstOrFail();

        Gate::authorize('update', $rdv);

        if (! $rdv->canStillBeEditedByClient()) {
            $this->dispatch('toast', message: 'Ce rendez-vous ne peut plus être modifié.', type: 'error');

            return;
        }

        $this->validate([
            'editDate' => ['required', 'date', 'after_or_equal:today'],
            'editHeure' => ['required', 'date_format:H:i'],
        ]);

        $availability = app(EmployeeAvailabilityService::class);

        $employee = null;

        if ($rdv->employe_id && $availability->employeeIsAvailableForSlot(
            $rdv->employe_id,
            $this->editDate,
            $this->editHeure,
            $rdv->serviceZone,
            (int) ($rdv->estimated_duration_minutes ?: $rdv->duree ?: 90),
            $rdv->id
        )) {
            $employee = $rdv->employe;
        } else {
            $employee = $availability->resolveBestAvailableEmployeeForSlot(
                $this->editDate,
                $this->editHeure,
                $rdv->serviceZone,
                (int) ($rdv->estimated_duration_minutes ?: $rdv->duree ?: 90),
                $rdv->id
            );
        }

        if (! $employee) {
            $this->dispatch('toast', message: 'Aucun employé disponible pour ce créneau.', type: 'error');

            return;
        }

        $original = [
            'date' => $rdv->date,
            'heure' => $rdv->heure,
            'status' => $rdv->status,
            'priorite' => $rdv->priorite,
            'employe_id' => $rdv->employe_id,
            'devis_estime' => $rdv->devis_estime,
        ];

        $rdv->date = $this->editDate;
        $rdv->heure = $this->editHeure;
        $rdv->employe_id = $employee->id;
        $rdv->status = BookingStatus::EN_ATTENTE;

        $rdv->resetNotificationTrackingIfNeeded($original);
        $rdv->save();

        if ($rdv->mission) {
            app(MissionFromRendezVousSyncService::class)->syncFromRendezVous($rdv->fresh());
        }

        ActivityLogger::log('rdv_reprogramme_par_client', $rdv, [
            'ancienne_date' => $original['date']?->format('Y-m-d') ?? (string) $original['date'],
            'ancienne_heure' => $original['heure'],
            'nouvelle_date' => $rdv->date?->format('Y-m-d') ?? (string) $rdv->date,
            'nouvelle_heure' => $rdv->heure,
            'ancien_employe_id' => $original['employe_id'],
            'nouvel_employe_id' => $employee->id,
            'ancien_devis' => $original['devis_estime'],
            'nouveau_devis' => $rdv->devis_estime,
        ]);

        $this->fermerEdition();

        $this->dispatch('toast', message: 'Rendez-vous replanifié avec succès.', type: 'success');
    }

    public function annuler(int $id): void
    {
        $this->demanderAnnulation($id);
    }

    public function historyFor(int $rdvId)
    {
        return ActivityLog::query()
            ->where('target_type', Booking::class)
            ->where('target_id', $rdvId)
            ->latest()
            ->limit(5)
            ->get();
    }

    public function render(): View
    {
        $query = Booking::with(['employe', 'feedback', 'serviceCatalog', 'serviceZone', 'organizationSite', 'postalCode', 'mission', 'mission.leadEmployee', 'mission.verificationCodes', 'mission.activeTrackingSession'])
            ->where('client_id', Auth::id())
            ->when($this->filtreStatus, fn ($q) => $q->where('status', $this->filtreStatus))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('date', '<=', $this->dateTo))
            ->when($this->search, fn ($q) => $q->searchStructured($this->search));

        return view('livewire.client.mes-rendez-vous-client', [
            'rendezVous' => $query
                ->orderBy('date', $this->tri)
                ->orderBy('heure', $this->tri)
                ->paginate(8),
        ]);
    }
}
