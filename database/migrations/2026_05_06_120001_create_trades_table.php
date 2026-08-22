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
        $this->corpsInitial();
        $this->fusion20260517120002ExtendTradesWithBusinessProperties();
        $this->fusion20260517120004AddBookingFormSchemaToTrades();
        $this->fusion20260527000000ExtendTradesWithBillingAndSiteVisit();
        $this->fusion20260728000002AddProviderFormSchemaToTrades();
        $this->fusionFacturerAuTempsPasseTrades();
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }

    /** Le corps d origine, extrait pour que son `return` ne quitte que lui. */
    private function corpsInitial(): void
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

    /** Fusionne depuis 2026_05_17_120002_extend_trades_with_business_properties */
    private function fusion20260517120002ExtendTradesWithBusinessProperties(): void
    {
        if (! Schema::hasTable('trades')) {
            return;
        }

        Schema::table('trades', function (Blueprint $table) {
            if (! Schema::hasColumn('trades', 'default_hourly_rate')) {
                $table->decimal('default_hourly_rate', 10, 2)->nullable();
            }
            if (! Schema::hasColumn('trades', 'emergency_multiplier')) {
                $table->decimal('emergency_multiplier', 5, 2)->default(1.00);
            }
            if (! Schema::hasColumn('trades', 'night_multiplier')) {
                $table->decimal('night_multiplier', 5, 2)->default(1.00);
            }
            if (! Schema::hasColumn('trades', 'weekend_multiplier')) {
                $table->decimal('weekend_multiplier', 5, 2)->default(1.00);
            }
            if (! Schema::hasColumn('trades', 'quote_validity_days')) {
                $table->unsignedSmallInteger('quote_validity_days')->nullable();
            }
            if (! Schema::hasColumn('trades', 'requires_quote_by_default')) {
                $table->boolean('requires_quote_by_default')->default(false);
            }
            if (! Schema::hasColumn('trades', 'sla_response_minutes')) {
                $table->unsignedSmallInteger('sla_response_minutes')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_17_120004_add_booking_form_schema_to_trades */
    private function fusion20260517120004AddBookingFormSchemaToTrades(): void
    {
        if (! Schema::hasTable('trades') || Schema::hasColumn('trades', 'booking_form_schema')) {
            return;
        }

        Schema::table('trades', function (Blueprint $table) {
            $table->json('booking_form_schema')->nullable();
        });
    }

    /** Fusionne depuis 2026_05_27_000000_extend_trades_with_billing_and_site_visit */
    private function fusion20260527000000ExtendTradesWithBillingAndSiteVisit(): void
    {
        if (! Schema::hasTable('trades')) {
            return;
        }

        Schema::table('trades', function (Blueprint $table) {
            if (! Schema::hasColumn('trades', 'billing_unit')) {
                $table->string('billing_unit', 30)
                    ->default('hourly')

                    ->comment('hourly | per_m2 | fixed | per_item');
            }

            if (! Schema::hasColumn('trades', 'requires_site_visit')) {
                $table->boolean('requires_site_visit')
                    ->default(false);
            }
        });
    }

    /** Fusionne depuis 2026_07_28_000002_add_provider_form_schema_to_trades */
    private function fusion20260728000002AddProviderFormSchemaToTrades(): void
    {
        Schema::table('trades', function (Blueprint $table) {
            $table->json('provider_form_schema')->nullable();
        });
    }

    /** Fusionne depuis 2026_08_30_090000_facturer_au_temps_passe */
    private function fusionFacturerAuTempsPasseTrades(): void
    {
        if (Schema::hasTable('trades') && ! Schema::hasColumn('trades', 'hourly_billing')) {
            Schema::table('trades', function (Blueprint $table) {
                $table->boolean('hourly_billing')
                    ->default(false);
            });
        }
    }
};
