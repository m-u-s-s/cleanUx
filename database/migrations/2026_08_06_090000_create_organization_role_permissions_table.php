<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MATRICE RÔLE → PERMISSIONS, RÉGLABLE PAR SOCIÉTÉ.
 *
 * `PermissionService::ROLE_PERMISSIONS` est une constante PRIVÉE : la correspondance était figée
 * dans le code. Un propriétaire pouvait accorder un droit à une personne précise (surcharge JSON
 * sur le membre), mais pas décider que « chez nous, les chefs d'équipe assignent les missions » —
 * cela demandait un déploiement.
 *
 * Cette table insère un étage intermédiaire, propre à l'organisation. Sans réglage, aucune ligne :
 * la résolution retombe sur le défaut du code et le comportement reste identique.
 *
 * `granted` est un booléen explicite, et non une simple présence : une société doit pouvoir
 * RETIRER un droit accordé par défaut autant qu'en ajouter un.
 */
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

            /*
             * Noms d'index EXPLICITES et courts : MySQL plafonne les identifiants à 64 caractères,
             * limite que SQLite ignore. Le nom auto-généré ici
             * (`organization_role_permissions_organization_account_id_role_permission_unique`)
             * ferait 76 caractères et casserait la migration en production — exactement le défaut
             * corrigé sur `signing_appointments` dans la phase précédente.
             */
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
