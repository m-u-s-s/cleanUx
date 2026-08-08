<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * RATTRAPE LES FONDATEURS INSCRITS DEPUIS LE MOBILE.
 *
 * `/api/auth/register` posait `provider_type = 'company'` au fondateur d'une société prestataire,
 * là où l'inscription web pose `'company_worker'`. Or aucune lecture ne reconnaît `'company'` :
 * `isProviderCompanyWorker()` ne teste que `'company_worker'`, et `isEmploye()` en dépend.
 *
 * Ces comptes ne résolvaient donc NI en société NI en prestataire et retombaient sur le repli
 * `client_individuelle` — un patron de société de nettoyage traité en particulier, atterrissant
 * dans l'espace client, sans ses missions ni sa société, alors que son organisation existe et
 * qu'il y porte le rôle `owner`.
 *
 * LE CRITÈRE EST L'ORGANISATION, PAS LE SEUL TYPE. On ne convertit que les profils rattachés à une
 * organisation de type `provider_company` : un profil `'company'` sans organisation serait une
 * donnée d'une autre nature, et la deviner serait pire que la laisser.
 *
 * IRRÉVERSIBLE PAR CHOIX. `down()` ne remet pas `'company'` : cette valeur n'a jamais été lue par
 * personne, la restaurer ne rendrait aucun service et recréerait le défaut.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('provider_profiles') || ! Schema::hasTable('organization_accounts')) {
            return;
        }

        $rattrapes = DB::table('provider_profiles')
            ->join(
                'organization_accounts',
                'organization_accounts.id',
                '=',
                'provider_profiles.organization_account_id'
            )
            ->where('provider_profiles.provider_type', 'company')
            ->where('organization_accounts.type', 'provider_company')
            ->update(['provider_profiles.provider_type' => 'company_worker']);

        if ($rattrapes > 0) {
            // Le compte est écrit dans la sortie de migration : un rattrapage silencieux sur des
            // comptes réels ne laisse aucune trace le jour où l'on cherche pourquoi.
            echo "  {$rattrapes} fondateur(s) de société prestataire retypé(s) en company_worker.".PHP_EOL;
        }
    }

    public function down(): void
    {
        // Volontairement vide — voir l'en-tête.
    }
};
