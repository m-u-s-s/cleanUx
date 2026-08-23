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
 * « CHEZ NOUS, LES CHEFS D'ÉQUIPE ASSIGNENT LES MISSIONS.
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

    /** Basculer une case. */
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

        // PURGER TOUTE L'ORGANISATION, PAS LE SEUL ACTEUR.
        app(PermissionService::class)->invalidateOrganizationCache((int) $orgId);

        unset($this->matrice);
    }

    /** Rendre un rôle à son réglage d'usine. */
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
