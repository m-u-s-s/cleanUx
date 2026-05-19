<?php

namespace App\Livewire\Provider;

use App\Models\KycVerification;
use App\Services\Kyc\KycVerificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ProviderKycPage extends Component
{
    public string $errorMessage = '';
    public string $successMessage = '';

    public function start(): void
    {
        $this->reset(['errorMessage', 'successMessage']);

        try {
            $verification = app(KycVerificationService::class)->start(Auth::user());
            $this->successMessage = "Vérification démarrée (référence #{$verification->id}). Suivez les instructions du provider.";
        } catch (\Throwable $e) {
            $this->errorMessage = "Impossible de démarrer la vérification : " . $e->getMessage();
        }
    }

    public function sync(int $verificationId): void
    {
        $verification = KycVerification::query()
            ->where('id', $verificationId)
            ->where('user_id', Auth::id())
            ->first();

        if (! $verification) {
            $this->errorMessage = 'Vérification introuvable.';
            return;
        }

        try {
            app(KycVerificationService::class)->syncStatus($verification);
            $this->dispatch('toast', 'Statut synchronisé.', 'success');
        } catch (\Throwable $e) {
            $this->errorMessage = 'Erreur sync : ' . $e->getMessage();
        }
    }

    public function render(): View
    {
        $verification = KycVerification::query()
            ->where('user_id', Auth::id())
            ->with('checks')
            ->latest('id')
            ->first();

        $profile = Auth::user()->providerProfile;

        return view('livewire.provider.provider-kyc-page', [
            'verification' => $verification,
            'profile' => $profile,
        ]);
    }
}
