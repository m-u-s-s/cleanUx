<?php

namespace App\Livewire\Client;

use App\Models\Mission;
use App\Models\User;
use App\Notifications\EmployeArriveNotification;
use App\Notifications\MissionEndCodeNotification;
use App\Services\Missions\MissionLifecycleService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MissionTracking extends Component
{
    public Mission $mission;

    /** Le refus du serveur, affiché tel quel — une mission non démarrée n'a pas de code de fin. */
    public ?string $erreurCode = null;

    protected function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public function mount(Mission $mission): void
    {
        abort_unless($mission->exists, 404);

        $user = $this->currentUser();

        $isOwner =
            $mission->booking?->client_id === $user?->id
            || $mission->organization_account_id === $user?->organization_account_id;

        abort_unless($isOwner, 403);

        $this->mission = $mission->load([
            'booking',
            'leadEmployee',
            'verificationCodes',
            'activeTrackingSession',
        ]);
    }

    public function render(): View
    {
        $this->mission->load([
            'verificationCodes',
            'booking',
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

        $user = $this->currentUser();

        $latestArrivalNotification = $user
            ? $user->notifications()
                ->where('type', EmployeArriveNotification::class)
                ->where('data->mission_id', $this->mission->id)
                ->latest('id')
                ->first()
            : null;

        // Le code de fin se lit dans la notification du CLIENT, comme celui de début juste au-dessus.
        $latestEndCodeNotification = ($user && $endCodeRecord)
            ? $user->notifications()
                ->where('type', MissionEndCodeNotification::class)
                ->where('data->code_id', $endCodeRecord->id)
                ->latest('id')
                ->first()
            : null;

        // UN CODE EXPIRÉ N'EST PAS UN CODE.
        $encoreValide = $endCodeRecord
            && ($endCodeRecord->expires_at === null || $endCodeRecord->expires_at->isFuture());

        return view('livewire.client.mission-tracking', [
            'startCodeRecord' => $startCodeRecord,
            'endCodeRecord' => $endCodeRecord,
            'clientStartCode' => data_get($latestArrivalNotification?->data, 'start_code'),
            'clientEndCode' => $encoreValide ? data_get($latestEndCodeNotification?->data, 'end_code') : null,
            'endCodeExpiresAt' => $encoreValide ? $endCodeRecord->expires_at : null,
        ]);
    }

    /** LE CLIENT DEMANDE SON CODE DE FIN LUI-MÊME. */
    public function genererCodeDeFin(): void
    {
        $this->erreurCode = null;

        try {
            app(MissionLifecycleService::class)->generateEndCode($this->mission->fresh());
            $this->mission = $this->mission->fresh(['verificationCodes', 'booking', 'leadEmployee']);
        } catch (\Throwable $e) {
            $this->erreurCode = $e->getMessage();
        }
    }
}
