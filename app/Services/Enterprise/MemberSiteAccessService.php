<?php

namespace App\Services\Enterprise;

use App\Models\OrganizationMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * QUELS LOCAUX CE MEMBRE A-T-IL LE DROIT DE VOIR.
 *
 * DEUX MÉCANISMES COEXISTAIENT, ET UN SEUL FONCTIONNAIT. La table
 * `organization_member_site_access` existait avec sa relation `authorizedMembers()` — et rien ne
 * l'écrivait, rien ne la lisait. Pendant ce temps, la restriction réelle passait par un objet JSON
 * posé sur l'utilisateur (`metadata.entreprise_context.allowed_site_ids`), sans écran pour le
 * régler non plus : il fallait modifier la colonne à la main.
 *
 * Résultat concret : un responsable de site d'une entreprise cliente voyait TOUS les locaux de sa
 * société — les réservations, les adresses, les factures des autres agences comprises. La donnée
 * pour l'en empêcher était modélisée depuis le début.
 *
 * LA TABLE DEVIENT LA SOURCE, et le JSON reste un repli. Faire l'inverse aurait laissé la table
 * dormante ; supprimer le JSON aurait rendu leur portée à des comptes déjà restreints en
 * production. Tant qu'aucune ligne n'existe pour un membre, on lit l'ancien emplacement — la
 * bascule se fait donc membre par membre, à la première écriture, sans migration de données ni
 * fenêtre où plus personne n'est restreint.
 *
 * L'ABSENCE DE LIGNE NE VEUT PAS DIRE « AUCUN ACCÈS ». C'est la distinction qui compte : un membre
 * sans restriction déclarée voit tout, comme aujourd'hui. La restriction est une décision positive,
 * pas un défaut — l'inverse aurait vidé les écrans de toutes les entreprises existantes au premier
 * déploiement.
 */
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

        /*
         * LE REPLI NE DÉPEND PAS DE L'APPARTENANCE, et c'est une correction qu'il a fallu faire
         * après coup : rendre `null` faute de ligne de membre supprimait la restriction des comptes
         * dont la portée était portée par le JSON seul. Deux tests l'ont dit tout de suite — un
         * utilisateur restreint devenait libre de tout voir.
         *
         * La ligne de membre n'est nécessaire qu'à la lecture de la TABLE. L'ancien emplacement, lui,
         * vit sur l'utilisateur et se lit sans elle.
         */
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

        /*
         * L'ANCIEN EMPLACEMENT EST TENU À JOUR TANT QU'IL EST LU. `ClientFinanceDocumentScope` et
         * `EnterpriseRoutingService` consultent encore `site_scope` pour décider s'il FAUT
         * restreindre : le laisser en arrière ferait ignorer la restriction qu'on vient de poser.
         */
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
