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

/**
 * LE CONTRÔLE FACIAL SUR LE WEB — la même porte, la même remédiation.
 *
 * La parité n'est pas un confort ici, c'est une condition de fonctionnement : le middleware
 * `face.verified` et `FaceCheckRequiredException` redirigent une session web vers
 * `provider.face-check`. Sans cette page, la redirection retombait sur l'accueil et le prestataire
 * tournait en rond sans jamais comprendre ce qu'on lui demandait.
 *
 * Et la moitié des prestataires de ce dépôt travaillent depuis le web : un module de sécurité
 * qui ne couvrirait que le mobile serait contournable en ouvrant un navigateur.
 */
class FaceCheckPage extends Component
{
    use WithFileUploads;

    /**
     * L'image capturée par le navigateur, téléversée depuis un canevas.
     *
     * `#[Locked]` n'a pas de sens sur un téléversement — c'est la validation qui garde ici, et la
     * décision, elle, appartient entièrement au serveur.
     *
     * @var mixed
     */
    public $selfie;

    public bool $consentement = false;

    public string $messageDIncident = '';

    public bool $signalementEnvoye = false;

    /**
     * L'identifiant du contrôle en cours. VERROUILLÉ : c'est une garde. Sans `#[Locked]`, le
     * navigateur pourrait le remplacer par celui de quelqu'un d'autre par un simple `$set` —
     * Livewire ne rejoue pas `mount()`, et la propriété publique est écrivable de l'extérieur.
     */
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
            'consentement.accepted' => "L'enregistrement de votre visage exige votre accord explicite.",
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
            $this->dispatch('toast', 'Votre visage a été enregistré.', 'success');
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
            $this->dispatch('toast', 'Aucun contrôle en cours. Rechargez la page.', 'error');

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
                ProviderFaceCheck::STATUS_PASSED => 'Identité confirmée. Bonne journée.',
                ProviderFaceCheck::STATUS_FAILED => "Nous n'avons pas pu vous reconnaître. Un administrateur va examiner votre dossier.",
                default => 'Photo non reconnue. Placez-vous face à la lumière et réessayez.',
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

    /**
     * « Ça ne marche pas. » Ouvre un dossier — et ne débloque rien, l'écran le dit.
     */
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
        ])->layout('layouts.app');
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
