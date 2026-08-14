<?php

namespace App\Livewire\Employe;

use App\Models\Mission;
use App\Models\MissionChecklistItem;
use App\Models\MissionMedia;
use App\Services\Missions\MissionChecklistService;
use App\Services\Missions\OnSite\MissionMediaService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Component;
use Livewire\WithFileUploads;

class MissionExecutionBoard extends Component
{
    use WithFileUploads;

    public Mission $mission;

    public array $beforePhotos = [];

    public array $afterPhotos = [];

    public ?string $successMessage = null;

    public ?string $errorMessage = null;

    public function mount(Mission $mission): void
    {
        abort_unless($mission->exists, 404);

        // Une affectation révoquée n'ouvre plus rien — voir `Mission::estIntervenant()`.
        $isAssigned = $mission->estIntervenant(Auth::id());

        abort_unless($isAssigned, 403);

        $this->mission = $mission->load(['checklists.items', 'media', 'serviceCatalog']);
        $this->ensureChecklist();
    }

    public function toggleChecklistItem(int $itemId): void
    {
        $this->resetMessages();

        $item = MissionChecklistItem::query()
            ->whereHas('checklist', fn ($q) => $q->where('mission_id', $this->mission->id))
            ->findOrFail($itemId);

        // `done` / `todo` : le vocabulaire de la colonne, et celui que lit la porte de clôture.
        // Cet écran écrivait `completed`, que personne ne reconnaît — cocher toutes les tâches
        // laissait donc la mission impossible à terminer. {@see MissionChecklistService}
        $completed = $item->status === MissionChecklistService::FAITE;

        $item->update([
            'status' => $completed ? MissionChecklistService::A_FAIRE : MissionChecklistService::FAITE,
            'completed_by_user_id' => $completed ? null : Auth::id(),
            'completed_at' => $completed ? null : now(),
        ]);

        app(MissionChecklistService::class)->refreshProgress($item->checklist);

        $this->mission = $this->mission->fresh(['checklists.items', 'media']);
        $this->successMessage = 'Checklist mise à jour.';
    }

    public function uploadBeforePhotos(): void
    {
        $this->resetMessages();

        $this->validate([
            'beforePhotos.*' => ['image', 'max:8192'],
        ]);

        $this->enregistrer(
            $this->beforePhotos,
            MissionMedia::TYPE_BEFORE_PHOTO,
            $this->mission->start_lat,
            $this->mission->start_lng,
        );

        $this->beforePhotos = [];
        $this->mission = $this->mission->fresh(['checklists.items', 'media']);
        $this->successMessage = 'Photos avant ajoutées.';
    }

    public function uploadAfterPhotos(): void
    {
        $this->resetMessages();

        $this->validate([
            'afterPhotos.*' => ['image', 'max:8192'],
        ]);

        $this->enregistrer(
            $this->afterPhotos,
            MissionMedia::TYPE_AFTER_PHOTO,
            $this->mission->end_lat,
            $this->mission->end_lng,
        );

        $this->afterPhotos = [];
        $this->mission = $this->mission->fresh(['checklists.items', 'media']);
        $this->successMessage = 'Photos après ajoutées.';
    }

    /**
     * UN SEUL CHEMIN D'ENREGISTREMENT, partagé avec l'application mobile.
     *
     * Ce composant écrivait ses lignes lui-même : pas d'empreinte du fichier, aucune diffusion au
     * client, rien dans l'historique de la mission. Une photo déposée depuis le navigateur ne
     * valait donc pas la même chose qu'une photo déposée depuis le téléphone — et c'est celle qui
     * vaut le moins qu'on aurait produite le jour du litige.
     *
     * La position vient de la MISSION et non de l'appareil : un navigateur de bureau n'en a pas
     * d'utile, et inventer un relevé serait pire que de n'en avoir aucun.
     *
     * @param  list<mixed>  $photos
     * @param  MissionMedia::TYPE_*  $type
     */
    private function enregistrer(array $photos, string $type, mixed $lat, mixed $lng): void
    {
        $service = app(MissionMediaService::class);

        foreach ($photos as $photo) {
            if (! $photo instanceof UploadedFile) {
                continue;
            }

            try {
                $service->capture(
                    $this->mission,
                    Auth::user(),
                    $photo,
                    $type,
                    $lat !== null ? (float) $lat : null,
                    $lng !== null ? (float) $lng : null,
                );
            } catch (\Throwable $e) {
                // Une photo refusée ne doit pas emporter les précédentes : le prestataire en
                // envoie plusieurs d'un coup, et tout rejeter l'obligerait à tout recommencer.
                Log::warning('[terrain] Photo non enregistrée depuis le web.', [
                    'mission_id' => $this->mission->id,
                    'media_type' => $type,
                    'error' => $e->getMessage(),
                ]);
                $this->errorMessage = 'Une photo au moins n’a pas pu être enregistrée.';
            }
        }
    }

    protected function ensureChecklist(): void
    {
        app(MissionChecklistService::class)->ensureChecklist($this->mission);
        $this->mission = $this->mission->fresh(['checklists.items', 'media']);
    }

    protected function resetMessages(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    public function render()
    {
        $checklist = $this->mission->checklists->first();
        $beforeMedia = $this->mission->media->where('media_type', 'before_photo');
        $afterMedia = $this->mission->media->where('media_type', 'after_photo');

        return view('livewire.employe.mission-execution-board', [
            'checklist' => $checklist,
            'beforeMedia' => $beforeMedia,
            'afterMedia' => $afterMedia,
        ]);
    }
}
