<?php

namespace App\Livewire\Admin\FaceCheck;

use App\Models\PlatformModule;
use App\Models\ProviderFaceCheck;
use App\Models\ProviderFaceIncident;
use App\Models\ProviderFaceProfile;
use App\Models\User;
use App\Services\FaceCheck\FaceCheckIncidentService;
use App\Services\FaceCheck\FaceCheckService;
use App\Services\FaceCheck\FaceCheckSettings;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/** LE CENTRE DE VÉRIFICATION FACIALE — la file d'attente d'un humain. */
class FaceCheckCenter extends Component
{
    use EnforcesAdminAccess;
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    /** revue | incidents | historique | reglages */
    public string $tab = 'revue';

    public string $search = '';

    public string $filtreSeverite = '';

    #[Locked]
    public ?int $profilOuvert = null;

    // ── Réglages du module ────────────────────────────────────────────────
    public bool $moduleActif = false;

    public int $minHours = 24;

    public int $maxHours = 72;

    public int $maxAttempts = 3;

    public int $failureThreshold = 2;

    public int $abandonThreshold = 3;

    public int $abandonWindowDays = 7;

    public int $abandonFraudThreshold = 6;

    public float $matchThreshold = 75.0;

    public float $idMatchThreshold = 65.0;

    public bool $livenessRequired = true;

    public int $selfieRetentionDays = 30;

    /** @var list<string> */
    protected $queryString = ['tab', 'search', 'filtreSeverite'];

    public function mount(): void
    {
        $this->chargerLesReglages();
    }

    // ── Gestes d'administrateur ───────────────────────────────────────────

    public function ouvrirLeProfil(int $profilId): void
    {
        $this->profilOuvert = ProviderFaceProfile::query()->whereKey($profilId)->value('id');
    }

    public function fermerLeProfil(): void
    {
        $this->profilOuvert = null;
    }

    public function leverLeBlocage(int $profilId, string $note = ''): void
    {
        $this->agir(function () use ($profilId, $note) {
            $profil = ProviderFaceProfile::findOrFail($profilId);
            app(FaceCheckService::class)->unblock($profil, Auth::user(), $note ?: null);

            return 'Blocage levé. Un nouveau contrôle sera exigé immédiatement.';
        });
    }

    public function bloquer(int $profilId): void
    {
        $this->agir(function () use ($profilId) {
            $profil = ProviderFaceProfile::findOrFail($profilId);
            app(FaceCheckService::class)->block($profil, ProviderFaceProfile::BLOCK_ADMIN);

            return 'Prestataire bloqué.';
        });
    }

    public function validerLAppariement(int $profilId): void
    {
        $this->agir(function () use ($profilId) {
            $profil = ProviderFaceProfile::findOrFail($profilId);
            app(FaceCheckService::class)->overrideIdMatch($profil, Auth::user(), true, 'Validé à l’œil par un administrateur.');

            return 'Appariement validé manuellement, blocage levé.';
        });
    }

    public function refuserLAppariement(int $profilId): void
    {
        $this->agir(function () use ($profilId) {
            $profil = ProviderFaceProfile::findOrFail($profilId);
            app(FaceCheckService::class)->overrideIdMatch($profil, Auth::user(), false, 'Refusé à l’œil par un administrateur.');

            return 'Appariement refusé, prestataire bloqué.';
        });
    }

    public function revoquerLeVisage(int $profilId): void
    {
        $this->agir(function () use ($profilId) {
            $profil = ProviderFaceProfile::findOrFail($profilId);
            app(FaceCheckService::class)->revokeReference($profil, Auth::user(), 'Visage de référence révoqué.');

            return 'Visage de référence supprimé. Le prestataire devra le réenregistrer.';
        });
    }

    public function forcerUnControle(int $profilId): void
    {
        $this->agir(function () use ($profilId) {
            $profil = ProviderFaceProfile::with('user')->findOrFail($profilId);

            if ($profil->user === null) {
                return 'Ce profil n’a plus d’utilisateur.';
            }

            app(FaceCheckService::class)->forceCheck($profil->user, Auth::user());

            return 'Contrôle exigé au prochain geste du prestataire.';
        });
    }

    public function accuserReception(int $incidentId): void
    {
        $this->agir(function () use ($incidentId) {
            $incident = ProviderFaceIncident::findOrFail($incidentId);
            app(FaceCheckIncidentService::class)->acknowledge($incident, Auth::user());

            return 'Incident pris en charge.';
        });
    }

