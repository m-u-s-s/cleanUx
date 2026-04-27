<?php

namespace App\Livewire\Client;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class MissionQrCodes extends Component
{
    public Mission $mission;

    public function mount(Mission $mission): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $isOwner = $mission->rendezVous?->client_id === $user->id
            || (
                $mission->organization_account_id
                && $user->organization_account_id
                && $mission->organization_account_id === $user->organization_account_id
            );

        abort_unless($isOwner, 403);

        $this->mission = $mission;
    }

    public function render(): View
    {
        $startCode = $this->mission->verificationCodes()
            ->where('code_type', 'start')
            ->where('is_consumed', false)
            ->latest('id')
            ->first();

        $endCode = $this->mission->verificationCodes()
            ->where('code_type', 'end')
            ->where('is_consumed', false)
            ->latest('id')
            ->first();

        return view('livewire.client.mission-qr-codes', [
            'startQr' => $startCode ? base64_encode(QrCode::format('svg')->size(220)->generate(
                route('employe.missions.qr.validate', [
                    'mission' => $this->mission->id,
                    'type' => 'start',
                    'codeId' => $startCode->id,
                ])
            )) : null,

            'endQr' => $endCode ? base64_encode(QrCode::format('svg')->size(220)->generate(
                route('employe.missions.qr.validate', [
                    'mission' => $this->mission->id,
                    'type' => 'end',
                    'codeId' => $endCode->id,
                ])
            )) : null,
        ]);
    }
}