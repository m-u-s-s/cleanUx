<?php

namespace App\Livewire\Provider\Onboarding;

use App\Models\ProviderOnboardingDocument;
use App\Models\ServiceZone;
use App\Services\Onboarding\ProviderOnboardingService;
use App\Services\Payments\StripeConnectService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Phase 14.1 — Wizard d'onboarding prestataire en 7 étapes (côté provider).
 *
 * Route : /provider/onboarding
 *
 * Étapes :
 *   0. Profil (nom, photo, bio)
 *   1. Identité (1 doc parmi ID/passport/résidence)
 *   2. Numéro fiscal (TVA / SIREN)
 *   3. Assurance pro
 *   4. Compétences + zones
 *   5. Stripe Connect (lien externe)
 *   6. En attente validation admin
 *
 * Affiche progress bar + steps cliquables (revient en arrière) + champs spécifiques
 * à l'étape courante. Idempotent : on peut re-uploader, re-saisir, etc.
 */
class ProviderOnboardingWizard extends Component
{
    use WithFileUploads;

    public int $currentStep = 0;

    // Step 0 — Profil
    public string $name = '';
    public ?string $phone = '';
    public ?string $bio = '';
    public $photo = null;

    // Step 1 — Identité
    public string $identityType = 'identity_card';
    public $identityFile = null;

    // Step 2 — Fiscal
    public ?string $taxId = '';

    // Step 3 — Assurance
    public $insuranceFile = null;

    // Step 4 — Compétences + zones
    public array $selectedSkills = [];
    public array $selectedZones = [];
    public array $availableSkills = [
        'cleaning_residential' => 'Nettoyage résidentiel',
        'cleaning_office'      => 'Nettoyage bureau',
        'plumbing'             => 'Plomberie',
        'electrical'           => 'Électricité',
        'gardening'            => 'Jardinage',
        'moving'               => 'Déménagement',
        'handyman'             => 'Bricolage / petits travaux',
        'painting'             => 'Peinture',
    ];

    public ?string $message = null;
    public ?string $messageType = null;

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        // Pré-remplir depuis le user existant
        $this->name = $user->name ?? '';
        $this->phone = $user->phone ?? '';

