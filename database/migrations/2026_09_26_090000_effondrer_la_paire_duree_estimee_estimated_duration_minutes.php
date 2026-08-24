<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** `duree_estimee` et `estimated_duration_minutes` disaient la même chose. Seul l'anglais survit. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'duree_estimee')) {
            return;
        }

        // La base locale n'a aucun trou, une autre en aura : on comble AVANT de supprimer.
        DB::table('bookings')
            ->whereNull('estimated_duration_minutes')
            ->whereNotNull('duree_estimee')
            ->update(['estimated_duration_minutes' => DB::raw('duree_estimee')]);

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('duree_estimee');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'duree_estimee')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('duree_estimee')->nullable()->after('estimated_duration_minutes');
        });

        DB::table('bookings')
            ->whereNotNull('estimated_duration_minutes')
            ->update(['duree_estimee' => DB::raw('estimated_duration_minutes')]);
    }
};
