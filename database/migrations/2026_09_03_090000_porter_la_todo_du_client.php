<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LA LISTE DU CLIENT EST LA CHECKLIST QUI BARRE DÉJÀ LA CLÔTURE.
 *
 * Ce dépôt porte déjà TROIS checklists — celle de la mission, celle de l'inspection qualité, et un
 * tableau JSON sur la réservation. Une seule barre la porte :
 * `MissionLifecycleService::assertRequiredChecklistCompleted()` interroge `mission_checklist_items`
 * et rien d'autre. En créer une quatrième pour le client aurait reproduit exactement le défaut
 * dominant de ce dépôt — deux notions sous un même nom — avec sa conséquence habituelle : un écran
 * qui affiche une liste pendant qu'une autre bloque.
 *
 * TROIS COLONNES SUFFISENT.
 *
 *   `source`             qui a posé cette tâche — `client`, `template`, `provider`. Sans elle,
 *                        impossible de distinguer une tâche écrite par le client d'une tâche
 *                        suggérée, ni de savoir laquelle il a le droit de retirer.
 *
 *   `created_by_user_id` la personne, pour l'affichage côté prestataire et pour l'audit. Le
 *                        prestataire doit savoir qu'une tâche vient du client : celle-là se
 *                        discute avec lui, une tâche générique non.
 *
 *   `locked_at`          l'instant où la liste s'est figée. Une DATE et non un booléen : elle dit
 *                        AUSSI quand, ce qu'un drapeau ne dit pas — et c'est cette date que le
 *                        support relira le jour où un client affirmera avoir ajouté à temps.
 *
 * `source` prend `template` par défaut, ce qui décrit exactement les lignes existantes : elles
 * viennent toutes du gabarit automatique.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_checklist_items')) {
            return;
        }

        Schema::table('mission_checklist_items', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_checklist_items', 'source')) {
                $table->string('source', 16)->default('template')->after('label');
            }

            if (! Schema::hasColumn('mission_checklist_items', 'created_by_user_id')) {
                $table->unsignedBigInteger('created_by_user_id')->nullable()->after('source');
            }

            if (! Schema::hasColumn('mission_checklist_items', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('completed_at');
            }
        });

        /*
         * « LES TÂCHES DU CLIENT SUR CETTE LISTE » — la seule requête nouvelle, et elle sera posée
         * à chaque ouverture de l'écran des deux côtés.
         *
         * Le nom est tenu court À DESSEIN : au-delà de 64 caractères, MySQL refuse la migration, et
         * SQLite l'accepte sans rien dire — la classe de défaut invisible à la suite de tests.
         */
        if (! $this->indexExiste('mci_liste_source_index')) {
            Schema::table('mission_checklist_items', function (Blueprint $table) {
                $table->index(['mission_checklist_id', 'source'], 'mci_liste_source_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('mission_checklist_items')) {
            return;
        }

        if ($this->indexExiste('mci_liste_source_index')) {
            Schema::table('mission_checklist_items', function (Blueprint $table) {
                $table->dropIndex('mci_liste_source_index');
            });
        }

        Schema::table('mission_checklist_items', function (Blueprint $table) {
            foreach (['source', 'created_by_user_id', 'locked_at'] as $colonne) {
                if (Schema::hasColumn('mission_checklist_items', $colonne)) {
                    $table->dropColumn($colonne);
                }
            }
        });
    }

    /**
     * Rejouer une migration ne doit pas échouer sur un index déjà posé — et `Schema` n'a pas de
     * `hasIndex()`. On interroge donc le schéma du moteur courant.
     */
    private function indexExiste(string $nom): bool
    {
        return collect(Schema::getIndexes('mission_checklist_items'))
            ->contains(fn (array $index) => ($index['name'] ?? null) === $nom);
    }
};
