<?php

namespace App\Services\Admin;

use App\Models\AdminRole;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\Platform\SiegeDuSuperAdmin;
use App\Support\ActivityLogger;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * ACCORDER ET RETIRER DES CAPACITÉS D'ADMINISTRATION — la porte unique.
 *
 * Distribuer du pouvoir est le geste le plus sensible d'une console. Trois règles le tiennent,
 * et aucune n'est cosmétique :
 *
 * 1. ON N'AUGMENTE PAS SES PROPRES CAPACITÉS. Sans cela, la seule capacité « gestion
 *    utilisateurs » suffirait à s'octroyer les vingt autres — l'écran deviendrait une porte
 *    vers tout le reste.
 * 2. ON NE DONNE QUE CE QU'ON A. Un administrateur ne peut pas fabriquer une capacité qu'il ne
 *    détient pas lui-même ; le titulaire du siège les a toutes, donc lui peut tout donner.
 * 3. LE TITULAIRE DU SIÈGE EST INTOUCHABLE ICI. Ses capacités ne viennent pas d'une case, et
 *    son siège ne se déplace que par {@see SiegeDuSuperAdmin}.
 *
 * LIRE N'EST PAS ÉCRIRE. La page s'ouvre à `manage-users` — savoir qui peut toucher à l'argent
 * est une question légitime d'audit. Distribuer, lui, exige `perform-critical-admin-actions`,
 * comme {@see UserPolicy::updateAdminSecurity()} l'exigeait déjà.
 */
class GestionDesRolesService
{
    /** @return array<string, string> */
    public function capacitesConnues(): array
    {
        return User::allowedAdminPermissions();
    }

    /**
     * @param  list<string>  $capacites
     *
     * @throws DomainException
     */
    public function creerUnRole(User $acteur, string $nom, array $capacites, ?string $perimetre = null): AdminRole
    {
        $nom = trim($nom);

        if ($nom === '') {
            throw new DomainException('Un rôle porte un nom.');
        }

        $this->exigeLeDroitDeDistribuer($acteur);
        $capacites = $this->capacitesAutorisees($acteur, $capacites);

        return AdminRole::create([
            'name' => $nom,
            'slug' => $this->slugLibre($nom),
            'permissions' => $capacites,
            'access_scope' => $perimetre,
        ]);
    }

    /**
     * @param  list<string>  $capacites
     *
     * @throws DomainException
     */
    public function modifierUnRole(User $acteur, AdminRole $role, string $nom, array $capacites, ?string $perimetre = null): AdminRole
    {
        $nom = trim($nom);

        if ($nom === '') {
            throw new DomainException('Un rôle porte un nom.');
        }

        // CE QU'ON NE PEUT PAS DONNER, ON NE PEUT PAS NON PLUS LE RETIRER EN AVEUGLE : les
        // capacités hors de portée de l'acteur restent telles qu'elles sont, sinon un
        // administrateur au périmètre étroit viderait un rôle qu'il ne comprend pas.
        $this->exigeLeDroitDeDistribuer($acteur);

        $horsDePortee = array_values(array_diff($role->capacites(), $this->capacitesDe($acteur)));
        $retenues = $this->capacitesAutorisees($acteur, $capacites);

        $role->forceFill([
            'name' => $nom,
            'permissions' => array_values(array_unique([...$retenues, ...$horsDePortee])),
            'access_scope' => $perimetre,
        ])->save();

        ActivityLogger::critical('security.admin_role_updated', $role, [
            'domain' => 'security',
            'capacites' => $role->capacites(),
        ]);

        return $role->refresh();
    }

    /** @throws DomainException */
    public function supprimerUnRole(AdminRole $role): void
    {
        // LES COMPTES NE PARTENT PAS AVEC LE RÔLE : ils retombent sur leurs seules capacités
        // individuelles, et on le dit avant plutôt que de le découvrir après.
        DB::transaction(function () use ($role) {
            User::query()->where('admin_role_id', $role->id)->update(['admin_role_id' => null]);
            $role->delete();
        });
    }

