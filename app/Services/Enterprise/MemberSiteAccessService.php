<?php

namespace App\Services\Enterprise;

use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** QUELS LOCAUX CE MEMBRE A-T-IL LE DROIT DE VOIR. */
class MemberSiteAccessService
{
    /**
     * Les identifiants de sites auxquels ce membre est restreint, ou `null` s'il ne l'est pas.
     *
     * @return array<int, int>|null
     */
    public function sitesAutorises(User $user): ?array
    {
        $membre = $this->membreActif($user);

        // LE REPLI NE DÉPEND PAS DE L'APPARTENANCE, et c'est une correction qu'il a fallu faire après coup : rendre `null` faute de ligne de membre supprimait la restriction des comptes dont la portée était portée par le JSON seul.
        if ($membre) {
            $lignes = DB::table('organization_member_site_access')
                ->where('organization_member_id', $membre->id)
                ->pluck('organization_site_id')
                ->map(static fn ($id) => (int) $id)
                ->all();

            if ($lignes !== []) {
                return $lignes;
            }
        }

        $heritage = (array) data_get($user->metadata, 'entreprise_context.allowed_site_ids', []);

        $heritage = array_values(array_unique(array_map(
            static fn ($id) => (int) $id,
            array_filter($heritage, static fn ($id) => filled($id)),
        )));

        return $heritage !== [] ? $heritage : null;
    }

    /**
     * Restreindre un membre à une liste de locaux — ou lever la restriction avec un tableau vide.
     *
     * @param  array<int, int>  $siteIds
     */
    public function definirLesSites(OrganizationMember $membre, array $siteIds): void
    {
        $siteIds = array_values(array_unique(array_map('intval', $siteIds)));

        DB::transaction(function () use ($membre, $siteIds) {
            DB::table('organization_member_site_access')
                ->where('organization_member_id', $membre->id)
                ->delete();

            if ($siteIds === []) {
                return;
            }

            DB::table('organization_member_site_access')->insert(array_map(
                static fn (int $siteId) => [
                    'organization_member_id' => $membre->id,
                    'organization_site_id' => $siteId,
                    'access_level' => 'view',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                $siteIds,
            ));
        });

        // L'ANCIEN EMPLACEMENT EST TENU À JOUR TANT QU'IL EST LU.
        $user = $membre->user;

        if ($user) {
            $metadata = (array) ($user->metadata ?? []);
            data_set($metadata, 'entreprise_context.allowed_site_ids', $siteIds);
            data_set($metadata, 'entreprise_context.site_scope', $siteIds === [] ? 'all' : 'selected');
            $user->forceFill(['metadata' => $metadata])->save();
        }
    }

    protected function membreActif(User $user): ?OrganizationMember
    {
        $organisationId = (int) ($user->organization_account_id ?? 0);

        if ($organisationId <= 0) {
            return null;
        }

        return OrganizationMember::query()
            ->where('organization_account_id', $organisationId)
            ->where('user_id', $user->id)
            ->first();
    }
}
