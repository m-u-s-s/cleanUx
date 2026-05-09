<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11 — Colonnes presence pour ProviderProfile.
 *
 * Ajoute le système "online/offline" inspiré d'Uber :
 *   - is_online : le prestataire signale qu'il est dispo MAINTENANT
 *   - went_online_at : depuis quand il est en ligne (pour stats)
 *   - last_heartbeat_at : dernier ping de l'app (auto-offline si trop vieux)
 *   - presence_meta : JSON libre (battery_level, accuracy, etc.)
 *
 * Approche défensive : Schema::hasColumn pour idempotence.
 * down() retire les colonnes pour permettre rollback propre.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('provider_profiles')) {
            return;
        }

        Schema::table('provider_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('provider_profiles', 'is_online')) {
                $table->boolean('is_online')->default(false)->after('verification_status');
            }
            if (! Schema::hasColumn('provider_profiles', 'went_online_at')) {
                $table->timestamp('went_online_at')->nullable()->after('is_online');
            }
            if (! Schema::hasColumn('provider_profiles', 'went_offline_at')) {
                $table->timestamp('went_offline_at')->nullable()->after('went_online_at');
            }
            if (! Schema::hasColumn('provider_profiles', 'last_heartbeat_at')) {
                $table->timestamp('last_heartbeat_at')->nullable()->after('went_offline_at');
            }
            if (! Schema::hasColumn('provider_profiles', 'presence_meta')) {
                $table->json('presence_meta')->nullable()->after('last_heartbeat_at');
            }
        });

        // Index pour les requêtes "trouve les prestataires online proches"
        // dropIfExists d'abord pour idempotence
        Schema::table('provider_profiles', function (Blueprint $table) {
            try {
                $table->index(['is_online', 'last_heartbeat_at'], 'provider_profiles_online_heartbeat_idx');
            } catch (\Throwable $e) {
                // Index existe déjà
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('provider_profiles')) {
            return;
        }

        Schema::table('provider_profiles', function (Blueprint $table) {
            try {
                $table->dropIndex('provider_profiles_online_heartbeat_idx');
            } catch (\Throwable $e) {
                // Index n'existe pas
            }

            $columns = [
                'is_online',
                'went_online_at',
                'went_offline_at',
                'last_heartbeat_at',
                'presence_meta',
            ];

            foreach ($columns as $col) {
                if (Schema::hasColumn('provider_profiles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