    public function cloreLIncident(int $incidentId, string $resolution): void
    {
        $this->agir(function () use ($incidentId, $resolution) {
            $incident = ProviderFaceIncident::findOrFail($incidentId);

            // La résolution vient du navigateur : on ne prend que ce qu'on connaît.
            $resolution = in_array($resolution, ['fixed', 'fraud_confirmed', 'dismissed'], true)
                ? $resolution
                : 'dismissed';

            app(FaceCheckIncidentService::class)->resolve($incident, Auth::user(), $resolution);

            return 'Incident clos.';
        });
    }

    // ── Réglages ──────────────────────────────────────────────────────────

    public function enregistrerLesReglages(): void
    {
        $valide = $this->validate([
            'moduleActif' => ['boolean'],
            'minHours' => ['required', 'integer', 'min:1', 'max:720'],
            'maxHours' => ['required', 'integer', 'min:1', 'max:720', 'gte:minHours'],
            'maxAttempts' => ['required', 'integer', 'min:1', 'max:10'],
            'failureThreshold' => ['required', 'integer', 'min:1', 'max:10'],
            'abandonThreshold' => ['required', 'integer', 'min:1', 'max:50'],
            'abandonWindowDays' => ['required', 'integer', 'min:1', 'max:90'],
            'abandonFraudThreshold' => ['required', 'integer', 'min:1', 'max:50', 'gte:abandonThreshold'],
            'matchThreshold' => ['required', 'numeric', 'min:0', 'max:100'],
            'idMatchThreshold' => ['required', 'numeric', 'min:0', 'max:100'],
            'livenessRequired' => ['boolean'],
            'selfieRetentionDays' => ['required', 'integer', 'min:1', 'max:365'],
        ], [
            'maxHours.gte' => "L'intervalle maximal ne peut pas être inférieur au minimal.",
            'abandonFraudThreshold.gte' => 'Le seuil de fraude ne peut pas être inférieur au seuil d’alerte.',
        ]);

        $module = $this->module();

        if ($module === null) {
            $this->dispatch('toast', 'Le module n’est pas déclaré en base. Lancez le seeder des modules.', 'error');

            return;
        }

        // FUSION, comme dans la page des modules : `settings` porte aussi l'audience par zone, réglée ailleurs.
        $reglages = $module->settings ?? [];
        $reglages['face_check'] = [
            'min_hours' => (int) $valide['minHours'],
            'max_hours' => (int) $valide['maxHours'],
            'max_attempts' => (int) $valide['maxAttempts'],
            'failure_threshold' => (int) $valide['failureThreshold'],
            'abandon_threshold' => (int) $valide['abandonThreshold'],
            'abandon_window_days' => (int) $valide['abandonWindowDays'],
            'abandon_fraud_threshold' => (int) $valide['abandonFraudThreshold'],
            'match_threshold' => (float) $valide['matchThreshold'],
            'id_match_threshold' => (float) $valide['idMatchThreshold'],
            'liveness_required' => (bool) $valide['livenessRequired'],
            'selfie_retention_days' => (int) $valide['selfieRetentionDays'],
        ];

        $module->update([
            'settings' => $reglages,
            'is_enabled' => (bool) $valide['moduleActif'],
        ]);

        app(FaceCheckSettings::class)->forget();

        $this->dispatch('toast', 'Réglages enregistrés.', 'success');
    }

    // ── Rendu ─────────────────────────────────────────────────────────────

    public function render(): View
    {
        return view('livewire.admin.face-check.face-check-center', [
            'kpis' => $this->kpis(),
            'items' => $this->file(),
            'profil' => $this->profilOuvert !== null
                ? ProviderFaceProfile::with(['user', 'idDocument'])->find($this->profilOuvert)
                : null,
            'derniersControles' => $this->derniersControles(),
            'moduleDeclare' => $this->module() !== null,
            'zonesCouvertes' => $this->module()?->settingsList('allowed_zone_ids') ?? [],
        ]);
    }

    /** @return array<string, int> */
    private function kpis(): array
    {
        return [
            'a_revoir' => ProviderFaceProfile::query()->awaitingReview()->count(),
            'bloques' => ProviderFaceProfile::query()->blocked()->count(),
            'incidents_ouverts' => ProviderFaceIncident::query()->open()->count(),
            'fraudes_possibles' => ProviderFaceIncident::query()
                ->open()
                ->where('severity', ProviderFaceIncident::SEVERITY_CRITICAL)
                ->count(),
            'controles_24h' => ProviderFaceCheck::query()
                ->where('requested_at', '>=', now()->subDay())
                ->count(),
            'echecs_24h' => ProviderFaceCheck::query()
                ->where('requested_at', '>=', now()->subDay())
                ->where('status', ProviderFaceCheck::STATUS_FAILED)
                ->count(),
        ];
    }