    /**
     * ASSIGNER UN RÔLE, DES CAPACITÉS EN PLUS, ET UN PÉRIMÈTRE.
     *
     * @param  list<string>  $capacitesIndividuelles
     *
     * @throws DomainException
     */
    public function appliquerA(
        User $acteur,
        User $cible,
        ?AdminRole $role,
        array $capacitesIndividuelles,
        string $perimetre,
        ?int $zoneGeree = null,
    ): User {
        $this->exigeUneCibleModifiable($acteur, $cible);

        // UN PERIMETRE « UNE SEULE ZONE » SANS ZONE ne limite rien et n'ouvre rien : le
        // compte se retrouve borne a une zone qui n'existe pas.
        if ($perimetre === User::ACCESS_SCOPE_ZONE && $zoneGeree === null) {
            throw new DomainException('Un périmètre limité à une zone exige de choisir laquelle.');
        }

        // LES CAPACITÉS HORS DE PORTÉE DE L'ACTEUR SURVIVENT : il ne peut ni les donner, ni les
        // reprendre. Un administrateur au périmètre étroit ne doit pas pouvoir désarmer un
        // collègue plus large que lui en décochant des cases qu'il ne voit même pas.
        $horsDePortee = array_values(array_diff($cible->permissionList(), $this->capacitesDe($acteur)));
        $retenues = $this->capacitesAutorisees($acteur, $capacitesIndividuelles);

        if ($role !== null) {
            $refusees = array_values(array_diff($role->capacites(), $this->capacitesDe($acteur)));

            if ($refusees !== []) {
                throw new DomainException(
                    'Ce rôle porte des capacités que vous ne détenez pas : '.implode(', ', $refusees)
                );
            }
        }

        $cible->forceFill([
            'admin_role_id' => $role?->id,
            'permissions' => array_values(array_unique([...$retenues, ...$horsDePortee])),
            'access_scope' => $perimetre,
            'managed_service_zone_id' => $perimetre === User::ACCESS_SCOPE_ZONE ? $zoneGeree : null,
        ])->save();

        ActivityLogger::critical('security.admin_capabilities_updated', $cible, [
            'domain' => 'security',
            'role' => $role?->slug,
            'capacites' => $cible->fresh()?->permissionList() ?? [],
            'perimetre' => $perimetre,
        ]);

        return $cible->refresh();
    }

    /**
     * CE QUE L'ACTEUR PEUT DONNER.
     *
     * @return list<string>
     */
    public function capacitesDe(User $acteur): array
    {
        if ($acteur->isSuperAdmin()) {
            return array_keys($this->capacitesConnues());
        }

        return array_values(array_intersect(
            $acteur->permissionList(),
            array_keys($this->capacitesConnues()),
        ));
    }

    /**
     * @param  list<string>  $demandees
     * @return list<string>
     *
     * @throws DomainException
     */
    private function capacitesAutorisees(User $acteur, array $demandees): array
    {
        $connues = array_keys($this->capacitesConnues());
        $demandees = array_values(array_unique(array_filter($demandees, 'is_string')));

        // UNE CAPACITÉ INVENTÉE N'OUVRE RIEN, et la laisser passer ferait croire le contraire à
        // qui la coche.
        $inconnues = array_values(array_diff($demandees, $connues));

        if ($inconnues !== []) {
            throw new DomainException('Capacités inconnues : '.implode(', ', $inconnues));
        }

        $interdites = array_values(array_diff($demandees, $this->capacitesDe($acteur)));

        if ($interdites !== []) {
            throw new DomainException(
                'Vous ne pouvez pas accorder une capacité que vous n’avez pas : '.implode(', ', $interdites)
            );
        }

        return $demandees;
    }

    /** @throws DomainException */
    private function exigeLeDroitDeDistribuer(User $acteur): void
    {
        if (! $acteur->canPerformCriticalAdminActions()) {
            throw new DomainException('Distribuer des capacités exige la capacité « Actions critiques ».');
        }
    }

    /** @throws DomainException */
    private function exigeUneCibleModifiable(User $acteur, User $cible): void
    {
        $this->exigeLeDroitDeDistribuer($acteur);

        if ($cible->id === $acteur->id) {
            throw new DomainException('Vous ne modifiez pas vos propres capacités.');
        }

        if ($cible->isSuperAdmin()) {
            throw new DomainException('Les capacités du titulaire du siège ne se règlent pas ici.');
        }

        if (! $cible->isPlatformAdmin()) {
            throw new DomainException('Ce compte n’est pas un administrateur de plateforme.');
        }
    }

    private function slugLibre(string $nom): string
    {
        $base = AdminRole::slugPour($nom);
        $slug = $base;
        $suffixe = 2;

        while (AdminRole::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffixe++;
        }

        return $slug;
    }
}
