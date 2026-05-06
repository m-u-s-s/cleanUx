<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — Multi-métiers.
 *
 * Crée la couche "Trade" (corps de métier) qui regroupe les ServiceCatalog existants
 * et permet d'en ajouter de nouveaux : Bâtiment, Peinture, Levage, etc.
 *
 * Cette table est créée vide ; le seed crée le Trade "Nettoyage" et y rattache
 * tous les ServiceCatalog existants (cf. ServiceCatalogTradeBackfillSeeder).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();

            $table->string('slug', 80)->unique();
            $table->string('code', 60)->unique();
            $table->string('name', 120);

            $table->string('icon', 60)->nullable();              // ex: "broom", "hammer", "paint-brush"
            $table->string('color', 16)->nullable();             // ex: "#0EA5E9" pour le badge UI
            $table->string('cover_image_path')->nullable();      // image hero pour landing métier

            $table->text('short_description')->nullable();
            $table->text('description')->nullable();

            // Drapeaux opérationnels
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_certification')->default(false); // ex: levage = oui
            $table->boolean('requires_insurance_proof')->default(false); // ex: bâtiment = RC pro
            $table->boolean('is_b2b_default')->default(true);
            $table->boolean('is_personal_default')->default(true);

            $table->unsignedInteger('sort_order')->default(0);

            $table->json('settings')->nullable();   // SEO, FAQ générale, KPIs cibles
            $table->json('metadata')->nullable();   // libre

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};
