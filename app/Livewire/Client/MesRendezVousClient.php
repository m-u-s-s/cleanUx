<?php

namespace App\Livewire\Client;

use App\Models\ActivityLog;
use App\Models\RendezVous;
use App\Support\ActivityLogger;
use App\Support\Domain\BookingStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;

class MesRendezVousClient extends Component
{
    use WithPagination;

    public string $tri = 'asc';
    public string $filtreStatus = '';
    public string $search = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    public $editRdvId = null;
    public $editDate = null;
    public $editHeure = null;

    public ?int $cancelRdvId = null;
    public string $cancelReason = '';

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
        $rdv = RendezVous::findOrFail($id);

        Gate::authorize('update', $rdv);

        if (! $rdv->canStillBeEditedByClient()) {
            $this->dispatch('toast', 'Ce rendez-vous ne peut plus être modifié.', 'error');
            return;
        }

        $this->editRdvId = $rdv->id;
        $this->editDate = $rdv->date?->format('Y-m-d') ?? $rdv->date;
        $this->editHeure = substr((string) $rdv->heure, 0, 5);
    }

    public function fermerEdition(): void
    {
        $this->editRdvId = null;
        $this->editDate = null;
        $this->editHeure = null;
    }

    public function enregistrerModif(): void
    {
        $rdv = RendezVous::where('id', $this->editRdvId)
            ->where('client_id', Auth::id())
            ->firstOrFail();

        Gate::authorize('update', $rdv);

        if (! $rdv->canStillBeEditedByClient()) {
            $this->dispatch('toast', 'Ce rendez-vous ne peut plus être modifié.', 'error');
            return;
        }

        $original = [
            'date' => $rdv->date,
            'heure' => $rdv->heure,
            'status' => $rdv->status,
            'priorite' => $rdv->priorite,
        ];

        $rdv->date = $this->editDate;
        $rdv->heure = $this->editHeure;
        $rdv->status = BookingStatus::EN_ATTENTE;
        $rdv->resetNotificationTrackingIfNeeded($original);
        $rdv->save();

        ActivityLogger::log('rdv_reprogramme_par_client', $rdv, [
            'ancienne_date' => $original['date']?->format('Y-m-d') ?? (string) $original['date'],
            'ancienne_heure' => $original['heure'],
            'nouvelle_date' => $rdv->date?->format('Y-m-d') ?? (string) $rdv->date,
            'nouvelle_heure' => $rdv->heure,
        ]);

        $this->fermerEdition();
        $this->dispatch('toast', 'Rendez-vous mis à jour.', 'success');
    }

    public function demanderAnnulation(int $id): void
    {
        $rdv = RendezVous::findOrFail($id);
        Gate::authorize('delete', $rdv);

        if (! $rdv->canStillBeEditedByClient()) {
            $this->dispatch('toast', 'Ce rendez-vous ne peut plus être annulé.', 'error');
            return;
        }

        $this->cancelRdvId = $id;
        $this->cancelReason = '';
    }

    public function fermerAnnulation(): void
    {
        $this->cancelRdvId = null;
        $this->cancelReason = '';
    }

    public function confirmerAnnulation(): void
    {
        $rdv = RendezVous::findOrFail($this->cancelRdvId);

        Gate::authorize('delete', $rdv);

        if (! $rdv->canStillBeEditedByClient()) {
            $this->dispatch('toast', 'Ce rendez-vous ne peut plus être annulé.', 'error');
            return;
        }

        ActivityLogger::log('rdv_annule_par_client', $rdv, [
            'date' => $rdv->date?->format('Y-m-d') ?? (string) $rdv->date,
            'heure' => $rdv->heure,
            'service' => $rdv->service_display_name,
            'service_identifier' => $rdv->service_identifier_display,
            'reason' => $this->cancelReason,
        ]);

        $rdv->delete();
        $this->fermerAnnulation();
        $this->dispatch('toast', 'Rendez-vous annulé.', 'success');
    }

    public function annuler(int $id): void
    {
        $this->demanderAnnulation($id);
    }

    public function historyFor(int $rdvId)
    {
        return ActivityLog::query()
            ->where('target_type', RendezVous::class)
            ->where('target_id', $rdvId)
            ->latest()
            ->limit(5)
            ->get();
    }

    public function render(): View
    {
        $query = RendezVous::with(['employe', 'feedback', 'serviceCatalog', 'serviceZone', 'organizationSite', 'postalCode', 'mission', 'mission.leadEmployee', 'mission.verificationCodes', 'mission.activeTrackingSession'])
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
