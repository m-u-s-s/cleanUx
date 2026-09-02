<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\ServiceCatalog;
use App\Models\Trade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * LE SERVICE DIT CE QUI EST VENDU, LE MÉTIER CE QUI EST DÉPÊCHÉ.
 *
 * Les deux catalogues du dépôt ne sont pas deux vérités concurrentes : les cinquante-neuf
 * `ServiceCatalog` portent TOUS un `trade_id`, le service vit donc SOUS le métier.
 * `ZoneServiceRule` dit à quelles conditions un service est offert dans une zone ;
 * `TradeZonePricing` dit combien coûte le métier. Ce sont deux étages, pas deux copies.
 *
 * Ce qui manquait : le chemin web ne posait que `service_catalog_id`. Le dispatch s'en sortait par
 * le repli de `resolveTradeId()`, mais une requête directe sur `bookings.trade_id` ignorait ces
 * réservations — la famille de défauts la plus coûteuse de ce dépôt.
 */
class LeMetierSuitLeServiceTest extends TestCase
{
    use RefreshDatabase;

    /** Toute réservation qui porte un service porte AUSSI son métier. */
    public function test_aucune_reservation_ne_porte_un_service_sans_son_metier(): void
    {
        $orphelines = DB::table('bookings')
            ->join('service_catalogs', 'service_catalogs.id', '=', 'bookings.service_catalog_id')
            ->whereNull('bookings.trade_id')
            ->whereNotNull('service_catalogs.trade_id')
            ->count();

        $this->assertSame(0, $orphelines,
            'Des réservations portent un service dont le métier n’a pas été reporté : '
            .'toute requête directe sur `bookings.trade_id` les ignore.');
    }

    /**
     * TÉMOIN — le compte sait voir une orpheline. Sans lui, l’assertion ci-dessus passerait au vert
     * sur une table vide, en mesurant l’absence de données plutôt que l’absence de défaut.
     */
    public function test_temoin_le_compte_repere_bien_une_orpheline(): void
    {
        $metier = Trade::factory()->create();
        $service = ServiceCatalog::factory()->create(['trade_id' => $metier->id]);

        $rdv = Booking::factory()->create(['service_catalog_id' => $service->id]);
        DB::table('bookings')->where('id', $rdv->id)->update(['trade_id' => null]);

        $orphelines = DB::table('bookings')
            ->join('service_catalogs', 'service_catalogs.id', '=', 'bookings.service_catalog_id')
            ->whereNull('bookings.trade_id')
            ->whereNotNull('service_catalogs.trade_id')
            ->count();

        $this->assertSame(1, $orphelines, 'Le compte ne repère plus une réservation sans métier.');
    }

    /** Le repli de `resolveTradeId()` reste juste — il devient une ceinture, plus une nécessité. */
    public function test_le_repli_par_le_catalogue_dit_toujours_vrai(): void
    {
        $metier = Trade::factory()->create();
        $service = ServiceCatalog::factory()->create(['trade_id' => $metier->id]);

        $rdv = Booking::factory()->create(['service_catalog_id' => $service->id, 'trade_id' => null]);

        $this->assertSame($metier->id, $rdv->fresh()->resolveTradeId());
    }

    /** LA COLONNE PRIME sur le catalogue : un métier posé explicitement ne se laisse pas réécrire. */
    public function test_la_colonne_prime_sur_le_catalogue(): void
    {
        $metierDuService = Trade::factory()->create();
        $metierExplicite = Trade::factory()->create();
        $service = ServiceCatalog::factory()->create(['trade_id' => $metierDuService->id]);

        $rdv = Booking::factory()->create([
            'service_catalog_id' => $service->id,
            'trade_id' => $metierExplicite->id,
        ]);

        $this->assertSame($metierExplicite->id, $rdv->fresh()->resolveTradeId());
    }
}
