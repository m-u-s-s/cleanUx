<?php

namespace App\Livewire\Admin\Providers;

use App\Models\OrganizationAccount;
use App\Models\ProviderProfile;
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
     * Pour une société, l'organisation créée à l'inscription est activée dans la même
     * transaction : c'est elle que consomment l'espace provider-company et le rattachement des
     * missions. L'oublier laisserait l'organisation en `pending`, dans un état intermédiaire que
     * plus personne ne viendrait débloquer.
     */
    public function approve(int $profileId): void
    {
        $profile = $this->selfRegisteredProfile($profileId);

        DB::transaction(function () use ($profile) {
            $profile->forceFill(['status' => 'active'])->save();

            if ($profile->organization_account_id) {
                OrganizationAccount::query()
                    ->whereKey($profile->organization_account_id)
                    ->update(['status' => 'active']);
            }
        });

        session()->flash('success', "Inscription approuvée : {$profile->user?->name} a désormais accès à l'application.");
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
            ->with('user:id,name')
            ->whereNotNull('self_registered_at')
            ->findOrFail($profileId);
    }

    public function getRegistrationsProperty()
    {
        return ProviderProfile::query()
            ->with(['user:id,name,email,phone,created_at', 'organization:id,name,tva_number,status'])
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
