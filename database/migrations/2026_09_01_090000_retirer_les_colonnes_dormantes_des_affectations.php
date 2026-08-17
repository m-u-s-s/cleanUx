<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DEUX COLONNES QUI RÉPONDAIENT À DES QUESTIONS DÉJÀ POSÉES AILLEURS.
 *
 * `mission_assignments` portait QUATRE colonnes pour DEUX notions :
 *
 *   `role`               NOT NULL, défaut « worker »    ← dormante
 *   `role_on_mission`    nullable                        ← celle que le code lit et écrit
 *   `status`             NOT NULL, défaut « assigned »   ← dormante
 *   `assignment_status`  NOT NULL, défaut « pending »    ← celle que le code lit et écrit
 *
 * CE N'EST PAS DE L'ESTHÉTIQUE, ET C'EST CE QUI JUSTIFIE LE RETRAIT. Une colonne NOT NULL avec un
 * défaut se remplit toute seule : les quatre lignes de la base de développement portent
 * `status = 'assigned'` alors qu'aucune ligne de code ne l'a jamais écrit. Elle garde donc cette
 * valeur POUR TOUJOURS, pendant que `assignment_status` traverse le vrai cycle de vie — accepté,
 * refusé, expiré, terminé.
 *
 * Une requête d'analyse, un tableau de bord, un développeur pressé qui lirait `status` verrait donc
 * toutes les offres comme éternellement en attente, y compris celles qui ont été honorées. Le
 * défaut le plus courant de ce dépôt, sous sa forme la plus discrète : deux notions pour un
 * événement, dont l'une ment sans jamais varier.
 *
 * VÉRIFIÉ AVANT DE RETIRER — aucun lecteur, aucun écrivain. Ni dans `app/`, ni dans les vues, ni
 * dans les fabriques, ni dans les semeurs. Les seules occurrences étaient les deux clés de
 * `$fillable` du modèle, retirées avec cette migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_assignments')) {
            return;
        }

        /*
         * TROIS ETAPES, ET CHAQUE MOTEUR A EXIGE LA SIENNE.
         *
         * `mission_assignments_user_id_status_index` porte `(user_id, status)`, et les deux bases
         * s'y opposent pour des raisons OPPOSEES :
         *
         *   — SQLite refuse qu'on retire `status` en laissant l'index pendre : toute operation
         *     ulterieure sur la table echoue avec « error in index … after drop column ». La suite
         *     de tests entiere tombait, alors que MySQL, lui, reecrit l'index tout seul.
         *   — MySQL refuse qu'on retire l'index : il SOUTIENT la cle etrangere de `user_id`
         *     (erreur 1553, « needed in a foreign key constraint »). SQLite, lui, s'en moque.
         *
         * D'ou l'ordre ci-dessous, qui satisfait les deux sans brancher sur le pilote : on donne
         * d'abord a la cle etrangere un index a elle, puis on retire le composite, puis la colonne.
         *
         * Le piege habituel de ce depot est RETOURNE : d'ordinaire SQLite cache ce que MySQL
         * refuse. Ici chacun a cache la moitie du probleme, et il a fallu exercer les DEUX.
         */
        Schema::table('mission_assignments', function (Blueprint $table) {
            $table->index('user_id', 'mission_assignments_user_id_index');
        });

        Schema::table('mission_assignments', function (Blueprint $table) {
            $table->dropIndex('mission_assignments_user_id_status_index');
        });

        Schema::table('mission_assignments', function (Blueprint $table) {
            foreach (['role', 'status'] as $dormante) {
                if (Schema::hasColumn('mission_assignments', $dormante)) {
                    $table->dropColumn($dormante);
                }
            }
        });
    }

    /**
     * LE RETOUR RESTAURE LES DÉFAUTS D'ORIGINE, et pas des colonnes nulles.
     *
     * Elles étaient NOT NULL : les recréer nullables ferait échouer la migration sur une table
     * peuplée, et laisserait un schéma qui n'est pas celui d'avant.
     */
    public function down(): void
    {
        if (! Schema::hasTable('mission_assignments')) {
            return;
        }

        Schema::table('mission_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_assignments', 'role')) {
                $table->string('role')->default('worker');
            }

            if (! Schema::hasColumn('mission_assignments', 'status')) {
                $table->string('status')->default('assigned');
            }
        });

        // Le schema d'avant, pas un schema approchant : l'index composite revient avec sa colonne,
        // et celui qu'on avait ajoute pour la cle etrangere s'efface.
        Schema::table('mission_assignments', function (Blueprint $table) {
            $table->index(['user_id', 'status'], 'mission_assignments_user_id_status_index');
        });

        Schema::table('mission_assignments', function (Blueprint $table) {
            $table->dropIndex('mission_assignments_user_id_index');
        });
    }
};
