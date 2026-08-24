<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** DEUX COLONNES POUR UNE PRIORITÉ, ET UNE SEULE AVAIT UN DÉFAUT. */
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

    /** LE RÉSIDU HISTORIQUE : DES MOTS ANGLAIS DANS LA COLONNE FRANÇAISE, ET L'INVERSE. */
    private function normaliserLeVocabulaireHistorique(): void
    {
        foreach ($this->versLeFrancais as $anglais => $francais) {
            DB::table('bookings')->where('priorite', $anglais)->update(['priorite' => $francais]);

            if (Schema::hasColumn('bookings', 'priority')) {
                DB::table('bookings')->where('priority', $francais)->update(['priority' => $anglais]);
            }
        }
    }

    /** Chaque ligne sans priorité reçoit celle que la colonne jumelle porte déjà, traduite. */
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
