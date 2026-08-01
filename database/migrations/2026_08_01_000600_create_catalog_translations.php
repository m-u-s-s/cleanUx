<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les libellés du catalogue dans les autres langues.
 *
 * Une table plutôt que des colonnes `label_nl`, `label_en` : le jour où une langue s'ajoute, on
 * insère des lignes au lieu de migrer six tables. Et une question sans traduction n'a tout
 * simplement pas de ligne — elle retombe sur son libellé de base, ce qu'une colonne vide ne
 * distingue pas d'une traduction volontairement identique.
 *
 * POLYMORPHE : questions, options, étapes et métiers partagent la même mécanique. Trois tables de
 * traduction séparées finiraient par diverger, et le premier écran qui les mélange redécouvrirait
 * trois conventions différentes.
 *
 * L'unicité (objet, langue, champ) empêche deux traductions concurrentes du même libellé — sans
 * elle, l'affichage dépendrait de l'ordre d'insertion, c'est-à-dire du hasard.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('catalog_translations')) {
            return;
        }

        Schema::create('catalog_translations', function (Blueprint $table) {
            $table->id();

            $table->string('translatable_type');
            $table->unsignedBigInteger('translatable_id');

            // Code court (fr, nl, en) : la liste vit dans config/i18n.php, pas ici. Figer les
            // langues dans un ENUM obligerait à migrer pour en ajouter une.
            $table->string('locale', 8);

            // Le champ traduit — « label », « help_text », « description »…
            $table->string('field', 40);

            $table->text('value');

            $table->timestamps();

            $table->unique(
                ['translatable_type', 'translatable_id', 'locale', 'field'],
                'catalog_translation_unique',
            );
            $table->index(['translatable_type', 'translatable_id', 'locale'], 'catalog_translation_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_translations');
    }
};
