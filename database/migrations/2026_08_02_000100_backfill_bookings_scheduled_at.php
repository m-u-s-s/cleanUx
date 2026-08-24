<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Reconstitue `scheduled_at` sur les réservations existantes. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'scheduled_at')) {
            return;
        }

        DB::table('bookings')
            ->whereNull('scheduled_at')
            ->whereNotNull('date')
            ->whereNotNull('heure')
            ->orderBy('id')
            // Par lots : la table peut être volumineuse, et un UPDATE global la verrouillerait
            // le temps du traitement.
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $timestamp = strtotime(substr((string) $row->date, 0, 10).' '.substr((string) $row->heure, 0, 8));

                    if ($timestamp === false) {
                        continue;
                    }

                    DB::table('bookings')
                        ->where('id', $row->id)
                        ->update(['scheduled_at' => date('Y-m-d H:i:s', $timestamp)]);
                }
            });
    }

    /** Aucun retour en arrière. */
    public function down(): void {}
};
