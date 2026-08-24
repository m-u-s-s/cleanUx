<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** `frequence` et `frequency` disaient la même chose. Seul l'anglais survit. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'frequence')) {
            return;
        }

        // La base locale n'a aucun trou, une autre en aura : on comble AVANT de supprimer.
        DB::table('bookings')
            ->whereNull('frequency')
            ->whereNotNull('frequence')
            ->update(['frequency' => DB::raw('frequence')]);

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('frequence');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'frequence')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('frequence')->nullable()->after('frequency');
        });

        DB::table('bookings')
            ->whereNotNull('frequency')
            ->update(['frequence' => DB::raw('frequency')]);
    }
};
