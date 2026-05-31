<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SP1 socle prestataire : garantit la présence des colonnes de traçabilité
 * société/équipe sur bookings + missions, indépendamment de la chaîne de
 * migrations en attente. Idempotent (gardes hasColumn).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            if (! Schema::hasColumn('bookings', 'assigned_provider_organization_id')) {
                if (Schema::hasColumn('bookings', 'assigned_provider_user_id')) {
                    $table->foreignId('assigned_provider_organization_id')->nullable()->after('assigned_provider_user_id');
                } else {
                    $table->foreignId('assigned_provider_organization_id')->nullable();
                }
            }
            if (! Schema::hasColumn('bookings', 'provider_team_id')) {
                if (Schema::hasColumn('bookings', 'assigned_provider_organization_id')) {
                    $table->unsignedBigInteger('provider_team_id')->nullable()->after('assigned_provider_organization_id');
                } else {
                    $table->unsignedBigInteger('provider_team_id')->nullable();
                }
            }
        });

        Schema::table('missions', function (Blueprint $table) {
            if (! Schema::hasColumn('missions', 'provider_organization_id')) {
                if (Schema::hasColumn('missions', 'organization_account_id')) {
                    $table->foreignId('provider_organization_id')->nullable()->after('organization_account_id');
                } else {
                    $table->foreignId('provider_organization_id')->nullable();
                }
            }
            if (! Schema::hasColumn('missions', 'provider_team_id')) {
                if (Schema::hasColumn('missions', 'provider_organization_id')) {
                    $table->unsignedBigInteger('provider_team_id')->nullable()->after('provider_organization_id');
                } else {
                    $table->unsignedBigInteger('provider_team_id')->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            foreach (['assigned_provider_organization_id', 'provider_team_id'] as $col) {
                if (Schema::hasColumn('bookings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('missions', function (Blueprint $table) {
            foreach (['provider_organization_id', 'provider_team_id'] as $col) {
                if (Schema::hasColumn('missions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
