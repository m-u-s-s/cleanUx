<?php

namespace App\Livewire\Client;

use App\Models\Mission;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MissionLiveTracking extends Component
{
    public Mission $mission;

    public function mount(Mission $mission): void
    {
        abort_unless($mission->exists, 404);

        $user = Auth::user();

        $isOwner =
            $mission->rendezVous?->client_id === $user?->id
            || (
                $mission->organization_account_id
                && $user?->organization_account_id
                && $mission->organization_account_id === $user->organization_account_id
            );

        abort_unless($isOwner, 403);

        $this->mission = $mission;
    }

    public function render()
    {
        return view('livewire.client.mission-live-tracking');
    }
}