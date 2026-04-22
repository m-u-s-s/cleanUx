<?php

namespace App\Livewire\Employe;

use App\Models\RendezVous;
use App\Notifications\StatutRendezVousNotification;
use App\Support\ActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class MesRendezVous extends Component
{
    use WithPagination;
    use WithFileUploads;

    public $filtreStatus = null;
    public $priorite = null;
    public $tri = 'asc';
    public $search = '';
    public $selectedRdvId = null;

    public $showCheckInModal = false;
    public $checkInRdvId = null;
    public $photos_avant = [];
    public $terrain_checklist = [];
    public $remarque_terrain = '';

    public $showRapportModal = false;
    public $rapportRdvId = null;
    public $commentaire_fin_mission = '';
    public $duree_reelle = null;
    public $photos_apres = [];
    public $incident_terrain = '';
    public $client_presence_confirmee = false;
    public $client_signature_data = null;

    protected $listeners = [
        'refuser-rdv' => 'refuserRdv',
    ];

    protected function defaultChecklist(): array
    {
        return [
            'acces_ok' => false,
            'materiel_ok' => false,
            'zone_securisee' => false,
            'etat_initial_capture' => false,
            'client_present' => false,
        ];
    }

    protected function rendezVousQuery()
    {
        $query = RendezVous::with([
            'client',
            'serviceCatalog',
            'postalCode',
            'mission',
            'mission.assignments',
            'mission.activeTrackingSession',
        ])
            ->where('employe_id', Auth::id())
            ->when($this->search, fn ($q) => $q->searchStructured($this->search));

        if ($this->filtreStatus) {
            $query->where('status', $this->filtreStatus);
        }

        if ($this->priorite) {
            $query->where('priorite', $this->priorite);
        }

        return $query;
    }

    protected function paginatedRendezVous(): LengthAwarePaginator
    {
        return $this->rendezVousQuery()
            ->orderBy('date', $this->tri)
            ->orderBy('heure', $this->tri)
            ->paginate(5);
    }


    public function selectRdv(int $id): void
    {
        $rdv = RendezVous::query()
            ->where('employe_id', Auth::id())
            ->findOrFail($id);

        $this->selectedRdvId = $rdv->id;
    }

    public function clearSelectedRdv(): void
    {
        $this->selectedRdvId = null;
    }

    public function getSelectedRendezVousProperty(): ?RendezVous
    {
        if (! $this->selectedRdvId) {
            return null;
        }

        return RendezVous::with([
            'client',
            'serviceCatalog',
            'postalCode',
            'mission',
            'mission.assignments',
            'mission.activeTrackingSession',
        ])
            ->where('employe_id', Auth::id())
            ->find($this->selectedRdvId);
    }

    public function getSelectedMissionProperty()
    {
        return $this->selectedRendezVous?->mission;
    }

    public function mettreAJourStatut($id, $status)
    {
        if (!in_array($status, [
            'confirme',
            'refuse',
            'en_attente',
            'en_route',
            'sur_place',
        ])) {
            abort(403);
        }

        $rdv = RendezVous::findOrFail($id);

        Gate::authorize('update', $rdv);

        $original = [
            'date' => $rdv->date,
            'heure' => $rdv->heure,
            'status' => $rdv->status,
            'priorite' => $rdv->priorite,
        ];

        if ($status === 'en_route' && ! $rdv->mission_started_at) {
            $rdv->mission_started_at = now();
        }

        if ($status === 'sur_place' && ! $rdv->mission_arrived_at) {
            $rdv->mission_arrived_at = now();
        }

        $rdv->status = $status;

        $rdv->resetNotificationTrackingIfNeeded($original);
        $rdv->save();

        $rdv->client?->notify(new StatutRendezVousNotification($rdv));

        $message = match ($status) {
            'confirme' => 'Intervention confirmée.',
            'refuse' => 'Intervention refusée.',
            'en_route' => 'Intervention marquée en route.',
            'sur_place' => 'Intervention marquée sur place.',
            default => 'Statut mis à jour.',
        };

        $type = in_array($status, ['refuse']) ? 'error' : 'success';
        ActivityLogger::log('mission_statut_modifie', $rdv, [
            'ancien_statut' => $original['status'],
            'nouveau_statut' => $rdv->status,
            'date' => $rdv->date?->format('Y-m-d') ?? (string) $rdv->date,
            'heure' => $rdv->heure,
            'client' => $rdv->client->name ?? null,
        ]);
        $this->selectedRdvId = $rdv->id;
        $this->dispatch('toast', $message, $type);
    }

    public function ouvrirCheckInMission($id): void
    {
        $rdv = RendezVous::findOrFail($id);

        Gate::authorize('update', $rdv);

        $this->selectedRdvId = $rdv->id;
        $this->checkInRdvId = $rdv->id;
        $this->photos_avant = [];
        $this->terrain_checklist = array_merge($this->defaultChecklist(), $rdv->terrain_checklist ?? []);
        $this->remarque_terrain = $rdv->remarque_terrain ?? '';
        $this->showCheckInModal = true;
    }

    public function fermerCheckInMission(): void
    {
        $this->reset([
            'showCheckInModal',
            'checkInRdvId',
            'photos_avant',
            'remarque_terrain',
        ]);
        $this->terrain_checklist = $this->defaultChecklist();
    }

    public function sauverCheckInMission(): void
    {
        $rdv = RendezVous::findOrFail($this->checkInRdvId);

        Gate::authorize('update', $rdv);

        $this->validate([
            'photos_avant.*' => ['nullable', 'image', 'max:4096'],
            'remarque_terrain' => ['nullable', 'string', 'max:2000'],
            'terrain_checklist' => ['array'],
        ]);

        $storedPhotos = $rdv->photos_avant ?? [];

        foreach ($this->photos_avant as $photo) {
            $storedPhotos[] = $photo->store('rendezvous/photos-avant', 'public');
        }

        $original = [
            'date' => $rdv->date,
            'heure' => $rdv->heure,
            'status' => $rdv->status,
            'priorite' => $rdv->priorite,
        ];

        $rdv->photos_avant = $storedPhotos;
        $rdv->terrain_checklist = array_merge($this->defaultChecklist(), array_map(fn ($value) => (bool) $value, $this->terrain_checklist ?? []));
        $rdv->remarque_terrain = $this->remarque_terrain;
        $rdv->client_presence_confirmed_at = ($rdv->terrain_checklist['client_present'] ?? false) ? now() : $rdv->client_presence_confirmed_at;
        $rdv->mission_started_at = $rdv->mission_started_at ?? now();
        $rdv->mission_arrived_at = now();
        $rdv->status = 'sur_place';

        $rdv->resetNotificationTrackingIfNeeded($original);
        $rdv->save();

        ActivityLogger::log('mission_checkin_effectue', $rdv, [
            'has_photos_avant' => ! empty($rdv->photos_avant),
            'client_present' => (bool) ($rdv->terrain_checklist['client_present'] ?? false),
            'acces_ok' => (bool) ($rdv->terrain_checklist['acces_ok'] ?? false),
            'materiel_ok' => (bool) ($rdv->terrain_checklist['materiel_ok'] ?? false),
        ]);

        $rdv->client?->notify(new StatutRendezVousNotification($rdv));

        $this->selectedRdvId = $rdv->id;
        $this->fermerCheckInMission();
        $this->dispatch('toast', 'Check-in terrain enregistré.', 'success');
    }

    public function ouvrirRapportFinMission($id)
    {
        $rdv = RendezVous::findOrFail($id);

        Gate::authorize('update', $rdv);

        $this->selectedRdvId = $rdv->id;
        $this->rapportRdvId = $rdv->id;
        $this->commentaire_fin_mission = $rdv->commentaire_fin_mission ?? '';
        $this->duree_reelle = $rdv->duree_reelle ?? $rdv->duree_estimee;
        $this->photos_apres = [];
        $this->incident_terrain = $rdv->incident_terrain ?? '';
        $this->client_presence_confirmee = (bool) $rdv->client_presence_confirmed_at;
        $this->client_signature_data = null;
        $this->showRapportModal = true;
    }

    public function fermerRapportFinMission()
    {
        $this->reset([
            'showRapportModal',
            'rapportRdvId',
            'commentaire_fin_mission',
            'duree_reelle',
            'photos_apres',
            'incident_terrain',
            'client_presence_confirmee',
            'client_signature_data',
        ]);
    }

    protected function storeSignatureFromDataUrl(?string $dataUrl): ?string
    {
        if (! filled($dataUrl) || ! Str::startsWith($dataUrl, 'data:image/')) {
            return null;
        }

        [$meta, $content] = explode(',', $dataUrl, 2) + [null, null];

        if (! $content) {
            return null;
        }

        $extension = Str::contains($meta, 'image/jpeg') ? 'jpg' : 'png';
        $binary = base64_decode($content, true);

        if ($binary === false) {
            return null;
        }

        $path = 'rendezvous/signatures/' . Str::uuid() . '.' . $extension;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    public function sauverRapportFinMission()
    {
        $rdv = RendezVous::findOrFail($this->rapportRdvId);

        Gate::authorize('update', $rdv);

        $this->validate([
            'commentaire_fin_mission' => ['nullable', 'string', 'max:2000'],
            'duree_reelle' => ['required', 'integer', 'min:15', 'max:1440'],
            'photos_apres.*' => ['nullable', 'image', 'max:4096'],
            'incident_terrain' => ['nullable', 'string', 'max:2000'],
            'client_signature_data' => ['nullable', 'string'],
        ]);

        $storedPhotos = $rdv->photos_apres ?? [];

        foreach ($this->photos_apres as $photo) {
            $storedPhotos[] = $photo->store('rendezvous/photos-apres', 'public');
        }

        $signaturePath = $this->storeSignatureFromDataUrl($this->client_signature_data);

        $original = [
            'date' => $rdv->date,
            'heure' => $rdv->heure,
            'status' => $rdv->status,
            'priorite' => $rdv->priorite,
        ];

        $rdv->commentaire_fin_mission = $this->commentaire_fin_mission;
        $rdv->duree_reelle = $this->duree_reelle;
        $rdv->photos_apres = $storedPhotos;
        $rdv->incident_terrain = $this->incident_terrain;
        $rdv->client_presence_confirmed_at = $this->client_presence_confirmee ? ($rdv->client_presence_confirmed_at ?? now()) : $rdv->client_presence_confirmed_at;
        $rdv->client_signature_path = $signaturePath ?: $rdv->client_signature_path;
        $rdv->mission_finished_at = now();
        $rdv->status = 'termine';

        $rdv->resetNotificationTrackingIfNeeded($original);
        $rdv->save();

        ActivityLogger::log('mission_terminee_avec_rapport', $rdv, [
            'duree_estimee' => $rdv->duree_estimee,
            'duree_reelle' => $rdv->duree_reelle,
            'has_commentaire_fin' => filled($rdv->commentaire_fin_mission),
            'has_photos_avant' => ! empty($rdv->photos_avant),
            'has_photos_apres' => ! empty($rdv->photos_apres),
            'has_signature' => filled($rdv->client_signature_path),
            'has_incident' => filled($rdv->incident_terrain),
        ]);

        $rdv->client?->notify(new StatutRendezVousNotification($rdv));

        $this->selectedRdvId = $rdv->id;
        $this->fermerRapportFinMission();
        $this->dispatch('toast', 'Rapport de fin de mission enregistré.', 'success');
    }

    public function refuserRdv($payload)
    {
        $this->mettreAJourStatut($payload['id'], 'refuse');
    }

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFiltreStatus()
    {
        $this->resetPage();
    }

    public function updatingPriorite()
    {
        $this->resetPage();
    }

    public function render()
    {
        return view('livewire.employe.mes-rendez-vous', [
            'rendezVous' => $this->paginatedRendezVous(),
            'selectedRendezVous' => $this->selectedRendezVous,
            'selectedMission' => $this->selectedMission,
        ]);
    }
}
