<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DEUX COLONNES POUR UNE PRIORITÉ, ET UNE SEULE AVAIT UN DÉFAUT.
 *
 * ── CE QU'ON A MESURÉ ────────────────────────────────────────────────────────────────────────
 *
 * Sur les quinze paires d'alias que porte `bookings`, quatorze sont parfaitement symétriques —
 * même type, même nullabilité, même défaut. Une seule ne l'était pas :
 *
 *   `priority`  varchar(255) NOT NULL DEFAULT 'normal'
 *   `priorite`  varchar(255) NULL     DEFAULT NULL
 *
 * `HasLegacyBookingAliases::propagerLaPaire()` ne peut rien y faire : il s'exécute sur `saving`,
 * donc AVANT que MySQL applique ses défauts, et ses branches exigent qu'un côté soit renseigné.
 * Quand une réservation est créée sans priorité explicite, les deux colonnes sont vides en PHP, le
 * trait passe son chemin, puis la base remplit un seul côté. La divergence est alors définitive.
 *
 * Mesuré sur la base `brio` : DEUX lignes sur trois portaient `priorite = NULL` avec
 * `priority = 'normal'`.
 *
 * ── POURQUOI CE N'EST PAS UN CHANGEMENT DE COMPORTEMENT ──────────────────────────────────────
 *
 * Aucun code du dépôt ne distingue `priorite IS NULL` — pas un `whereNull`, pas une comparaison.
 * Les deux seuls endroits qui rencontrent le cas le traitent déjà comme « normale » :
 *
 *   app/Notifications/NouveauRendezVousNotification.php:24   `$this->rdv->priorite ?? 'normale'`
 *   resources/views/livewire/client/profil-client.blade.php:80  repli d'affichage
 *
 * Poser le défaut ne change donc pas ce que la plateforme considère comme vrai : cela le rend
 * effectif dans la colonne que tous les filtres interrogent, au lieu de le laisser à la charge de
 * chaque appelant — dont les `where()` en base, eux, ne peuvent pas appliquer de repli.
 *
 * ── LE VOCABULAIRE ───────────────────────────────────────────────────────────────────────────
 *
 * Le défaut est `'normale'` et non `'normal'` : c'est la valeur des listes de choix qui alimentent
 * les filtres. La traduction employée par le rattrapage ci-dessous est celle de
 * `HasLegacyBookingAliases::$legacyAliasValueMaps`, corrigée dans le même lot — elle y est
 * documentée avec les emplacements qui la justifient.
 */
return new class extends Migration
{
    /** Le vocabulaire de l'API vers celui des écrans. Voir le trait pour la justification. */
    private array $versLeFrancais = [
        'low' => 'basse',
        'normal' => 'normale',
        'high' => 'haute',
        'urgent' => 'urgente',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasColumn('bookings', 'priorite')) {
            return;
        }

        $this->rattraperLesLignesSansPriorite();
        $this->normaliserLeVocabulaireHistorique();

        Schema::table('bookings', function (Blueprint $t) {
            $t->string('priorite')->nullable()->default('normale')->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bookings') || ! Schema::hasColumn('bookings', 'priorite')) {
            return;
        }

        // On ne défait que le défaut. Les lignes rattrapées gardent leur valeur : on ne sait plus
        // lesquelles étaient nulles, et les remettre à NULL recréerait sciemment la divergence.
        Schema::table('bookings', function (Blueprint $t) {
            $t->string('priorite')->nullable()->default(null)->change();
        });
    }

    /**
     * LE RÉSIDU HISTORIQUE : DES MOTS ANGLAIS DANS LA COLONNE FRANÇAISE, ET L'INVERSE.
     *
     * Tant que le trait recopiait sans traduire, chaque écriture déposait le vocabulaire de l'autre
     * côté. Poser le défaut ne suffit donc pas — il faut aussi rendre lisibles les lignes déjà
     * écrites, sans quoi un filtre sur `'normale'` continue de les manquer.
     *
     * Mesuré sur `brio` avant ce passage : une ligne sur trois portait `priorite = 'normal'`.
     */
    private function normaliserLeVocabulaireHistorique(): void
    {
        foreach ($this->versLeFrancais as $anglais => $francais) {
            DB::table('bookings')->where('priorite', $anglais)->update(['priorite' => $francais]);

            if (Schema::hasColumn('bookings', 'priority')) {
                DB::table('bookings')->where('priority', $francais)->update(['priority' => $anglais]);
            }
        }
    }

    /**
     * Chaque ligne sans priorité reçoit celle que la colonne jumelle porte déjà, traduite.
     *
     * On ne pose pas `'normale'` partout : si `priority` dit `'urgent'`, la réservation EST urgente
     * et l'écraser en « normale » perdrait une information vraie.
     */
    private function rattraperLesLignesSansPriorite(): void
    {
        if (! Schema::hasColumn('bookings', 'priority')) {
            DB::table('bookings')->whereNull('priorite')->update(['priorite' => 'normale']);

            return;
        }

        foreach ($this->versLeFrancais as $anglais => $francais) {
            DB::table('bookings')
                ->whereNull('priorite')
                ->where('priority', $anglais)
                ->update(['priorite' => $francais]);
        }

        // Ce qui reste ne portait rien d'exploitable des deux côtés.
        DB::table('bookings')->whereNull('priorite')->update(['priorite' => 'normale']);
    }
};
