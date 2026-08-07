<?php

namespace App\Livewire\ProviderCompany;

use App\Models\Mission;
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
 * Une société de nettoyage qui intervient dans vingt immeubles y place des habitués : celui qui
 * connaît le code de la porte, l'ascenseur en panne, l'étage à ne pas déranger avant 10 h. Cette
 * connaissance ne vivait nulle part — chaque répartition repartait de zéro et de la mémoire du
 * dispatcheur.
 *
 * LES SITES SE DÉDUISENT, ILS NE SE SAISISSENT PAS. Un prestataire ne possède pas les locaux de ses
 * clients : il y intervient. La liste vient donc de ce qui l'y relie réellement — ses missions et
 * ses contrats-cadres. Un formulaire de saisie aurait créé un second registre à tenir à jour, faux
 * dès la première mission oubliée.
 *
 * @property-read Collection<int, OrganizationSite> $sites
 * @property-read Collection<int, OrganizationMember> $membres
 */
class SiteOperations extends Component
{
    use EnforcesActiveOrgMembership;

    public string $recherche = '';

    /**
     * Les sites où cette société a une raison d'être.
     *
     * Deux sources, réunies : les missions déjà planifiées et les contrats-cadres où elle est la
     * partie prestataire. La seconde compte autant que la première — une société est sous contrat
     * sur un site avant d'y avoir envoyé quiconque, et c'est précisément le moment où elle veut
     * désigner un référent.
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
            /*
             * Les référents sont chargés SCOPÉS sur notre organisation, pas tous.
             *
             * Deux prestataires peuvent desservir le même immeuble — l'un le nettoyage, l'autre les
             * espaces verts. Le site est légitimement visible des deux côtés ; la composition de
             * l'équipe adverse ne l'est pas. Sans cette contrainte dans l'eager-load, l'écran
             * afficherait les employés d'un concurrent.
             */
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

    /**
     * Désigner le référent d'un site.
     *
     * Les DEUX identifiants viennent du client, et ni l'un ni l'autre n'est cru : le site doit
     * faire partie de ceux qu'on dessert réellement, et la personne doit être un membre actif de
     * la maison. Un identifiant étranger ne produit rien plutôt qu'une erreur parlante — la
     * différence entre un refus et un « introuvable » dirait déjà quelque chose.
     */
    public function designerReferent(int $siteId, int $userId): void
    {
        $user = Auth::user();
        $orgId = $user?->organizationContextId();

        abort_if($orgId === null, 403);

        /*
         * `sites.assign_members` : déclarée dans la matrice depuis le début, consultée par
         * personne jusqu'ici. Ce n'est pas la même chose que répartir les missions du jour —
         * un dispatcheur organise la journée, il ne redessine pas l'affectation durable des
         * équipes aux sites.
         */
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

        /*
         * Le contrat-cadre porte l'organisation CLIENTE, pas un site : il couvre par construction
         * l'ensemble des locaux de ce client. On remonte donc du contrat au client, puis du client
         * à ses sites.
         */
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
