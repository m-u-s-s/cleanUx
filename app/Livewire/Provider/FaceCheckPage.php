<?php

namespace App\Livewire\Provider;

use App\Models\ProviderFaceCheck;
use App\Models\User;
use App\Services\FaceCheck\Data\FaceCheckDecision;
use App\Services\FaceCheck\FaceCheckGate;
use App\Services\FaceCheck\FaceCheckIncidentService;
use App\Services\FaceCheck\FaceCheckRequirement;
use App\Services\FaceCheck\FaceCheckService;
use App\Services\FaceCheck\FaceCheckSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/** LE CONTRÔLE FACIAL SUR LE WEB — la même porte, la même remédiation. */
class FaceCheckPage extends Component
{
    use WithFileUploads;

    /**
     * L'image capturée par le navigateur, téléversée depuis un canevas.
     *
     * @var mixed
     */
    public $selfie;

    public bool $consentement = false;

    public string $messageDIncident = '';

    public bool $signalementEnvoye = false;

    /** L'identifiant du contrôle en cours. VERROUILLÉ : c'est une garde. */
    #[Locked]
    public ?int $controleId = null;

    public function mount(): void
    {
        abort_unless(Auth::check(), 403);

        $this->rafraichir();
    }

    public function rafraichir(): void
    {
        $verdict = $this->verdict();

        // On n'ouvre pas un contrôle qui n'est pas dû : la cadence appartient au serveur.
        if ($verdict->code !== FaceCheckDecision::CHECK_REQUIRED) {
            $this->controleId = $verdict->checkId;

            return;
        }

        $controle = app(FaceCheckService::class)->openCheck(
            $this->prestataire(),
            $verdict->trigger ?? ProviderFaceCheck::TRIGGER_INTERVAL,
            ['ip' => request()->ip()],
        );

        $this->controleId = $controle->id;
    }

    public function enregistrerLeVisage(): void
    {
        $this->validate([
            'selfie' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:8192'],
            'consentement' => ['accepted'],
        ], [
            'consentement.accepted' => __('face_check.errors.consent_required'),
        ]);

        try {
            app(FaceCheckService::class)->enroll(
                provider: $this->prestataire(),
                contents: (string) file_get_contents($this->selfie->getRealPath()),
                mimeType: (string) ($this->selfie->getMimeType() ?: 'image/jpeg'),
                consentement: true,
                contexte: ['ip' => request()->ip()],
            );

            $this->reset('selfie');
            $this->rafraichir();
            $this->dispatch('toast', __('face_check.result.enrolled'), 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', 'Erreur : '.$e->getMessage(), 'error');
        }
    }

    public function envoyerLeSelfie(): void
    {
        $this->validate([
            'selfie' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:8192'],
        ]);

        $controle = $this->controleCourant();

        if ($controle === null) {
            $this->dispatch('toast', __('face_check.errors.no_open_check'), 'error');

            return;
        }

        try {
            $resultat = app(FaceCheckService::class)->submit(
                controle: $controle,
                contents: (string) file_get_contents($this->selfie->getRealPath()),
                mimeType: (string) ($this->selfie->getMimeType() ?: 'image/jpeg'),
            );

            $this->reset('selfie');

            $message = match ($resultat->status) {
                ProviderFaceCheck::STATUS_PASSED => __('face_check.result.passed'),
                ProviderFaceCheck::STATUS_FAILED => __('face_check.result.failed_final'),
                default => __('face_check.result.failed_retry', ['left' => max(0, $this->essaisRestants($resultat))]),
            };

            $this->dispatch(
                'toast',
                $message,
                $resultat->status === ProviderFaceCheck::STATUS_PASSED ? 'success' : 'error'
            );
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', 'Erreur : '.$e->getMessage(), 'error');
        }
    }

    /** « Ça ne marche pas. » Ouvre un dossier — et ne débloque rien, l'écran le dit. */
    public function signaler(): void
    {
        $this->validate([
            'messageDIncident' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        app(FaceCheckIncidentService::class)->reportByProvider(
            provider: $this->prestataire(),
            message: $this->messageDIncident,
            diagnostics: [
                'surface' => 'web',
                'user_agent' => substr((string) request()->userAgent(), 0, 255),
            ],
            check: $this->controleCourant(),
        );

        $this->reset('messageDIncident');
        $this->signalementEnvoye = true;
    }

    public function render(): View
    {
        $verdict = $this->verdict();
        $service = app(FaceCheckService::class);
        $reglages = app(FaceCheckSettings::class);
        $profil = $service->profileFor($this->prestataire());

        return view('livewire.provider.face-check-page', [
            'soumis' => app(FaceCheckRequirement::class)->appliesToProvider($this->prestataire()),
            'verdict' => $verdict,
            'profil' => $profil,
            'controle' => $this->controleCourant(),
            'vivaciteExigee' => $reglages->livenessRequired(),
            'versionDuConsentement' => $reglages->consentVersion(),
            // Le MÊME texte que celui servi par l'API au mobile : une seule source relue une fois.
            'texteDuConsentement' => __('face_check.consent.text', ['days' => $reglages->selfieRetentionDays()]),
            'noteJuridique' => __('face_check.consent.legal_note'),
        ])->layout('layouts.app');
    }

    private function essaisRestants(ProviderFaceCheck $controle): int
    {
        return max(0, app(FaceCheckSettings::class)->maxAttempts() - $controle->attempt_number + 1);
    }

    private function verdict(): FaceCheckDecision
    {
        return app(FaceCheckGate::class)->inspectProvider($this->prestataire());
    }

    private function controleCourant(): ?ProviderFaceCheck
    {
        if ($this->controleId === null) {
            return null;
        }

        // On ne lit QUE ses propres contrôles, même si l'identifiant est verrouillé : deux gardes
        // valent mieux qu'une quand la seconde ne coûte qu'une clause `where`.
        return ProviderFaceCheck::query()
            ->where('id', $this->controleId)
            ->where('user_id', $this->prestataire()->id)
            ->first();
    }

    private function prestataire(): User
    {
        $user = Auth::user();

        abort_if($user === null, 403);

        return $user;
    }
}
