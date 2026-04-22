<?php

namespace App\Livewire\Employe;

use App\Models\Mission;
use App\Models\MissionChecklistItem;
use App\Models\MissionMedia;
use App\Services\Missions\MissionChecklistService;
use Illuminate\Support\Facades\Auth;
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

        $isAssigned = $mission->lead_employee_id === Auth::id()
            || $mission->assignments()->where('user_id', Auth::id())->exists();

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

        $completed = $item->status === 'completed';

        $item->update([
            'status' => $completed ? 'pending' : 'completed',
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

        foreach ($this->beforePhotos as $photo) {
            $path = $photo->store('missions/'.$this->mission->id.'/before', 'public');

            MissionMedia::query()->create([
                'mission_id' => $this->mission->id,
                'uploaded_by_user_id' => Auth::id(),
                'media_type' => 'before_photo',
                'path' => $path,
                'caption' => 'Photo avant',
                'taken_at' => now(),
                'lat' => $this->mission->start_lat,
                'lng' => $this->mission->start_lng,
            ]);
        }

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

        foreach ($this->afterPhotos as $photo) {
            $path = $photo->store('missions/'.$this->mission->id.'/after', 'public');

            MissionMedia::query()->create([
                'mission_id' => $this->mission->id,
                'uploaded_by_user_id' => Auth::id(),
                'media_type' => 'after_photo',
                'path' => $path,
                'caption' => 'Photo après',
                'taken_at' => now(),
                'lat' => $this->mission->end_lat,
                'lng' => $this->mission->end_lng,
            ]);
        }

        $this->afterPhotos = [];
        $this->mission = $this->mission->fresh(['checklists.items', 'media']);
        $this->successMessage = 'Photos après ajoutées.';
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