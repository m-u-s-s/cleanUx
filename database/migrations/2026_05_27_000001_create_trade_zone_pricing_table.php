<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->corpsInitial();
        $this->fusion20260829090000TariferAuKilometreParMetierEtZone();
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_zone_pricing');
    }

    /** Le corps d origine, extrait pour que son `return` ne quitte que lui. */
    private function corpsInitial(): void
    {
        if (Schema::hasTable('trade_zone_pricing')) {
            return;
        }

        Schema::create('trade_zone_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trade_id')->constrained('trades')->cascadeOnDelete();
            $table->foreignId('service_zone_id')->constrained('service_zones')->cascadeOnDelete();
            $table->unsignedInteger('base_rate_cents')->default(0);
            $table->decimal('surge_multiplier', 5, 2)->default(1.00);
            $table->unsignedInteger('min_price_cents')->nullable();
            $table->unsignedInteger('max_price_cents')->nullable();
            $table->boolean('is_active')->default(true);

            /*
             * L'INTERVENTION IMMÉDIATE SE DÉCIDE PAR ZONE, comme le prix — et sur la MÊME LIGNE.
             *
             * Un plombier de garde à Bruxelles n'implique pas un plombier de garde à Bastogne : la
             * question « ce métier fait-il de l'immédiat » n'a de réponse qu'ici, dans le couple
             * (métier, zone). Une colonne globale sur `trades` promettrait un dépannage dans une
             * zone où personne n'est jamais en ligne — et le client attendrait devant sa porte.
             *
             * DÉFAUT FAUX. Ouvrir l'immédiat engage la plateforme à dépêcher quelqu'un dans
             * l'heure : c'est une décision d'administrateur, jamais un oubli de configuration.
             */
            $table->boolean('asap_enabled')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['trade_id', 'service_zone_id']);
            $table->index(['service_zone_id', 'is_active']);
            $table->index(['trade_id', 'is_active']);
        });
    }

    /** Fusionne depuis 2026_08_29_090000_tarifer_au_kilometre_par_metier_et_zone */
    private function fusion20260829090000TariferAuKilometreParMetierEtZone(): void
    {
        if (! Schema::hasTable('trade_zone_pricing')) {
            return;
        }

        Schema::table('trade_zone_pricing', function (Blueprint $table) {
            if (! Schema::hasColumn('trade_zone_pricing', 'distance_pricing_enabled')) {
                $table->boolean('distance_pricing_enabled')->default(false);
            }

            if (! Schema::hasColumn('trade_zone_pricing', 'pickup_fee_cents')) {
                // La prise en charge : ce qu'on paie pour que quelqu'un vienne, avant le moindre
                // kilomètre.
                $table->unsignedInteger('pickup_fee_cents')->default(0);
            }

            if (! Schema::hasColumn('trade_zone_pricing', 'price_per_km_cents')) {
                $table->unsignedInteger('price_per_km_cents')->nullable();
            }

            if (! Schema::hasColumn('trade_zone_pricing', 'price_per_minute_cents')) {
                // Facultatif : tous les marchés ne facturent pas le temps, et là où l'embouteillage
                // est la règle, le kilomètre seul ruine le conducteur.
                $table->unsignedInteger('price_per_minute_cents')->nullable();
            }

            if (! Schema::hasColumn('trade_zone_pricing', 'included_km')) {
                $table->unsignedInteger('included_km')->default(0);
            }
        });
    }
};
