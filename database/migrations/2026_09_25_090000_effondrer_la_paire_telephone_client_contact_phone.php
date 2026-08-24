<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** `telephone_client` et `contact_phone` disaient la même chose. Seul l'anglais survit. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'telephone_client')) {
            return;
        }

        // La base locale n'a aucun trou, une autre en aura : on comble AVANT de supprimer.
        DB::table('bookings')
            ->whereNull('contact_phone')
            ->whereNotNull('telephone_client')
            ->update(['contact_phone' => DB::raw('telephone_client')]);

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('telephone_client');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'telephone_client')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('telephone_client')->nullable()->after('contact_phone');
        });

        DB::table('bookings')
            ->whereNotNull('contact_phone')
            ->update(['telephone_client' => DB::raw('contact_phone')]);
    }
};
