<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marque les profils créés par l'inscription en libre-service depuis l'app prestataire.
 *
 * Ces comptes n'ont encore été validés par personne : ils peuvent compléter leur dossier
 * d'onboarding, rien d'autre, tant qu'un humain ne les a pas approuvés.
 *
 * Colonne dédiée plutôt qu'une déduction sur `status` : sur la base réelle, 4 profils
 * prestataires sur 9 ne sont pas `active` (pending, ou sans profil du tout). Une garde fondée
 * sur le statut mettrait donc dehors des prestataires légitimes déjà en production. Les lignes
 * antérieures laissent cette colonne nulle et ne sont jamais restreintes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->timestamp('self_registered_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('provider_profiles', function (Blueprint $table) {
            $table->dropColumn('self_registered_at');
        });
    }
};
