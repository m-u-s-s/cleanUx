<?php

namespace App\Livewire\Client;

use App\Models\Mission;
use App\Models\MissionClientAction;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MissionClientActions extends Component
{
    public Mission $mission;
    public string $issueMessage = '';
    public ?string $successMessage = null;
    public ?string $errorMessage = null;

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

    public function confirmPresence(): void
    {
        $this->resetMessages();

        MissionClientAction::query()->create([
            'mission_id' => $this->mission->id,
            'client_user_id' => Auth::id(),
            'action_type' => 'presence_confirmed',
            'status' => 'submitted',
            'acted_at' => now(),
        ]);

        $this->successMessage = 'Présence validée.';
    }

    public function reportIssue(): void
    {
        $this->resetMessages();

        $this->validate([
            'issueMessage' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        MissionClientAction::query()->create([
            'mission_id' => $this->mission->id,
            'client_user_id' => Auth::id(),
            'action_type' => 'issue_reported',
            'status' => 'submitted',
            'message' => $this->issueMessage,
            'acted_at' => now(),
        ]);

        $this->issueMessage = '';
        $this->successMessage = 'Problème signalé.';
    }

    protected function resetMessages(): void
    {
        $this->successMessage = null;
        $this->errorMessage = null;
    }

    public function render()
    {
        return view('livewire.client.mission-client-actions');
    }
}