<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** `type_lieu` et `place_type` disaient la même chose. Seul l'anglais survit. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'type_lieu')) {
            return;
        }

        // La base locale n'a aucun trou, une autre en aura : on comble AVANT de supprimer.
        DB::table('bookings')
            ->whereNull('place_type')
            ->whereNotNull('type_lieu')
            ->update(['place_type' => DB::raw('type_lieu')]);

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('type_lieu');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'type_lieu')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('type_lieu')->nullable()->after('place_type');
        });

        DB::table('bookings')
            ->whereNotNull('place_type')
            ->update(['type_lieu' => DB::raw('place_type')]);
    }
};
