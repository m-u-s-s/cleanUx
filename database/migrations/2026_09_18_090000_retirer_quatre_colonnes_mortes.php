<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QUATRE COLONNES DE `bookings` QUE PLUS RIEN N'ATTEIGNAIT.
 *
 * ── COMMENT ON LES A TROUVÉES ────────────────────────────────────────────────────────────────
 *
 * `bookings` porte 168 colonnes, dont 150 nullables — 89 %. Sur une table pareille, l'œil ne suffit
 * pas : on a balayé chaque colonne contre TOUT le code hors migrations (`app`, `routes`, `config`,
 * les vues, les seeders, les fabriques, les tests), avec un critère volontairement PERMISSIF —
 * une simple sous-chaîne suffit à sauver une colonne. Ce qu'un tel critère déclare mort l'est.
 *
 * Le même balayage sur `missions` (61 colonnes) et `users` (55) n'a rien rendu : leur sparsité est
 * légitime. Le problème est propre à `bookings`.
 *
 * ── LES QUATRE ───────────────────────────────────────────────────────────────────────────────
 *
 *   `is_urgent`         Une TROISIÈME représentation de l'urgence, à côté de `priorite` et
 *                       `priority`. Écrite par sa seule migration d'origine — c'est-à-dire jamais.
 *                       Les trois lignes de la base portent son défaut `0`, y compris celles que la
 *                       plateforme tient pour urgentes. La notion vit dans `priorite`, corrigée au
 *                       lot précédent.
 *   `occurrence_index`  Aucune référence nulle part, aucune ligne renseignée. Le rang d'une
 *                       occurrence dans une série se lit ailleurs.
 *   `service_snapshot`  Aucune référence, aucune ligne. Elle n'est même pas dans `$fillable` du
 *                       modèle : toute écriture y aurait été écartée en silence, ce qui explique
 *                       qu'elle soit restée vide sans que personne s'en aperçoive.
 *   `areas`             Le seul cas d'écriture SEULE : `DemoPlatformSeeder:574` y pose `['demo']`,
 *                       et rien au monde ne la relit. Le modèle la déclare (`@property`,
 *                       `$fillable`, cast `array`) — une colonne peut être parfaitement câblée et
 *                       ne servir à rien.
 *
 * ── CE QUI ACCOMPAGNE CETTE MIGRATION ────────────────────────────────────────────────────────
 *
 * `areas` disparaît aussi du modèle `Booking` (docblock, `$fillable`, cast) et du seeder. Retirer
 * la colonne sans retirer sa déclaration ferait échouer le seeder à la première exécution — la
 * colonne n'existerait plus quand MySQL recevrait l'INSERT.
 */
return new class extends Migration
{
    private array $colonnes = ['is_urgent', 'occurrence_index', 'service_snapshot', 'areas'];

    public function up(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        $aRetirer = array_values(array_filter(
            $this->colonnes,
            fn (string $c) => Schema::hasColumn('bookings', $c)
        ));

        if ($aRetirer === []) {
            return;
        }

        Schema::table('bookings', fn (Blueprint $t) => $t->dropColumn($aRetirer));
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $t) {
            if (! Schema::hasColumn('bookings', 'is_urgent')) {
                $t->boolean('is_urgent')->default(false);
            }
            if (! Schema::hasColumn('bookings', 'occurrence_index')) {
                $t->unsignedInteger('occurrence_index')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'service_snapshot')) {
                $t->json('service_snapshot')->nullable();
            }
            if (! Schema::hasColumn('bookings', 'areas')) {
                $t->json('areas')->nullable();
            }
        });
    }
};
