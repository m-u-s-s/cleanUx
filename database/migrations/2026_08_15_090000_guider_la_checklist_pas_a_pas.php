<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D'UNE LISTE À UN GUIDE (F6).
 *
 * Les checklists d'inspection existent par métier, et elles se présentent aujourd'hui comme une
 * liste de cases : toutes visibles, cochables dans n'importe quel ordre. C'est parfait pour un
 * professionnel expérimenté qui vérifie qu'il n'a rien oublié — et inutilisable pour celui qui
 * débute, ou qui découvre un métier qu'il ne pratique pas tous les jours.
 *
 * DEUX COLONNES SÉPARENT LA LISTE DU GUIDE.
 *
 * `sort_order` impose une SÉQUENCE. Sur une remise en état après travaux, aspirer avant de
 * dépoussiérer les hauteurs fait le travail deux fois ; l'ordre n'est pas une préférence, c'est le
 * métier. Sans lui, l'application affiche les étapes dans l'ordre d'insertion en base — un ordre qui
 * ne veut rien dire.
 *
 * `requires_photo` dit quelles étapes demandent une PREUVE. Toutes ne la méritent pas : photographier
 * chaque geste transformerait l'intervention en séance photo. Mais l'état d'une moquette avant
 * traitement, oui — c'est la pièce qui tranche un litige trois semaines plus tard.
 *
 * LES DEUX SONT ADDITIVES ET NULLABLES. Une checklist existante sans ordre reste utilisable telle
 * quelle, dans son ordre d'insertion : basculer tout le monde en mode guidé du jour au lendemain
 * imposerait une séquence que personne n'a définie.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_checklist_items')) {
            return;
        }

        Schema::table('mission_checklist_items', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_checklist_items', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->nullable()->after('is_required');
            }

            if (! Schema::hasColumn('mission_checklist_items', 'requires_photo')) {
                $table->boolean('requires_photo')->default(false);
            }

            if (! Schema::hasColumn('mission_checklist_items', 'mission_media_id')) {
                // La photo prise pour CETTE étape. Sans ce lien, le rapport ne saurait pas laquelle
                // des vingt photos de la mission atteste laquelle des vingt étapes.
                $table->unsignedBigInteger('mission_media_id')->nullable();
            }

            if (! Schema::hasColumn('mission_checklist_items', 'guidance')) {
                // La consigne de l'étape : ce qu'on attend, en une phrase. C'est ce qui fait la
                // différence entre « Sols » et « Aspirer puis laver, produit neutre sur parquet ».
                $table->text('guidance')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mission_checklist_items')) {
            return;
        }

        Schema::table('mission_checklist_items', function (Blueprint $table) {
            foreach (['sort_order', 'requires_photo', 'mission_media_id', 'guidance'] as $colonne) {
                if (Schema::hasColumn('mission_checklist_items', $colonne)) {
                    $table->dropColumn($colonne);
                }
            }
        });
    }
};
