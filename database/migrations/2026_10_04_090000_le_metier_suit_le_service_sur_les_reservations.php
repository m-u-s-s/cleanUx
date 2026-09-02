<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * LE MÉTIER SUIT LE SERVICE — rattrapage des réservations qui ne portaient que le second.
 *
 * `ServiceCatalog` dit CE QUI EST VENDU, `Trade` CE QUI EST DÉPÊCHÉ : les cinquante-neuf services
 * portent tous un `trade_id`, le premier vit donc SOUS le second. Mais le chemin web n'écrivait
 * que `service_catalog_id`. Le dispatch s'en sortait par le repli de `Booking::resolveTradeId()` ;
 * toute requête directe sur `bookings.trade_id`, elle, ignorait ces lignes.
 *
 * Les deux portes posent désormais le couple. Cette migration remet les anciennes au même niveau.
 * Elle ne remplit que des colonnes VIDES et se déduit entièrement de la relation : la rejouer ne
 * change rien, et elle n'écrase aucun choix explicite.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('bookings')
            ->whereNull('trade_id')
            ->whereNotNull('service_catalog_id')
            ->orderBy('id')
            ->chunkById(500, function ($lignes) {
                foreach ($lignes as $ligne) {
                    $metierId = DB::table('service_catalogs')
                        ->where('id', $ligne->service_catalog_id)
                        ->value('trade_id');

                    if ($metierId !== null) {
                        DB::table('bookings')->where('id', $ligne->id)->update(['trade_id' => $metierId]);
                    }
                }
            });
    }

    /**
     * PAS DE RETOUR EN ARRIÈRE. Vider `trade_id` reprendrait aussi les réservations que le moteur
     * de commande a toujours créées avec — la migration ne sait pas distinguer les siennes.
     * Le rattrapage est déductible de la relation : le défaire n'a aucune valeur.
     */
    public function down(): void {}
};
