<?php

namespace App\Support\Organizations;

use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\User;

/**
 * QUELLE ORGANISATION SERT UNE REQUÊTE D'ESPACE SOCIÉTÉ — UNE SEULE RÈGLE, POUR LES DEUX CÔTÉS.
 *
 * POURQUOI CE FICHIER EXISTE. Les deux contrôleurs société lisaient `$user->currentOrganization`,
 * c'est-à-dire la seule colonne `current_organization_id`. Or `db:seed` ne la renseigne jamais :
 * `DemoPlatformSeeder` rattache le contact entreprise par `organization_account_id` et s'arrête là.
 * Mesuré sur une base fraîchement semée :
 *
 *     facilities@atlasfacilities.test | org_account_id=1 | current_org_id=NULL
 *
 * Conséquence : les onze écrans société répondaient 403 à tout compte de démonstration, portes
 * ouvertes comprises. Un écran d'erreur derrière un bouton visible est le même défaut qu'un bouton
 * absent — il coûte juste un clic de plus à découvrir.
 *
 * LA RÉSOLUTION passe par `User::organizationContextId()`, qui existait déjà et que
 * `ClientContractsCenter` utilise depuis longtemps : `organization_account_id`, puis
 * `current_organization_id`, puis deux emplacements de `metadata`. Ajouter une n-ième variante
 * aurait fait diverger les surfaces une fois de plus.
 *
 * L'APPARTENANCE EST VÉRIFIÉE, et c'est un durcissement, pas un maintien. La garde précédente
 * faisait CONFIANCE à la colonne : quel que soit son contenu, l'organisation était servie sans
 * qu'aucune ligne de `organization_members` soit consultée. Ici, un identifiant qui ne correspond
 * à aucune adhésion ACTIVE ne donne rien — y compris s'il venait de `metadata`, que le repli lit
 * et qu'aucun point d'API n'écrit aujourd'hui, mais dont on ne veut pas dépendre.
 */
trait ResolvesActiveOrganization
{
    /**
     * L'organisation de l'appelant, ou 403.
     *
     * Le 403 est délibérément indistinct entre « aucun contexte » et « pas membre » : la différence
     * dirait à un appelant si une organisation existe.
     */
    protected function organisationActive(): OrganizationAccount
    {
        /** @var User|null $user */
        $user = auth()->user();

        $organisationId = $user?->organizationContextId();

        abort_if($user === null || $organisationId === null, 403);

        $estMembreActif = OrganizationMember::query()
            ->where('organization_account_id', $organisationId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->exists();

        abort_unless($estMembreActif, 403);

        $organisation = OrganizationAccount::query()->find($organisationId);

        abort_if($organisation === null, 403);

        return $organisation;
    }
}
