<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FACTURER AU TEMPS PASSÉ — le drapeau du métier, et le tarif par zone.
 *
 * CE QUE CETTE MIGRATION RÉPARE AUTANT QU'ELLE AJOUTE. La plateforme annonce déjà publiquement
 * « à partir de 45 €/heure » (`pages/service-trade.blade.php`) à partir de `trades.default_hourly_rate`,
 * et le métier « Nettoyage à domicile » est semé avec `pricing_unit = per_hour`. Mais AUCUN moteur du
 * parcours de commande ne lit l'une ou l'autre : ces 45 € sont facturés comme un FORFAIT. Un ménage
 * de trois heures coûte 45 €, pas 135 €. La vitrine promet un tarif horaire que la commande
 * n'applique pas.
 *
 * `hourly_billing` est le drapeau qui rend cette promesse vraie — et c'est le SEUL des quatre
 * candidats qui pilotera un prix. Les trois autres notions d'unité qui existent déjà
 * (`trades.billing_unit`, `trades.pricing_unit`, `service_catalogs.billing_unit`) ne pilotent rien
 * aujourd'hui et ne piloteront rien demain : elles restent en place pour l'affichage, et le
 * commentaire de `Trade::$casts` dit désormais laquelle fait foi.
 *
 * `price_per_hour_cents` sur la zone suit exactement le patron de `price_per_km_cents`, posé par
 * `2026_08_29_090000` : le prix vendu vit par zone, le métier ne porte qu'une référence. Une heure
 * de ménage ne vaut pas la même chose à Bruxelles et dans un village.
 *
 * DÉFAUT PAR DÉFAUT : `false` et `null`. Aucun métier ne change de comportement tant qu'un
 * administrateur n'a rien coché.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trades') && ! Schema::hasColumn('trades', 'hourly_billing')) {
            Schema::table('trades', function (Blueprint $table) {
                $table->boolean('hourly_billing')
                    ->default(false)
                    ->after('default_hourly_rate');
            });
        }

        if (Schema::hasTable('trade_zone_pricing') && ! Schema::hasColumn('trade_zone_pricing', 'price_per_hour_cents')) {
            Schema::table('trade_zone_pricing', function (Blueprint $table) {
                /*
                 * NULLABLE, ET LA NUANCE EST LE PROPOS.
                 *
                 * `null` = « cette zone ne surcharge rien », on retombe sur le tarif du métier.
                 * `0` = « une heure est gratuite ici », ce qui est une décision, absurde mais
                 * explicite. `price_per_km_cents` a exactement la même nuance, pour la même raison :
                 * un défaut à 0 aurait rendu la surcharge indistinguable de l'absence de surcharge,
                 * et toutes les zones auraient silencieusement facturé zéro.
                 */
                $table->unsignedInteger('price_per_hour_cents')
                    ->nullable()
                    ->after('included_km');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('trade_zone_pricing') && Schema::hasColumn('trade_zone_pricing', 'price_per_hour_cents')) {
            Schema::table('trade_zone_pricing', function (Blueprint $table) {
                $table->dropColumn('price_per_hour_cents');
            });
        }

        if (Schema::hasTable('trades') && Schema::hasColumn('trades', 'hourly_billing')) {
            Schema::table('trades', function (Blueprint $table) {
                $table->dropColumn('hourly_billing');
            });
        }
    }
};
