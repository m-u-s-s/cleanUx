<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OPTIONAL migration — ex-Phase 2 du module Tenancy v2 (module SUPPRIMÉ).
 *
 * Ajoute une colonne `tenant_id` nullable à la table `users`.
 *
 * DEAD COLUMN (M7) : le module Tenancy v2 a été retiré ; le trait `BelongsToTenant`
 * et la commande `tenancy:backfill` mentionnés à l'origine n'existent pas/plus, et
 * aucun scope ne lit `users.tenant_id`. La colonne n'est plus mass-assignable
 * (retirée du $fillable de User). La suppression définitive de la colonne + index
 * est en attente de validation (voir docs/AUDIT-QUARANTINE-BATCH.md).
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