    private function file(): mixed
    {
        $recherche = trim($this->search);

        return match ($this->tab) {
            'incidents' => ProviderFaceIncident::query()
                ->with('user:id,name,email')
                ->open()
                ->when($this->filtreSeverite !== '', fn ($q) => $q->where('severity', $this->filtreSeverite))
                ->when($recherche !== '', fn ($q) => $q->whereHas(
                    'user',
                    fn ($u) => $u->where('name', 'like', "%{$recherche}%")->orWhere('email', 'like', "%{$recherche}%")
                ))
                ->orderByRaw("CASE severity WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
                ->latest('id')
                ->paginate(20),

            'historique' => ProviderFaceCheck::query()
                ->with('user:id,name,email')
                ->when($recherche !== '', fn ($q) => $q->whereHas(
                    'user',
                    fn ($u) => $u->where('name', 'like', "%{$recherche}%")->orWhere('email', 'like', "%{$recherche}%")
                ))
                ->latest('requested_at')
                ->paginate(25),

            'reglages' => ProviderFaceProfile::query()->whereRaw('1 = 0')->paginate(1),

            default => ProviderFaceProfile::query()
                ->with('user:id,name,email')
                ->awaitingReview()
                ->when($recherche !== '', fn ($q) => $q->whereHas(
                    'user',
                    fn ($u) => $u->where('name', 'like', "%{$recherche}%")->orWhere('email', 'like', "%{$recherche}%")
                ))
                ->orderByDesc('blocked_at')
                ->latest('updated_at')
                ->paginate(20),
        };
    }

    private function derniersControles(): mixed
    {
        if ($this->profilOuvert === null) {
            return collect();
        }

        return ProviderFaceCheck::query()
            ->where('provider_face_profile_id', $this->profilOuvert)
            ->latest('requested_at')
            ->limit(10)
            ->get();
    }

    /** Les URL d'image sont SIGNÉES ET COURTES — dix minutes, comme les pièces d'onboarding. */
    public function urlDeLaReference(ProviderFaceProfile $profil): ?string
    {
        if ($profil->reference_path === null) {
            return null;
        }

        return URL::temporarySignedRoute('admin.face-check.reference', now()->addMinutes(10), [
            'profile' => $profil->id,
        ]);
    }

    public function urlDuSelfie(ProviderFaceCheck $controle): ?string
    {
        if ($controle->selfie_path === null) {
            return null;
        }

        return URL::temporarySignedRoute('admin.face-check.selfie', now()->addMinutes(10), [
            'faceCheck' => $controle->id,
        ]);
    }

    private function chargerLesReglages(): void
    {
        $reglages = app(FaceCheckSettings::class);

        $this->moduleActif = (bool) $this->module()?->is_enabled;
        $this->minHours = $reglages->minHours();
        $this->maxHours = $reglages->maxHours();
        $this->maxAttempts = $reglages->maxAttempts();
        $this->failureThreshold = $reglages->failureThreshold();
        $this->abandonThreshold = $reglages->abandonThreshold();
        $this->abandonWindowDays = $reglages->abandonWindowDays();
        $this->abandonFraudThreshold = $reglages->abandonFraudThreshold();
        $this->matchThreshold = $reglages->matchThreshold();
        $this->idMatchThreshold = $reglages->idMatchThreshold();
        $this->livenessRequired = $reglages->livenessRequired();
        $this->selfieRetentionDays = $reglages->selfieRetentionDays();
    }

    private function module(): ?PlatformModule
    {
        return PlatformModule::query()
            ->where('key', (string) config('face_check.module_key', 'security.face_check'))
            ->first();
    }

    /** LES GARDES SE REVÉRIFIENT À CHAQUE ACTION. */
    private function agir(callable $geste): void
    {
        $utilisateur = Auth::user();

        if (! $utilisateur instanceof User || ! $utilisateur->isAdmin()) {
            abort(403);
        }

        try {
            $message = $geste();
            $this->dispatch('toast', $message, 'success');
        } catch (\Throwable $e) {
            report($e);
            $this->dispatch('toast', 'Erreur : '.$e->getMessage(), 'error');
        }
    }
}
