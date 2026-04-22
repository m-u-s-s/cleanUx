<?php

namespace App\Livewire\Client;

use App\Models\Mission;
use App\Notifications\EmployeArriveNotification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MissionTracking extends Component
{
    public Mission $mission;

    public function mount(Mission $mission): void
    {
        abort_unless($mission->exists, 404);

        $user = Auth::user();

        $isOwner =
            $mission->rendezVous?->client_id === $user?->id
            || $mission->organization_account_id === $user?->organization_account_id;

        abort_unless($isOwner, 403);

        $this->mission = $mission->load([
            'rendezVous',
            'leadEmployee',
            'verificationCodes',
            'activeTrackingSession',
        ]);
    }

    public function render()
    {
        $this->mission->load([
            'verificationCodes',
            'rendezVous',
            'leadEmployee',
            'activeTrackingSession',
        ]);

        $startCodeRecord = $this->mission->verificationCodes()
            ->where('code_type', 'start')
            ->where('is_consumed', false)
            ->latest('id')
            ->first();

        $endCodeRecord = $this->mission->verificationCodes()
            ->where('code_type', 'end')
            ->where('is_consumed', false)
            ->latest('id')
            ->first();

        $latestArrivalNotification = Auth::user()
            ? Auth::user()->notifications()
                ->where('type', EmployeArriveNotification::class)
                ->where('data->mission_id', $this->mission->id)
                ->latest('id')
                ->first()
            : null;

        return view('livewire.client.mission-tracking', [
            'startCodeRecord' => $startCodeRecord,
            'endCodeRecord' => $endCodeRecord,
            'clientStartCode' => data_get($latestArrivalNotification?->data, 'start_code'),
        ]);
    }
}
