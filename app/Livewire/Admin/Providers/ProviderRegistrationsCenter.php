<?php

namespace App\Livewire\Admin\Providers;

use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
use App\Services\Onboarding\ProviderDossierSummary;
use App\Services\Onboarding\ProviderOnboardingService;
use App\Support\Livewire\Concerns\EnforcesAdminAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Approbation des prestataires inscrits en libre-service depuis l'app mobile.
 *
 * Route : /admin/inscriptions-prestataires
 *
 * Ces comptes naissent en `status = pending` avec `self_registered_at` renseigné. Le middleware
 * provider.approved les cantonne à leur dossier d'onboarding tant qu'un humain ne les a pas
 * approuvés : cet écran est la bascule, qui se faisait sinon à la main en base.
 *
 * Volontairement distinct d'AdminOnboardingProvidersList, malgré la proximité apparente. Cet
 * écran-là traite l'approbation FINALE sur pièces justificatives et refuse tout profil sans
 * document — donc précisément ces nouveaux comptes, qui n'en ont encore aucun. Les deux flux
 * s'enchaînent : approbation d'inscription ici, validation du dossier là-bas.
 *
 * Le périmètre est la garantie principale : seuls les profils portant `self_registered_at` sont
 * listés. Les prestataires antérieurs ne sont soumis à aucune attente d'approbation et ne
 * doivent jamais apparaître ici — un test le verrouille.
 */
class ProviderRegistrationsCenter extends Component
{
    use EnforcesAdminAccess;
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $search = '';

    /** pending | approved | rejected | all */
    public string $filter = 'pending';

    /**
     * Motif saisi pour approuver un dossier incomplet, indexé par profil. Consigné dans les
     * métadonnées du profil : un passage en force doit laisser une trace de qui et pourquoi.
     *
     * @var array<int, string>
     */
    public array $overrideReason = [];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Ouvre l'accès complet à la surface prestataire.
     *
     * Cette méthode posait `status = 'active'` sans rien vérifier : un administrateur approuvait
     * à l'aveugle un prestataire n'ayant franchi aucune vérification, et le profil restait
     * `verification_status = 'unverified'` indéfiniment — alors que l'autre voie d'approbation,
     * ProviderOnboardingService::approveOnboarding(), exige identité, assurance et compte de
     * paiement. Les deux voies affirmaient des choses différentes du même prestataire.
     *
     * L'approbation reste un geste unique, mais elle sait désormais ce qu'elle approuve :
     *
     *  - dossier complet → `verification_status` passe à `verified` et le parcours v2 est marqué
     *    terminé, ce qui aligne les deux moteurs ;
     *  - dossier incomplet → l'approbation exige un motif, qui est consigné, et le statut de
     *    vérification n'est PAS touché : on n'affirme pas une vérification qui n'a pas eu lieu.
     *
     * Pour une société, l'organisation créée à l'inscription est activée dans la même
     * transaction : c'est elle que consomment l'espace provider-company et le rattachement des
     * missions. L'oublier laisserait l'organisation en `pending`, dans un état intermédiaire que
     * plus personne ne viendrait débloquer.
     */
    public function approve(int $profileId): void
    {
        $profile = $this->selfRegisteredProfile($profileId);
        $user = $profile->user;

        if (! $user) {
            session()->flash('error', 'Ce profil n’est rattaché à aucun compte.');

            return;
        }

        $summary = app(ProviderDossierSummary::class)->for($user);
        $reason = trim($this->overrideReason[$profileId] ?? '');

        if (! $summary['is_complete'] && $reason === '') {
            // On ne refuse pas le passage en force — un administrateur a de bonnes raisons de
            // débloquer un dossier. On refuse qu'il soit silencieux.
            session()->flash('error', 'Dossier incomplet : indiquez un motif pour approuver malgré tout.');

            return;
        }

        DB::transaction(function () use ($profile, $user, $summary, $reason) {
            $attributes = ['status' => 'active'];

            if ($summary['can_mark_verified']) {
                $attributes['verification_status'] = 'verified';
                $attributes['verified_at'] = now();
            }

            $attributes['metadata'] = array_merge($profile->metadata ?? [], [
                'registration_approved_by_admin_id' => auth()->id(),
                'registration_approved_at' => now()->toIso8601String(),
                'registration_approved_with_blockers' => $summary['blockers'],
                'registration_override_reason' => $reason !== '' ? $reason : null,
            ]);

            $profile->forceFill($attributes)->save();

            if ($profile->organization_account_id) {
                OrganizationAccount::query()
                    ->whereKey($profile->organization_account_id)
                    ->update(['status' => 'active']);
            }

            // Le parcours v2 n'est marqué terminé que s'il l'est réellement : le forcer sur un
            // dossier incomplet ferait mentir le cockpit du prestataire, qui afficherait 100 %
            // avec des étapes non franchies.
            if ($summary['is_complete']) {
                app(ProviderOnboardingService::class)->markOnboardingV2Completed($user);
            }
        });

        unset($this->overrideReason[$profileId]);

        session()->flash('success', $summary['is_complete']
            ? "Inscription approuvée : {$user->name} a désormais accès à l'application."
            : "Inscription approuvée malgré un dossier incomplet : {$user->name}. Le motif est consigné.");
    }

