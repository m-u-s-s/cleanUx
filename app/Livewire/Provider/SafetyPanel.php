<?php

namespace App\Livewire\Provider;

use App\Models\SafetyAlert;
use App\Services\Safety\SafetyAlertService;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** LE MODE SÉCURITÉ / SOS, CÔTÉ WEB (E33). POURQUOI IL EXISTE AUSSI ICI. */
class SafetyPanel extends Component
{
    public string $message = '';

    public string $contactNom = '';

    public string $contactTelephone = '';

    #[Locked]
    public ?string $refus = null;

    public function mount(): void
    {
        $profil = Auth::user()?->providerProfile;

        $this->contactNom = (string) ($profil->emergency_contact_name ?? '');
        $this->contactTelephone = (string) ($profil->emergency_contact_phone ?? '');
    }

    public function declencher(string $niveau): void
    {
        app(SafetyAlertService::class)->declencher(
            Auth::user(),
            $niveau,
            null,
            $this->message !== '' ? $this->message : null,
        );

        $this->reset(['message', 'refus']);
    }

    public function fermer(int $alerteId): void
    {
        // Une alerte À MOI, ou rien : on ne referme pas celle d'un autre.
        $alerte = SafetyAlert::query()
            ->where('user_id', Auth::id())
            ->find($alerteId);

        if ($alerte === null) {
            return;
        }

        try {
            app(SafetyAlertService::class)->cloturer($alerte, Auth::user());
            $this->refus = null;
        } catch (DomainException $e) {
            $this->refus = $e->getMessage();
        }
    }

    /** Enregistrer le contact d'urgence — à froid, jamais au moment du déclenchement. */
    public function enregistrerLeContact(): void
    {
        $this->validate([
            'contactNom' => ['nullable', 'string', 'max:120'],
            'contactTelephone' => ['nullable', 'string', 'max:40'],
        ]);

        $profil = Auth::user()?->providerProfile;

        $profil?->forceFill([
            'emergency_contact_name' => $this->contactNom !== '' ? $this->contactNom : null,
            'emergency_contact_phone' => $this->contactTelephone !== '' ? $this->contactTelephone : null,
        ])->save();
    }

    public function render(): View
    {
        return view('livewire.provider.safety-panel', [
            'alerte' => app(SafetyAlertService::class)->alerteOuverteDe(Auth::user()),
        ])->layout('layouts.app');
    }
}
