<?php

namespace App\Livewire\Client;

use App\Models\Mission;
use App\Models\MissionIncident;
use App\Models\MissionMedia;
use App\Services\Missions\OnSite\MissionIncidentService;
use App\Services\Missions\OnSite\MissionMediaService;
use App\Services\Missions\OnSite\MissionTimelineService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/** LE SUIVI CLIENT, SUR LE WEB — et pas seulement jusqu'à la porte. */
class MissionLiveTracking extends Component
{
    public Mission $mission;

    public function mount(Mission $mission): void
    {
        abort_unless($mission->exists, 404);

        $user = Auth::user();

        $isOwner =
            $mission->booking?->client_id === $user?->id
            || (
                $mission->organization_account_id
                && $user?->organization_account_id
                && $mission->organization_account_id === $user->organization_account_id
            );

        abort_unless($isOwner, 403);

        $this->mission = $mission;
    }

    /**
     * Le déroulé de l'intervention.
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function fil(): array
    {
        return app(MissionTimelineService::class)->pour($this->mission, vueClient: true);
    }

    /**
     * Les photos, groupées comme le comparateur les lit.
     *
     * @return array{before: list<array<string, mixed>>, after: list<array<string, mixed>>}
     */
    #[Computed]
    public function photos(): array
    {
        $service = app(MissionMediaService::class);
        $medias = $service->pourLaMission($this->mission, clientSeulement: true);

        return [
            'before' => $medias
                ->where('media_type', MissionMedia::TYPE_BEFORE_PHOTO)
                ->map(fn (MissionMedia $m) => $service->presenter($m))
                ->values()
                ->all(),
            'after' => $medias
                ->where('media_type', MissionMedia::TYPE_AFTER_PHOTO)
                ->map(fn (MissionMedia $m) => $service->presenter($m))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function imprevus(): array
    {
        $service = app(MissionIncidentService::class);

        return $service
            ->pourLaMission($this->mission, clientSeulement: true)
            ->map(fn (MissionIncident $i) => $service->presenter($i))
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.client.mission-live-tracking');
    }
}