    /**
     * Détail du dossier, pour que l'écran montre ce qu'il approuve.
     *
     * @return array<string, mixed>
     */
    public function dossierFor(ProviderProfile $profile): array
    {
        return $profile->user
            ? app(ProviderDossierSummary::class)->for($profile->user)
            : ['is_complete' => false, 'blockers' => ['Compte introuvable'], 'warnings' => [], 'journey' => ['percent' => 0, 'done' => 0, 'total' => 0, 'missing' => []]];
    }

    /**
     * Le compte reste en base et conserve son historique — on ne supprime pas un utilisateur
     * depuis un écran de modération. La garde provider.approved continue de le bloquer.
     */
    public function reject(int $profileId): void
    {
        $profile = $this->selfRegisteredProfile($profileId);

        DB::transaction(function () use ($profile) {
            $profile->forceFill([
                'status' => 'rejected',
                'verification_status' => 'rejected',
            ])->save();

            if ($profile->organization_account_id) {
                OrganizationAccount::query()
                    ->whereKey($profile->organization_account_id)
                    ->update(['status' => 'rejected']);
            }
        });

        session()->flash('success', "Inscription refusée : {$profile->user?->name} n'a pas accès à l'application.");
    }

    /**
     * Ne résout QUE des profils auto-inscrits : cet écran ne doit pas devenir un moyen détourné
     * de modifier le statut d'un prestataire historique.
     */
    private function selfRegisteredProfile(int $profileId): ProviderProfile
    {
        return ProviderProfile::query()
            // `role` est indispensable : le moteur d'onboarding v2 résout le parcours par le
            // rôle de l'utilisateur. Une sélection partielle l'omettant le laissait à null, et la
            // synchronisation échouait en silence — elle est soft-fail — laissant le parcours du
            // prestataire « en cours » alors que son compte venait d'être approuvé.
            ->with('user:id,name,role')
            ->whereNotNull('self_registered_at')
            ->findOrFail($profileId);
    }

    public function getRegistrationsProperty()
    {
        return ProviderProfile::query()
            ->with(['user:id,name,email,phone,role,created_at', 'organization:id,name,tva_number,status'])
            ->whereNotNull('self_registered_at')
            ->when($this->filter === 'pending', fn ($q) => $q->where('status', 'pending'))
            ->when($this->filter === 'approved', fn ($q) => $q->where('status', 'active'))
            ->when($this->filter === 'rejected', fn ($q) => $q->where('status', 'rejected'))
            ->when($this->search !== '', function ($q) {
                $term = '%'.trim($this->search).'%';
                $q->where(function ($inner) use ($term) {
                    $inner->whereHas('user', fn ($u) => $u->where('name', 'like', $term)->orWhere('email', 'like', $term))
                        ->orWhereHas('organization', fn ($o) => $o->where('name', 'like', $term));
                });
            })
            ->orderByDesc('self_registered_at')
            ->paginate(20);
    }

    /** @return array<string, int> */
    public function getCountsProperty(): array
    {
        $base = ProviderProfile::query()->whereNotNull('self_registered_at');

        return [
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'approved' => (clone $base)->where('status', 'active')->count(),
            'rejected' => (clone $base)->where('status', 'rejected')->count(),
        ];
    }

    #[Layout('layouts.app')]
    public function render(): View
    {
        return view('livewire.admin.providers.provider-registrations-center', [
            'registrations' => $this->registrations,
            'counts' => $this->counts,
        ]);
    }
}