        // Démarrer / charger l'onboarding
        $profile = app(ProviderOnboardingService::class)->startOnboarding($user);
        $this->currentStep = (int) $profile->onboarding_step;
        $this->bio = $profile->bio ?? '';
        $this->taxId = $profile->metadata['tax_id'] ?? '';
        $this->selectedSkills = is_array($profile->skills) ? $profile->skills : [];
        $this->selectedZones = $profile->metadata['service_zone_ids'] ?? [];
    }

    public function getProgressProperty(): array
    {
        return app(ProviderOnboardingService::class)->getProgress(Auth::user());
    }

    public function getDocumentsProperty()
    {
        return ProviderOnboardingDocument::forUser(Auth::id())
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('document_type');
    }

    public function getZonesProperty()
    {
        return ServiceZone::where('status', 'active')->orderBy('name')->get();
    }

    // ──────────────────────────────────────────────
    // Navigation
    // ──────────────────────────────────────────────

    public function goToStep(int $step): void
    {
        if ($step < 0 || $step > 6) return;

        // Ne peut pas sauter en avant des étapes pas faites
        $maxAllowed = max($this->currentStep, $step);
        $progress = $this->progress;
        if ($step > $progress['current_step']) return;

        $this->currentStep = $step;
        $this->message = null;
    }

    // ──────────────────────────────────────────────
    // Étape 0 — Profil
    // ──────────────────────────────────────────────

    public function saveStep0(): void
    {
        $this->validate([
            'name'  => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'bio'   => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'image', 'max:5120'],
        ]);

        try {
            app(ProviderOnboardingService::class)->setProfileBasics(
                Auth::user(),
                [
                    'name'  => $this->name,
                    'phone' => $this->phone,
                    'bio'   => $this->bio,
                ],
                $this->photo,
            );
            $this->photo = null;
            $this->currentStep = 1;
            $this->flashMessage('✅ Profil enregistré.', 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->flashMessage('Erreur lors de l\'enregistrement.', 'error');
        }
    }

    // ──────────────────────────────────────────────
    // Étape 1 — Identité
    // ──────────────────────────────────────────────

    public function saveStep1(): void
    {
        $this->validate([
            'identityType' => ['required', 'in:identity_card,passport,residence_permit'],
            'identityFile' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        try {
            app(ProviderOnboardingService::class)->uploadDocument(
                Auth::user(),
                $this->identityType,
                $this->identityFile,
            );
            $this->identityFile = null;
            $this->currentStep = 2;
            $this->flashMessage('✅ Document d\'identité envoyé. En attente de validation admin.', 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->flashMessage('Erreur lors de l\'upload.', 'error');
        }
    }

    // ──────────────────────────────────────────────
    // Étape 2 — Fiscal
    // ──────────────────────────────────────────────

    public function saveStep2(): void
    {
        $this->validate([
            'taxId' => ['required', 'string', 'min:5', 'max:30'],
        ]);

        try {
            app(ProviderOnboardingService::class)->setTaxInfo(Auth::user(), $this->taxId);
            $this->currentStep = 3;
            $this->flashMessage('✅ Numéro fiscal enregistré.', 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->flashMessage('Erreur.', 'error');
        }
    }

    // ──────────────────────────────────────────────
    // Étape 3 — Assurance
    // ──────────────────────────────────────────────

    public function saveStep3(): void
    {
        $this->validate([
            'insuranceFile' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
        ]);

        try {
            app(ProviderOnboardingService::class)->uploadDocument(
                Auth::user(),
                ProviderOnboardingDocument::TYPE_INSURANCE,
                $this->insuranceFile,
            );
            $this->insuranceFile = null;
            $this->currentStep = 4;
            $this->flashMessage('✅ Attestation d\'assurance envoyée.', 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->flashMessage('Erreur.', 'error');
        }
    }

    // ──────────────────────────────────────────────
    // Étape 4 — Compétences + zones
    // ──────────────────────────────────────────────

    public function saveStep4(): void
    {
        $this->validate([
            'selectedSkills'   => ['required', 'array', 'min:1'],
            'selectedSkills.*' => ['string'],
            'selectedZones'    => ['nullable', 'array'],
            'selectedZones.*'  => ['integer'],
        ]);

        try {
            app(ProviderOnboardingService::class)->setSkills(
                Auth::user(),
                $this->selectedSkills,
                $this->selectedZones,
            );
            $this->currentStep = 5;
            $this->flashMessage('✅ Compétences enregistrées.', 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->flashMessage('Erreur.', 'error');
        }
    }

    // ──────────────────────────────────────────────
    // Étape 5 — Stripe Connect
    // ──────────────────────────────────────────────

    public function startStripeOnboarding(): void
    {
        try {
            $url = app(StripeConnectService::class)->onboardingLink(Auth::user());
            $this->dispatch('open-stripe-link', url: $url);
        } catch (\Throwable $e) {
            report($e);
            $this->flashMessage('Erreur lors du lancement Stripe Connect : ' . $e->getMessage(), 'error');
        }
    }

    public function refreshStripeStatus(): void
    {
        try {
            app(StripeConnectService::class)->syncAccountStatus(Auth::user());
            app(ProviderOnboardingService::class)->markStripeConnectComplete(Auth::user()->fresh());

            $user = Auth::user()->fresh();
            if ($user->stripe_connect_status === 'active') {
                $this->currentStep = 6;
                $this->flashMessage('✅ Compte Stripe actif. Validation admin en attente.', 'success');
            } else {
                $this->flashMessage('⏳ Compte Stripe pas encore actif. Termine l\'onboarding sur Stripe et re-clique.', 'info');
            }
        } catch (\Throwable $e) {
            report($e);
            $this->flashMessage('Erreur lors de la vérification : ' . $e->getMessage(), 'error');
        }
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    protected function flashMessage(string $msg, string $type): void
    {
        $this->message = $msg;
        $this->messageType = $type;
    }

    public function clearMessage(): void
    {
        $this->message = null;
        $this->messageType = null;
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        return view('livewire.provider.onboarding.provider-onboarding-wizard', [
            'progress'  => $this->progress,
            'documents' => $this->documents,
            'zones'     => $this->zones,
        ]);
    }
}
