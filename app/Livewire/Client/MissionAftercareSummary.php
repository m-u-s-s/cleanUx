<?php

namespace App\Livewire\Client;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MissionAftercareSummary extends Component
{
    public Mission $mission;

    public function mount(Mission $mission): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $isOwner =
            $mission->rendezVous?->client_id === $user->id
            || (
                $mission->organization_account_id
                && $user->organization_account_id
                && $mission->organization_account_id === $user->organization_account_id
            );

        abort_unless($isOwner, 403);

        $this->mission = $mission->load([
            'rendezVous',
            'leadEmployee',
            'media.uploadedBy',
            'checklists.items.completedBy',
            'incidents',
            'report',
        ]);
    }

    public function render(): View
    {
        $this->mission->load([
            'rendezVous',
            'leadEmployee',
            'media.uploadedBy',
            'checklists.items.completedBy',
            'incidents',
            'report',
        ]);

        $beforePhotos = $this->mission->media
            ->where('media_type', 'before_photo')
            ->values();

        $afterPhotos = $this->mission->media
            ->where('media_type', 'after_photo')
            ->values();

        $checklist = $this->mission->checklists->first();

        return view('livewire.client.mission-aftercare-summary', [
            'beforePhotos' => $beforePhotos,
            'afterPhotos' => $afterPhotos,
            'checklist' => $checklist,
            'incidents' => $this->mission->incidents,
            'report' => $this->mission->report,
        ]);
    }
}