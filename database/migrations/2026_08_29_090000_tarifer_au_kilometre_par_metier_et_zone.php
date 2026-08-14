<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LE PRIX AU KILOMÈTRE — EN OPTION, ET ÉTEINT PAR DÉFAUT.
 *
 * Le moteur de commande ne connaît que des forfaits : une base, des modificateurs, des
 * multiplicateurs. Aucune distance n'entre nulle part dans un prix. C'est juste pour un ménage, et
 * absurde pour une course : deux kilomètres et vingt kilomètres s'y factureraient pareil.
 *
 * `distance_pricing_enabled` par DÉFAUT À FAUX, et ce n'est pas une précaution : c'est la garantie
 * que la sortie du moteur reste identique au centime pour tous les métiers existants. Un défaut à
 * vrai avec des tarifs à zéro donnerait le même résultat en apparence, mais ferait dépendre chaque
 * devis d'un calcul supplémentaire — et le premier tarif saisi par erreur déplacerait des prix
 * pour de vrais clients.
 *
 * LA LIGNE EST LA MÊME QUE L'ACTIVATION. `trade_zone_pricing` porte déjà « ce métier est ouvert
 * ici, à ce tarif » ; le prix au kilomètre est de la même nature — une décision d'exploitation,
 * zone par zone. Une course coûte plus cher au kilomètre en centre-ville qu'en périphérie, et
 * personne ne devrait déployer pour le dire.
 *
 * `included_km` existe parce que tous les concurrents facturent une PRISE EN CHARGE qui couvre les
 * premiers kilomètres. Sans elle, une course de huit cents mètres se facturerait au compteur, et le
 * montant serait trop bas pour couvrir le déplacement du conducteur.
 */
return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        if (! Schema::hasTable('trade_zone_pricing')) {
            return;
        }

        Schema::table('trade_zone_pricing', function (Blueprint $table) {
            foreach ([
                'distance_pricing_enabled',
                'pickup_fee_cents',
                'price_per_km_cents',
                'price_per_minute_cents',
                'included_km',
            ] as $colonne) {
                if (Schema::hasColumn('trade_zone_pricing', $colonne)) {
                    $table->dropColumn($colonne);
                }
            }
        });
    }
};
