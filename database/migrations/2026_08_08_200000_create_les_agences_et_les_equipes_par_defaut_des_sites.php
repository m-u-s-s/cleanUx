<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** LES AGENCES D'UNE SOCIÉTÉ PRESTATAIRE, ET L'ÉQUIPE HABITUELLE D'UN SITE. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('provider_agencies')) {
            Schema::create('provider_agencies', function (Blueprint $table) {
                $table->id();

                $table->foreignId('provider_organization_id')
                    ->constrained('organization_accounts')
                    ->cascadeOnDelete();

                $table->string('name');
                $table->string('slug', 120);

                $table->string('address')->nullable();
                $table->string('city', 120)->nullable();
                $table->string('postal_code', 20)->nullable();
                $table->string('country_code', 2)->nullable();

                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();

                // Une agence couvre souvent une zone de service : c'est ce qui la rend utile au
                // moteur de répartition, au-delà de l'organigramme.
                $table->unsignedBigInteger('service_zone_id')->nullable();

                $table->string('status', 20)->default('active');
                $table->timestamps();

                // Noms d'index EXPLICITES et courts : MySQL plafonne les identifiants à 64 caractères, limite que SQLite ignore — la migration passerait la suite de tests et casserait en production.
                $table->unique(['provider_organization_id', 'slug'], 'pa_org_slug_unique');
                $table->index(['provider_organization_id', 'status'], 'pa_org_status_idx');
            });
        }

        if (! Schema::hasTable('provider_site_teams')) {
            Schema::create('provider_site_teams', function (Blueprint $table) {
                $table->id();

                $table->foreignId('provider_organization_id')
                    ->constrained('organization_accounts')
                    ->cascadeOnDelete();

                $table->foreignId('organization_site_id')
                    ->constrained('organization_sites')
                    ->cascadeOnDelete();

                $table->foreignId('field_team_id')
                    ->constrained('field_teams')
                    ->cascadeOnDelete();

                $table->timestamps();

                // UNE équipe par défaut et par site, POUR NOTRE SOCIÉTÉ.
                $table->unique(['provider_organization_id', 'organization_site_id'], 'pst_org_site_unique');
            });
        }

        // L'agence d'une équipe terrain — d'où elle part.
        if (Schema::hasTable('field_teams') && ! Schema::hasColumn('field_teams', 'provider_agency_id')) {
            Schema::table('field_teams', function (Blueprint $table) {
                $table->unsignedBigInteger('provider_agency_id')->nullable();
                $table->index('provider_agency_id', 'ft_agency_idx');
            });
        }

        // L'agence de rattachement d'un membre — où il pointe.
        if (Schema::hasTable('organization_members')
            && ! Schema::hasColumn('organization_members', 'provider_agency_id')) {
            Schema::table('organization_members', function (Blueprint $table) {
                $table->unsignedBigInteger('provider_agency_id')->nullable();
                $table->index('provider_agency_id', 'om_agency_idx');
            });
        }

        // De quelle agence relève une mission — renseigné par le rattachement de qui l'exécute.
        if (Schema::hasTable('missions') && ! Schema::hasColumn('missions', 'provider_agency_id')) {
            Schema::table('missions', function (Blueprint $table) {
                $table->unsignedBigInteger('provider_agency_id')->nullable();
                $table->index('provider_agency_id', 'missions_agency_idx');
            });
        }
    }

    /** `down()` volontairement vide : migrations non destructives uniquement. */
    public function down(): void
    {
        // Retirer ces tables effacerait l'organisation que les sociétés y auront décrite.
    }
};
