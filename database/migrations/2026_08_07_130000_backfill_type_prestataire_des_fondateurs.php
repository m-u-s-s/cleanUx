<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * LES FONDATEURS PORTAIENT UN TYPE QUE RIEN NE RECONNAÎT.
 *
 * L'inscription d'une société prestataire écrivait `provider_profiles.provider_type = 'company'`.
 * Cette valeur n'était LUE nulle part dans `app/` — une seule écriture, aucune lecture. Pendant ce
 * temps, deux vérifications décident de l'accès et testent `company_worker` :
 * `isProviderCompanyWorker()`, qui garde le tableau de bord société, et `isEmploye()`, dont
 * dépendent les routes `role:employe`.
 *
 * Résultat : le fondateur était refusé sur l'espace de sa propre société, alors que chaque employé
 * qu'il invitait ensuite recevait `company_worker` de `OrganizationMembershipService`. Le patron
 * était le seul membre du mauvais type.
 *
 * L'inscription est corrigée ; ce remplissage rattrape les comptes déjà créés.
 *
 * NON DESTRUCTIVE, et strictement bornée : on ne convertit QUE les profils rattachés à une
 * organisation de type `provider_company`. Un profil `company` sans organisation resterait
 * ambigu — on n'y touche pas plutôt que de deviner.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('provider_profiles') || ! Schema::hasTable('organization_accounts')) {
            return;
        }

        if (! Schema::hasColumn('provider_profiles', 'provider_type')) {
            return;
        }

        $societesPrestataires = DB::table('organization_accounts')
            ->where('type', 'provider_company')
            ->pluck('id');

        if ($societesPrestataires->isEmpty()) {
            return;
        }

        DB::table('provider_profiles')
            ->where('provider_type', 'company')
            ->whereIn('organization_account_id', $societesPrestataires)
            ->update(['provider_type' => 'company_worker']);
    }

    public function down(): void
    {
        /*
         * PAS DE RETOUR EN ARRIÈRE.
         *
         * Rétablir `company` remettrait ces comptes dans l'état où ils ne pouvaient pas ouvrir leur
         * propre espace, et rien en base ne distingue les profils convertis ici de ceux qu'un
         * employé a légitimement reçus. Une réversibilité qui recasse ce qu'elle prétend restaurer
         * n'en est pas une.
         */
    }
};
