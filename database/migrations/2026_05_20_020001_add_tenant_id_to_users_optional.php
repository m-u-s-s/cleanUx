<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPTIONAL migration — Phase 2 du module Tenancy v2.
 *
 * Ajoute une colonne `tenant_id` nullable à la table `users`. Le `tenant_id`
 * est laissé null par défaut (compatibilité backwards). Pour populater :
 *   `php artisan tenancy:backfill --tenant=main`
 *
 * Une fois la colonne en place + backfill effectué, tu peux activer le trait
 * `BelongsToTenant` sur le model `User` pour scope automatique par tenant.
 *
 * Cette migration est volontairement EXÉCUTÉE comme les autres (ordre standard)
 * — la nullabilité garantit qu'aucun code legacy ne casse, et le trait n'est
 * activé que manuellement par le développeur. Permet l'opt-in progressif.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }
        if (! Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            });
        }
    }
};
