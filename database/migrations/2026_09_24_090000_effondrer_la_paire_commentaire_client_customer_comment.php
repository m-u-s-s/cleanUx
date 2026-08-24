<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** `commentaire_client` et `customer_comment` disaient la même chose. Seul l'anglais survit. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'commentaire_client')) {
            return;
        }

        // La base locale n'a aucun trou, une autre en aura : on comble AVANT de supprimer.
        DB::table('bookings')
            ->whereNull('customer_comment')
            ->whereNotNull('commentaire_client')
            ->update(['customer_comment' => DB::raw('commentaire_client')]);

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn('commentaire_client');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'commentaire_client')) {
            return;
        }

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('commentaire_client')->nullable()->after('customer_comment');
        });

        DB::table('bookings')
            ->whereNotNull('customer_comment')
            ->update(['commentaire_client' => DB::raw('customer_comment')]);
    }
};
