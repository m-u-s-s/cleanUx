<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 9 — Préférence currency au niveau User et OrganizationAccount.
 *
 * Permet à chaque utilisateur (ou organisation cliente) de définir sa devise
 * d'affichage. Ne change PAS la devise de stockage (les bookings restent en EUR
 * ou la devise spécifiée à la création) — juste la devise d'affichage.
 *
 * Ex: user belge avec preferred_currency = USD voit ses montants convertis
 *     dans le dashboard, mais en DB c'est toujours stocké en EUR.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'preferred_currency')) {
                $table->string('preferred_currency', 3)->nullable()->after('locale');
            }
        });

        Schema::table('organization_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('organization_accounts', 'preferred_currency')) {
                $table->string('preferred_currency', 3)->default('EUR');
            }
            if (! Schema::hasColumn('organization_accounts', 'preferred_locale')) {
                $table->string('preferred_locale', 5)->default('fr');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'preferred_currency')) {
                $table->dropColumn('preferred_currency');
            }
        });

        Schema::table('organization_accounts', function (Blueprint $table) {
            foreach (['preferred_currency', 'preferred_locale'] as $col) {
                if (Schema::hasColumn('organization_accounts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
