<?php

namespace App\Livewire\Client;

use App\Models\Mission;
use App\Models\User;
use App\Notifications\EmployeArriveNotification;
use App\Notifications\MissionEndCodeNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class MissionTracking extends Component
{
    public Mission $mission;

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

        /*
         * Le code de fin se lit dans la notification du CLIENT, comme celui de début juste au-dessus.
         *
         * La vue le prenait dans `session('mission_end_code_…')` — une clé écrite dans la session DU
         * PRESTATAIRE (MissionActions::prepareEndCode), donc jamais présente dans le navigateur du
         * client, et carrément inexistante lorsque le prestataire clôture depuis l'application
         * mobile, authentifiée par jeton et sans session. L'encadré affichait son texte de repli à
         * la place des six chiffres, quel que soit l'état réel de la mission.
         */
        $latestEndCodeNotification = $user
            ? $user->notifications()
                ->where('type', MissionEndCodeNotification::class)
                ->where('data->mission_id', $this->mission->id)
                ->latest('id')
                ->first()
            : null;

        return view('livewire.client.mission-tracking', [
            'startCodeRecord' => $startCodeRecord,
            'endCodeRecord' => $endCodeRecord,
            'clientStartCode' => data_get($latestArrivalNotification?->data, 'start_code'),
            'clientEndCode' => data_get($latestEndCodeNotification?->data, 'end_code'),
        ]);
    }
}
