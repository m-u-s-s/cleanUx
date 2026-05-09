<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 14 — État surge par zone (mis à jour périodiquement par job).
 *
 * Pattern Uber : la pression temporaire d'une zone est résumée par un
 * `multiplier` (1.0 = neutre, 1.5 = +50%, 2.0 = ×2). Recalculé toutes les
 * 30s/1min par RecomputeSurgeJob qui regarde demand/supply en temps réel.
 *
 * Cap réglementaire (BE/FR) : 3.0 max — sinon risque "prix abusif".
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('pricing_zones_state')) {
            return;
        }

        Schema::create('pricing_zones_state', function (Blueprint $table) {
            $table->id();

            $table->foreignId('service_zone_id')
                ->constrained('service_zones')
                ->cascadeOnDelete();

            $table->decimal('multiplier', 4, 2)->default(1.00);

            // Décomposition pour debug + UI ("pourquoi je paie +50% ?")
            $table->decimal('demand_factor', 4, 2)->default(1.00);
            $table->decimal('supply_factor', 4, 2)->default(1.00);
            $table->decimal('temporal_factor', 4, 2)->default(1.00);

            // Stats brutes au moment du calcul
            $table->unsignedInteger('open_bookings_count')->default(0);
            $table->unsignedInteger('online_providers_count')->default(0);

            // Décay : à partir de quand le surge expire si pas re-calculé
            $table->timestamp('expires_at')->nullable();

            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('service_zone_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_zones_state');
    }
};
