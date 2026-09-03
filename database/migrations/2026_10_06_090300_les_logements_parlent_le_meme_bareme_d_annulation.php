<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UN SEUL VOCABULAIRE POUR LE BARÈME D'ANNULATION.
 *
 * `config/peer_rental.cancellation` ne connaît que `souple`, `moderee` et `stricte`. Le logement
 * naissait avec `flexible` — un mot que ce barème ignore. Or `fraisDAnnulation()` retient TOUT le
 * loyer quand aucun palier ne correspond : chaque séjour annulé, même trois mois à l'avance,
 * aurait retenu 100 %. Muet, et du côté de l'argent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peer_stays', function (Blueprint $table) {
            $table->string('cancellation_policy')->default('moderee')->change();
        });

        DB::table('peer_stays')
            ->whereNotIn('cancellation_policy', ['souple', 'moderee', 'stricte'])
            ->update(['cancellation_policy' => 'souple']);
    }

    public function down(): void
    {
        Schema::table('peer_stays', function (Blueprint $table) {
            $table->string('cancellation_policy')->default('flexible')->change();
        });
    }
};
