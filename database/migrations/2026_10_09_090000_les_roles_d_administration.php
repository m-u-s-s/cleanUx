<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DES RÔLES D'ADMINISTRATION, PARCE QUE VINGT ET UNE CASES NE SE COCHENT PAS À LA MAIN.
 *
 * Jusqu'ici chaque administrateur portait une liste plate de capacités, posée uniquement par un
 * seeder ou à la main en base : AUCUN ÉCRAN ne savait en donner ni en retirer une. Les méthodes
 * existaient dans `GestionUtilisateurs` — `editSecurity`, `saveSecurity`, `permissionOptions` —
 * mais aucune Blade ne les appelait.
 *
 * LE RÔLE EST UN LIEN, PAS UN TAMPON. Copier les capacités sur le compte au moment de
 * l'assignation ferait dériver les deux en silence : corriger un rôle six mois plus tard ne
 * corrigerait personne. La capacité se lit donc comme l'UNION du rôle et des ajouts individuels —
 * et cette union ne peut qu'ajouter, jamais retirer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_roles', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();

            // LES CAPACITÉS DU RÔLE — les mêmes clés que `User::allowedAdminPermissions()`.
            $table->json('permissions')->nullable();

            // Le périmètre par défaut du rôle : all | zone | readonly. Null = ne l'impose pas.
            $table->string('access_scope', 20)->nullable();

            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            // `nullOnDelete` ET NON `cascade` : supprimer un rôle ne doit jamais supprimer des
            // comptes. Ils retombent sur leurs seules capacités individuelles.
            $table->foreignId('admin_role_id')->nullable()->after('permissions')
                ->constrained('admin_roles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_role_id');
        });

        Schema::dropIfExists('admin_roles');
    }
};
