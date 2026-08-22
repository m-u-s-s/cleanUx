<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LES AGENCES D'UNE SOCIÉTÉ PRESTATAIRE, ET L'ÉQUIPE HABITUELLE D'UN SITE.
 *
 * DEUX NOTIONS QUE RIEN NE PORTAIT. `organization_sites` désigne les locaux du CLIENT — un
 * prestataire ne possède pas les immeubles où il intervient. Ses propres implantations (le dépôt de
 * Bruxelles, l'antenne d'Anvers) n'avaient aucune existence : une société multi-villes déclarait
 * tout au même endroit, et le répartiteur n'avait aucun moyen de dire « cette mission relève du
 * dépôt Nord ».
 *
 * `provider_site_teams` répond à l'autre moitié : quelle ÉQUIPE dessert habituellement tel immeuble.
 * `provider_site_assignments` (lot précédent) nomme des PERSONNES ; une équipe entière est le cas
 * ordinaire d'un grand site, et le désigner personne par personne recommence à chaque changement
 * d'effectif.
 *
 * TOUT EST ADDITIF ET NULLABLE. Une société qui n'a qu'une implantation ne voit rien changer :
 * `provider_agency_id` reste `null`, et le moteur d'auto-assignation n'accorde alors aucun point
 * d'agence — ce qui est exactement le comportement d'avant.
 */
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

                /*
                 * Noms d'index EXPLICITES et courts : MySQL plafonne les identifiants à 64
                 * caractères, limite que SQLite ignore — la migration passerait la suite de tests
                 * et casserait en production.
                 *
                 * Le slug est unique PAR SOCIÉTÉ, pas globalement : deux prestataires peuvent
                 * légitimement appeler leur implantation « nord ».
                 */
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

                /*
                 * UNE équipe par défaut et par site, POUR NOTRE SOCIÉTÉ. Le scoping est dans la
                 * clé unique : plusieurs prestataires concurrents desservent le même immeuble, et
                 * chacun y a son équipe habituelle.
                 */
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
