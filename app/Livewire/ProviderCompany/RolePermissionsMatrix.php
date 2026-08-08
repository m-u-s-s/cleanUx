<?php

namespace App\Livewire\ProviderCompany;

use App\Enums\OrganizationRole;
use App\Models\OrganizationRolePermission;
use App\Services\PermissionService;
use App\Support\Livewire\Concerns\EnforcesActiveOrgMembership;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * « CHEZ NOUS, LES CHEFS D'ÉQUIPE ASSIGNENT LES MISSIONS. »
 *
 * `organization_role_permissions` existe depuis le 2026-08-06, `PermissionService::memberCan()` la
 * lit comme deuxième étage de résolution — et AUCUN écran, AUCUN endpoint ne l'écrivait. La table
 * était vide en production, remplie seulement par des tests : une capacité annoncée dans le code,
 * inaccessible à qui devait s'en servir. Un propriétaire pouvait accorder un droit à une PERSONNE
 * (dérogation nominative, premier étage), mais pas décider d'une règle de maison — cela réclamait un
 * déploiement.
 *
 * CET ÉCRAN EST DONC LE PREMIER ÉCRIVAIN. Il accorde ET retire : `granted` est un booléen explicite,
 * non une simple présence, ce qui permet à une société de refuser un droit que le code accorde par
 * défaut. Sans réglage, aucune ligne n'existe et la résolution retombe intégralement sur la matrice
 * du code — le comportement d'origine reste le comportement par défaut.
 *
 * LA PERMISSION EST REVÉRIFIÉE À CHAQUE ACTION, jamais seulement au montage : Livewire ne rejoue pas
 * `mount()`, et un droit retiré pendant qu'un onglet reste ouvert doit fermer la porte
 * immédiatement. Motif `TeamManagement`.
 *
 * @property-read array<string, array<string, bool>> $matrice
 * @property-read list<OrganizationRole> $rolesReglables
 * @property-read list<string> $permissions
 */
class RolePermissionsMatrix extends Component
{
    use EnforcesActiveOrgMembership;

    /** La clé qui gouverne cet écran — distribuer des droits n'est pas inviter. */
    private const PERMISSION_REQUISE = 'members.manage_permissions';

    public function mount(): void
    {
        $this->exigeLaPermission();
    }

    private function exigeLaPermission(): void
    {
        $utilisateur = Auth::user();

        abort_unless(
            $utilisateur !== null
                && app(PermissionService::class)->can(
                    $utilisateur,
                    self::PERMISSION_REQUISE,
                    $utilisateur->currentOrganization,
                ),
            403
        );
    }

    /**
     * Les rôles réglables — ceux d'une société prestataire, et pas le propriétaire.
     *
     * LE PROPRIÉTAIRE EST HORS MATRICE, ET C'EST UNE PROTECTION, PAS UN OUBLI. Il est le seul à
     * porter `members.manage_permissions` par défaut : lui laisser la retirer à son propre rôle
     * fermerait cet écran à tout le monde, définitivement et sans recours en base.
     *
     * @return list<OrganizationRole>
     */
    public function getRolesReglablesProperty(): array
    {
        return array_values(array_filter(
            OrganizationRole::forProviderCompany(),
            fn (OrganizationRole $role) => $role !== OrganizationRole::OWNER,
        ));
    }

    /** @return list<string> */
    public function getPermissionsProperty(): array
    {
        return app(PermissionService::class)->allPermissionKeys();
    }

    /**
     * L'état EFFECTIF de chaque case : réglage de la société s'il existe, défaut du code sinon.
     *
     * Une matrice qui n'afficherait que les réglages explicites montrerait un tableau vide au
     * premier usage, et laisserait croire que personne n'a de droits.
     *
     * @return array<string, array<string, bool>>
     */
    public function getMatriceProperty(): array
    {
        $orgId = Auth::user()?->current_organization_id;
        $permissions = app(PermissionService::class);

        $reglages = $orgId === null
            ? collect()
            : OrganizationRolePermission::query()
                ->where('organization_account_id', $orgId)
                ->get()
                ->groupBy('role');

        $matrice = [];

        foreach ($this->rolesReglables as $role) {
            $parRole = $reglages->get($role->value, collect())
                ->pluck('granted', 'permission');

            foreach ($this->permissions as $permission) {
                $matrice[$role->value][$permission] = $parRole->has($permission)
                    ? (bool) $parRole->get($permission)
                    : $permissions->roleAccordeParDefaut($role->value, $permission);
            }
        }

        return $matrice;
    }

    /**
     * Basculer une case.
     *
     * ON ÉCRIT TOUJOURS UNE LIGNE, y compris pour revenir à la valeur par défaut. Supprimer la ligne
     * serait plus économe et ambigu : « aucun réglage » et « réglé comme le défaut » cesseraient
     * d'être distinguables, et un changement du défaut dans une version future déplacerait
     * silencieusement une décision que la société avait prise.
     */
    public function basculer(string $role, string $permission): void
    {
        $this->exigeLaPermission();

        $orgId = Auth::user()->current_organization_id;

        // Les deux valeurs viennent du navigateur : une case inventée écrirait une ligne que rien ne
        // relira, et un rôle hors périmètre réglerait la société cliente depuis l'écran prestataire.
        $roleValide = in_array(
            $role,
            array_map(fn (OrganizationRole $r) => $r->value, $this->rolesReglables),
            true,
        );

        if (! $roleValide || ! in_array($permission, $this->permissions, true)) {
            return;
        }

        $actuel = $this->matrice[$role][$permission] ?? false;

        OrganizationRolePermission::updateOrCreate(
            [
                'organization_account_id' => $orgId,
                'role' => $role,
                'permission' => $permission,
            ],
            ['granted' => ! $actuel],
        );

        /*
         * PURGER TOUTE L'ORGANISATION, PAS LE SEUL ACTEUR. Ce réglage change les droits de plusieurs
         * personnes d'un coup ; ne vider que son propre cache laisserait les autres sur l'ancienne
         * réponse pendant une minute — un délai invisible et incompréhensible pour eux.
         */
        app(PermissionService::class)->invalidateOrganizationCache((int) $orgId);

        unset($this->matrice);
    }

    /**
     * Rendre un rôle à son réglage d'usine.
     *
     * Une sortie de secours explicite : sans elle, revenir en arrière demanderait de se souvenir de
     * ce que le code accordait avant qu'on y touche.
     */
    public function reinitialiser(string $role): void
    {
        $this->exigeLaPermission();

        $orgId = Auth::user()->current_organization_id;

        OrganizationRolePermission::query()
            ->where('organization_account_id', $orgId)
            ->where('role', $role)
            ->delete();

        app(PermissionService::class)->invalidateOrganizationCache((int) $orgId);

        unset($this->matrice);
    }

    public function render(): View
    {
        return view('livewire.provider-company.role-permissions-matrix', [
            'roles' => $this->rolesReglables,
            'permissions' => $this->permissions,
            'matrice' => $this->matrice,
        ])->layout('layouts.provider-company');
    }
}
