<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Les libellés du catalogue dans les autres langues. */
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
