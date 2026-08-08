<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UNE ÉQUIPE CRÉÉE DANS L'ESPACE SOCIÉTÉ NE POUVAIT PAS RECEVOIR DE MISSION.
 *
 * Trois notions d'équipe coexistent dans ce dépôt :
 *
 *   - `provider_teams` — cible de la FK `missions.provider_team_id`, sans aucun modèle Eloquent,
 *     alimentée par les seuls seeders ;
 *   - `field_teams` / `field_team_members` — modèles complets, créées par l'espace société via
 *     `FieldTeams.php`, mais JAMAIS référencées par `missions` ;
 *   - un vestige Jetstream `teams`, hors sujet.
 *
 * Une société qui créait son « Équipe Nord » sur son propre écran ne pouvait donc rien lui confier :
 * la colonne qui relie une mission à une équipe pointe ailleurs. C'est cette rupture-là que la
 * colonne ci-dessous referme.
 *
 * ON NE REPOINTE PAS `provider_team_id`, ET C'EST DÉLIBÉRÉ. Changer la cible d'une clé étrangère
 * existante est destructif : les lignes semées la référencent, et une migration qui les casserait
 * en production pour réparer un modèle de données serait exactement ce que ce chantier s'interdit.
 * `provider_teams` est GELÉE — aucun nouveau lecteur, aucun nouvel écrivain — et `field_team_id`
 * devient la notion canonique. Les deux colonnes cohabitent, l'ancienne ne reçoit plus que ce que
 * le rendez-vous portait déjà.
 *
 * `reassigned_by` / `reassignment_reason` : `mission_assignments` savait dire qu'une ligne avait été
 * `reassigned`, jamais PAR QUI ni pourquoi. Un intervenant retiré de la mission de demain
 * découvrait le changement sans interlocuteur, et une réclamation client se réglait sans trace de
 * la décision.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('missions') && ! Schema::hasColumn('missions', 'field_team_id')) {
            Schema::table('missions', function (Blueprint $table) {
                /*
                 * Nullable et SANS contrainte de clé étrangère déclarée ici.
                 *
                 * SQLite ne sait pas ajouter une FK à une table existante par `ALTER TABLE` : la
                 * migration passerait sous MySQL et casserait la suite de tests. Le scoping est de
                 * toute façon applicatif — `assignerEquipe()` refuse une équipe d'une autre société,
                 * ce qu'une FK ne saurait pas exprimer.
                 */
                $table->unsignedBigInteger('field_team_id')->nullable()->after('provider_team_id');

                // Nom EXPLICITE et court : MySQL plafonne les identifiants à 64 caractères, limite
                // que SQLite ignore. La requête chaude est « les missions de cette équipe ».
                $table->index('field_team_id', 'missions_field_team_idx');
            });
        }

        if (! Schema::hasTable('mission_assignments')) {
            return;
        }

        Schema::table('mission_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_assignments', 'reassigned_by')) {
                $table->unsignedBigInteger('reassigned_by')->nullable();
            }

            if (! Schema::hasColumn('mission_assignments', 'reassignment_reason')) {
                $table->string('reassignment_reason', 255)->nullable();
            }
        });
    }

    /**
     * `down()` NE FAIT RIEN, et c'est la règle de ce chantier.
     *
     * Retirer une colonne détruit ce qu'elle contient. Une migration inverse qui perd la trace de
     * qui a réassigné quoi n'est pas un retour en arrière, c'est une perte de données déguisée en
     * sécurité.
     */
    public function down(): void
    {
        // Volontairement vide : migrations non destructives uniquement.
    }
};
