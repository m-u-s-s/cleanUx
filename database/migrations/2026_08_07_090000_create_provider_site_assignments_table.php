<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** LE RÉFÉRENT D'UNE SOCIÉTÉ PRESTATAIRE SUR UN SITE CLIENT. */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotente : rejouable sans erreur sur une base déjà migrée.
        if (Schema::hasTable('provider_site_assignments')) {
            return;
        }

        Schema::create('provider_site_assignments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('provider_organization_id')
                ->constrained('organization_accounts')
                ->cascadeOnDelete();

            $table->foreignId('organization_site_id')
                ->constrained('organization_sites')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('role', 20)->default('lead');
            $table->text('notes')->nullable();

            $table->timestamps();

            // Noms d'index EXPLICITES et courts : MySQL plafonne les identifiants à 64 caractères, limite que SQLite ignore — la migration passerait la suite de tests et casserait en production.
            $table->unique(
                ['provider_organization_id', 'organization_site_id', 'user_id'],
                'psa_org_site_user_unique'
            );

            // La suggestion du répartiteur part du site : c'est la requête chaude.
            $table->index(
                ['organization_site_id', 'provider_organization_id'],
                'psa_site_lookup_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_site_assignments');
    }
};
