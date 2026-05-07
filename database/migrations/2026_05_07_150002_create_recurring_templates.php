<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6.1 — Templates pré-définis pour création de récurrences en 1 clic.
 *
 * Différence avec recurring_booking_series (Phase 6) :
 *   - recurring_booking_series : INSTANCES actives (1 série = X bookings générés)
 *   - recurring_templates       : MODÈLES réutilisables ("Hebdo bureaux 5j",
 *                                 "Bi-mensuel commerces", etc.)
 *
 * Workflow :
 *   1. User choisit un template ("Hebdo bureaux 5j") dans la galerie
 *   2. Pré-remplit le flow PrendreRendezVous avec la config du template
 *   3. User valide (peut-être ajuster) → crée une RecurringBookingSeries
 *
 * Templates système : is_system=true, créés par seeder (catalogue commun).
 * Templates user : is_system=false, owner_user_id ou owner_organization_id.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('recurring_templates')) {
            return;
        }

        Schema::create('recurring_templates', function (Blueprint $table) {
            $table->id();

            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();

            // Catégorie pour grouper dans la galerie : office, retail, hospitality, residential
            $table->string('category', 32)->nullable();

            // Icône emoji ou lucide name (pour la card)
            $table->string('icon', 32)->nullable();

            // Owner : null = template système ; user_id/org_id = template perso
            $table->boolean('is_system')->default(false);
            $table->foreignId('owner_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('owner_organization_id')
                ->nullable()
                ->constrained('organization_accounts')
                ->nullOnDelete();

            // Service par défaut (optionnel — peut être laissé à choix)
            $table->foreignId('default_service_catalog_id')
                ->nullable()
                ->constrained('service_catalogs')
                ->nullOnDelete();

            // Configuration récurrence (mêmes clés que recurring_booking_series)
            $table->string('frequency', 16);              // daily, weekly, monthly
            $table->unsignedSmallInteger('interval')->default(1);
            $table->json('days')->nullable();             // ['monday','thursday'] pour weekly
            $table->time('default_time')->nullable();     // ex: 08:00
            $table->unsignedInteger('default_duration_minutes')->nullable();

            // Métadonnées additionnelles (libre)
            $table->json('payload')->nullable();

            // Compteur d'usage (pour trier "le plus utilisé")
            $table->unsignedInteger('usage_count')->default(0);

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('display_order')->default(100);

            $table->timestamps();

            $table->index(['is_system', 'is_active']);
            $table->index(['category', 'display_order']);
            $table->index('owner_user_id');
            $table->index('owner_organization_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_templates');
    }
};
