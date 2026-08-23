<?php

namespace App\Livewire\ProviderCompany;

use App\Models\Mission;
use App\Models\OrganizationAccount;
use App\Models\OrganizationContract;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\ProviderSiteAssignment;
use App\Services\PermissionService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * LES SITES CLIENTS QUE LA SOCIÉTÉ DESSERT, ET QUI S'EN OCCUPE.
 *
 * @property-read Collection<int, OrganizationSite> $sites
 * @property-read Collection<int, OrganizationMember> $membres
 */
class SiteOperations extends Component
{
    use EnforcesActiveOrgMembership;

    public string $recherche = '';

    /** LA GARDE DE LECTURE QUI MANQUAIT. */
    public function mount(): void
    {
        $this->exigeLaPermissionDeLecture();
    }

    private function exigeLaPermissionDeLecture(): void
    {
        $utilisateur = Auth::user();
        $organisationId = $utilisateur?->organizationContextId();

        abort_unless($utilisateur !== null && $organisationId !== null, 403);

        $organisation = OrganizationAccount::find($organisationId);

        abort_unless(
            $organisation !== null
                && app(PermissionService::class)->can($utilisateur, 'sites.view_all', $organisation),
            403
        );
    }

    /**
     * Les sites où cette société a une raison d'être.
     *
     * @return Collection<int, OrganizationSite>
     */
    public function getSitesProperty(): Collection
    {
        $orgId = Auth::user()?->organizationContextId();

        if (! $orgId) {
            return OrganizationSite::query()->whereRaw('1 = 0')->get();
        }

        return OrganizationSite::query()
            ->whereIn('id', $this->idsDesSitesDesservis($orgId))
            ->when(
                $this->recherche !== '',
                fn ($q) => $q->where('name', 'like', '%'.$this->recherche.'%')
            )
            // Les référents sont chargés SCOPÉS sur notre organisation, pas tous.
            ->with([
                'providerAssignments' => fn ($q) => $q
                    ->where('provider_organization_id', $orgId)
                    ->with('user:id,name,profile_photo_path'),
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * Les membres qu'on peut désigner.
     *
     * @return Collection<int, OrganizationMember>
     */
    public function getMembresProperty(): Collection
    {
        $orgId = Auth::user()?->organizationContextId();

        if (! $orgId) {
            return OrganizationMember::query()->whereRaw('1 = 0')->get();
        }

        return OrganizationMember::query()
            ->where('organization_account_id', $orgId)
            ->where('status', 'active')
            ->with('user:id,name')
            ->get();
    }

    /** Désigner le référent d'un site. */
    public function designerReferent(int $siteId, int $userId): void
    {
        $user = Auth::user();
        $orgId = $user?->organizationContextId();

        abort_if($orgId === null, 403);

        // `sites.assign_members` : déclarée dans la matrice depuis le début, consultée par personne jusqu'ici.
        abort_unless(
            app(PermissionService::class)->can($user, 'sites.assign_members', $orgId),
            403
        );

        if (! in_array($siteId, $this->idsDesSitesDesservis($orgId), true)) {
            return;
        }

        $estDeLaMaison = OrganizationMember::query()
            ->where('organization_account_id', $orgId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->exists();

        if (! $estDeLaMaison) {
            return;
        }

        // `updateOrCreate` sur la clé unique : redésigner remplace, et le geste reste rejouable.
        ProviderSiteAssignment::updateOrCreate(
            [
                'provider_organization_id' => $orgId,
                'organization_site_id' => $siteId,
                'user_id' => $userId,
            ],
            ['role' => ProviderSiteAssignment::ROLE_LEAD],
        );
    }

    public function retirerReferent(int $assignmentId): void
    {
        $user = Auth::user();
        $orgId = $user?->organizationContextId();

        abort_if($orgId === null, 403);

        abort_unless(
            app(PermissionService::class)->can($user, 'sites.assign_members', $orgId),
            403
        );

        // Scoping DANS la requête : une affectation d'une autre société n'est jamais chargée.
        ProviderSiteAssignment::query()
            ->where('provider_organization_id', $orgId)
            ->whereKey($assignmentId)
            ->delete();
    }

    /**
     * Les identifiants des sites desservis — missions ET contrats-cadres.
     *
     * @return list<int>
     */
    private function idsDesSitesDesservis(int $orgId): array
    {
        $parMissions = Mission::query()
            ->where('provider_organization_id', $orgId)
            ->whereNotNull('organization_site_id')
            ->distinct()
            ->pluck('organization_site_id');

        // Le contrat-cadre porte l'organisation CLIENTE, pas un site : il couvre par construction l'ensemble des locaux de ce client.
        $orgsClientesSousContrat = OrganizationContract::query()
            ->where('provider_organization_id', $orgId)
            ->distinct()
            ->pluck('organization_account_id');

        $parContrats = OrganizationSite::query()
            ->whereIn('organization_account_id', $orgsClientesSousContrat)
            ->pluck('id');

        return $parMissions->merge($parContrats)
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function render(): View
    {
        return view('livewire.provider-company.site-operations', [
            'sites' => $this->sites,
            'membres' => $this->membres,
        ])->layout('layouts.provider-company');
    }
}
