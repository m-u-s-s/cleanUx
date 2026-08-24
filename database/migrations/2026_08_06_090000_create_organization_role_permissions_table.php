<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** MATRICE RÔLE → PERMISSIONS, RÉGLABLE PAR SOCIÉTÉ. */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotente : rejouable sans erreur sur une base déjà migrée.
        if (Schema::hasTable('organization_role_permissions')) {
            return;
        }

        Schema::create('organization_role_permissions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('organization_account_id')
                ->constrained('organization_accounts')
                ->cascadeOnDelete();

            $table->string('role', 50);
            $table->string('permission', 80);
            $table->boolean('granted')->default(true);

            $table->timestamps();

            // Noms d'index EXPLICITES et courts : MySQL plafonne les identifiants à 64 caractères, limite que SQLite ignore.
            $table->unique(
                ['organization_account_id', 'role', 'permission'],
                'org_role_perm_unique'
            );

            $table->index(
                ['organization_account_id', 'role'],
                'org_role_perm_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_role_permissions');
    }
};
