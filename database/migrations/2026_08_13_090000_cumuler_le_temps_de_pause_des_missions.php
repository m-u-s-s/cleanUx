<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE CHRONOMÈTRE DE MISSION SAVAIT S'ARRÊTER, PAS COMPTER (F4).
 *
 * `trip_tracking_sessions` portait déjà `is_paused` et `paused_at` : on savait donc qu'une pause
 * était EN COURS, et depuis quand. Mais `resumeSession()` se contentait de baisser le drapeau —
 * sans jamais cumuler la durée écoulée, et sans même effacer `paused_at`, qui continuait ensuite de
 * se lire comme « en pause depuis ce matin ».
 *
 * CONSÉQUENCE : LE TEMPS TRAVAILLÉ N'ÉTAIT PAS CALCULABLE. Entre l'entrée en mission et la clôture,
 * il y a le travail ET les pauses ; sans le second terme, la seule durée disponible est la durée de
 * présence. Sur une intervention de quatre heures dont une de déjeuner, cela fait une heure
 * facturée ou payée en trop — et c'est exactement la donnée dont les feuilles d'heures (E20) et la
 * rentabilité (E22) ont besoin.
 *
 * UNE SEULE COLONNE, ADDITIVE, EN SECONDES. Un cumul plutôt qu'un journal de pauses : ce qu'on veut
 * savoir, c'est combien de temps a été travaillé, pas à quelle heure chacun a pris son café. Le
 * journal existe déjà ailleurs, dans l'historique de mission, pour qui veut le détail.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trip_tracking_sessions')) {
            return;
        }

        Schema::table('trip_tracking_sessions', function (Blueprint $table) {
            if (! Schema::hasColumn('trip_tracking_sessions', 'paused_total_seconds')) {
                // Défaut à zéro et non nul : « aucune pause » est une valeur, pas une absence de
                // valeur. Un nul obligerait chaque lecteur à décider quoi en faire, et l'un d'eux
                // se tromperait.
                $table->unsignedInteger('paused_total_seconds')->default(0)->after('paused_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('trip_tracking_sessions')) {
            return;
        }

        Schema::table('trip_tracking_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('trip_tracking_sessions', 'paused_total_seconds')) {
                $table->dropColumn('paused_total_seconds');
            }
        });
    }
};
