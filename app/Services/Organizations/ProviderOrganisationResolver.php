<?php

namespace App\Services\Organizations;

use Illuminate\Support\Facades\DB;

/**
 * LA SOCIÉTÉ PRESTATAIRE D'UN COMPTE — une seule lecture, un seul endroit.
 *
 * POURQUOI CE SERVICE EXISTE. La société d'un SALARIÉ vit sur `provider_profiles
 * .organization_account_id`, et non sur `users.organization_account_id` — cette dernière porte
 * l'organisation CLIENTE d'un compte. Le dépôt s'est déjà trompé de colonne au moins une fois :
 * `routes/channels.php` autorise `presence-org.{orgId}` sur `users.organization_account_id`, si
 * bien qu'un salarié n'entre pas dans le canal de présence de sa propre société.
 *
 * Tous les chemins d'écriture de `missions.provider_organization_id` passent par ici. Deux
 * lectures concurrentes de la même vérité finiraient par diverger — ce dépôt en a la
 * démonstration : l'inscription web posait `company_worker` là où l'API posait `company`, et une
 * seule des deux écritures était reconnue en lecture.
 */
class ProviderOrganisationResolver
{
    /**
     * L'identifiant de la société prestataire du compte, ou `null`.
     *
     * `null` est une réponse, pas un échec : un prestataire indépendant n'a pas de société, et lui
     * en inventer une ferait apparaître ses missions dans le dispatch de quelqu'un d'autre.
     *
     * Requête directe plutôt que modèle : ce résolveur est appelé depuis un observateur, sur le
     * chemin d'écriture d'un rendez-vous. Charger un modèle Eloquent complet — avec ses casts, ses
     * accesseurs et ses relations — pour lire une clé étrangère coûterait sans rien apporter.
     */
    public function pourUtilisateur(?int $userId): ?int
    {
        if ($userId === null) {
            return null;
        }

        $organisation = DB::table('provider_profiles')
            ->where('user_id', $userId)
            ->value('organization_account_id');

        return $organisation === null ? null : (int) $organisation;
    }
}
