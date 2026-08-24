<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** UNE MISSION, UNE RÉSERVATION, UNE CLÉ. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('missions') || ! Schema::hasColumn('missions', 'rendez_vous_id')) {
            return;
        }

        // REPORT PORTABLE, pas un `UPDATE ... INNER JOIN`.
        DB::table('missions')
            ->whereNull('booking_id')
            ->whereNotNull('rendez_vous_id')
            ->whereIn('rendez_vous_id', DB::table('bookings')->select('id'))
            ->update(['booking_id' => DB::raw('rendez_vous_id')]);

        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn('rendez_vous_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('missions') || Schema::hasColumn('missions', 'rendez_vous_id')) {
            return;
        }

        Schema::table('missions', function (Blueprint $table) {
            $table->unsignedBigInteger('rendez_vous_id')->nullable()->after('booking_id');
        });

        // Le retour arrière rend la colonne, pas l'ambiguïté : les lignes reçoivent la même valeur
        // que `booking_id`, ce qui est exact pour toutes celles que la montée a reportées.
        DB::table('missions')
            ->whereNotNull('booking_id')
            ->update(['rendez_vous_id' => DB::raw('booking_id')]);
    }
};
