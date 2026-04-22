<?php

namespace App\Livewire\Client;

use App\Models\Mission;
use App\Services\Missions\MissionQualityService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MissionFinalValidation extends Component
{
    public Mission $mission;
    public string $comment = '';
    public ?string $successMessage = null;

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

    public function satisfied(): void
    {
        app(MissionQualityService::class)->submitClientFinalValidation(
            $this->mission->fresh(),
            Auth::user(),
            true,
            $this->comment ?: null
        );

        $this->comment = '';
        $this->mission = $this->mission->fresh(['report', 'qualityReviews', 'incidents']);
        $this->successMessage = 'Validation client enregistrée.';
    }

    public function problem(): void
    {
        $this->validate([
            'comment' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        app(MissionQualityService::class)->submitClientFinalValidation(
            $this->mission->fresh(),
            Auth::user(),
            false,
            $this->comment
        );

        $this->comment = '';
        $this->mission = $this->mission->fresh(['report', 'qualityReviews', 'incidents']);
        $this->successMessage = 'Problème enregistré.';
    }

    public function render()
    {
        return view('livewire.client.mission-final-validation');
    }
}