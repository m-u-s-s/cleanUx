<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POURQUOI CETTE PERSONNE-LÀ, ET PAS UNE AUTRE ?
 *
 * Le bouton « assignation automatique » distribue le travail d'une société sans que personne ne
 * regarde. Sans trace, la première question d'un gérant — « pourquoi Karim et pas Nadia ? » — n'a
 * aucune réponse, et la seule issue est de désactiver la fonction. Un moteur qu'on ne peut pas
 * expliquer ne se fait pas confiance.
 *
 * Table calquée sur `booking_matching_decisions`, qui joue ce rôle pour le matching MARKETPLACE.
 * Les deux ne se confondent pas : celui-ci répartit entre SALARIÉS d'une même société, l'autre
 * choisit un prestataire sur la place de marché. Mêmes besoins d'audit, domaines distincts.
 *
 * `candidates` porte le détail du calcul, pas seulement le gagnant : c'est la différence entre
 * « le moteur a choisi Karim » et « voici les cinq personnes libres, leurs scores et le pourquoi de
 * chaque point ». La seconde forme permet de contester le réglage ; la première ne permet que de
 * douter du logiciel.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('internal_assignment_decisions')) {
            Schema::create('internal_assignment_decisions', function (Blueprint $table) {
                $table->id();

                $table->foreignId('mission_id')
                    ->constrained('missions')
                    ->cascadeOnDelete();

                $table->foreignId('provider_organization_id')
                    ->constrained('organization_accounts')
                    ->cascadeOnDelete();

                // Qui a déclenché : un humain (bouton), ou personne (mode continu).
                $table->unsignedBigInteger('triggered_by')->nullable();

                // manual | auto_button | auto_mode
                $table->string('mode', 20);

                // assigned | no_candidate | skipped_locked
                $table->string('status', 20);

                $table->unsignedBigInteger('chosen_user_id')->nullable();
                $table->integer('chosen_score')->nullable();

                /*
                 * Le détail par candidat : identifiant, score total, et la ventilation par critère.
                 * JSON parce que les critères BOUGENT — l'agence arrive au lot 6 — et qu'une colonne
                 * par critère ferait une migration à chaque réglage.
                 */
                $table->json('candidates')->nullable();

                $table->timestamps();

                /*
                 * Noms d'index EXPLICITES et courts : MySQL plafonne les identifiants à 64
                 * caractères, limite que SQLite ignore — la migration passerait la suite de tests et
                 * casserait en production.
                 *
                 * La lecture chaude est « l'historique de cette société », d'abord le plus récent.
                 */
                $table->index(['provider_organization_id', 'created_at'], 'iad_org_date_idx');
                $table->index('mission_id', 'iad_mission_idx');
            });
        }

        /*
         * LE MODE CONTINU — « toute nouvelle mission de la société est auto-assignée ».
         *
         * Colonne sur `organization_accounts` plutôt qu'une table de réglages : c'est un booléen par
         * société, lu à chaque création de mission. `default(false)` est ce qui rend la migration
         * sûre — aucune société existante ne se met à distribuer son travail toute seule du fait
         * d'un déploiement.
         */
        if (Schema::hasTable('organization_accounts')
            && ! Schema::hasColumn('organization_accounts', 'auto_assign_enabled')) {
            Schema::table('organization_accounts', function (Blueprint $table) {
                $table->boolean('auto_assign_enabled')->default(false);
            });
        }
    }

    /** `down()` volontairement vide : migrations non destructives uniquement. */
    public function down(): void
    {
        // Retirer la table effacerait l'historique des décisions — une perte de données déguisée
        // en retour en arrière.
    }
};
