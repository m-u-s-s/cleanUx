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

    public function getV2Props(): array
    {
        $mission = $this->mission ?? null;
        if (!$mission) {
            return [
                'missionId' => 0,
                'initialEtaMinutes' => 0,
                'initialDistanceKm' => 0.0,
                'initialCurrentStep' => 'enroute',
                'provider' => ['name' => '—', 'rating' => 5.0, 'missionsCount' => 0, 'label' => 'Prestataire'],
                'isUrgent' => false,
            ];
        }

        $stepMap = [
            'planned' => 'accepted',
            'assigned' => 'accepted',
            'en_route' => 'enroute',
            'arrived' => 'arrived',
            'started' => 'mission',
            'in_mission' => 'mission',
            'completed' => 'done',
        ];

        $leadProvider = $mission->leadProvider;
        $booking = $mission->booking;
        $serviceCatalog = $booking?->serviceCatalog ?? null;

        // ETA from Mission last_eta_seconds / last_eta_meters (Trip Tracking v2 writes here)
        $etaMinutes = $mission->last_eta_seconds
            ? (int) round($mission->last_eta_seconds / 60)
            : 0;
        $distanceKm = $mission->last_eta_meters
            ? (float) round($mission->last_eta_meters / 1000, 1)
            : 0.0;

        $providerProfile = $leadProvider?->providerProfile;

        return [
            'missionId' => $mission->id,
            'initialEtaMinutes' => $etaMinutes,
            'initialDistanceKm' => $distanceKm,
            'initialCurrentStep' => $stepMap[$mission->status] ?? 'enroute',
            'provider' => [
                'name' => $leadProvider?->name ?? 'Provider',
                'rating' => (float) ($providerProfile?->rating_avg ?? 5.0),
                'missionsCount' => 0,
                'label' => data_get($serviceCatalog, 'name') ?? 'Prestataire',
            ],
            'isUrgent' => ($booking?->priority ?? null) === 'urgent',
        ];
    }
}