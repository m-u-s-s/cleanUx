<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE RÉFÉRENT D'UNE SOCIÉTÉ PRESTATAIRE SUR UN SITE CLIENT.
 *
 * Une société de nettoyage qui dessert vingt immeubles y place des habitués : celui qui connaît le
 * code de la porte, l'ascenseur en panne et l'étage à ne pas déranger avant 10 h. Cette
 * connaissance n'existait nulle part — chaque mission repartait d'une page blanche, et le
 * répartiteur choisissait de mémoire.
 *
 * POURQUOI UNE TABLE ET PAS LE PIVOT EXISTANT. `organization_member_site_access` relie un site aux
 * membres de l'organisation QUI POSSÈDE ce site — un facility manager et ses locaux. Ici, la
 * relation va dans l'autre sens : une organisation PRESTATAIRE désigne l'un des siens sur le site
 * d'un CLIENT. Les deux ne partagent ni les colonnes, ni les gardes, ni le sens ; les confondre
 * aurait donné à un employé du prestataire un droit sur les locaux du client.
 *
 * `role` distingue le titulaire du remplaçant : `lead` est celui qu'on suggère, `backup` celui
 * qu'on suggère quand le premier est déjà pris.
 */
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

            /*
             * Noms d'index EXPLICITES et courts : MySQL plafonne les identifiants à 64 caractères,
             * limite que SQLite ignore — la migration passerait la suite de tests et casserait en
             * production. Le nom auto-généré ici
             * (`provider_site_assignments_provider_organization_id_organization_site_id_user_id_unique`)
             * ferait 86 caractères.
             */
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
