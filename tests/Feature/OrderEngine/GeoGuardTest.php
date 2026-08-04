<?php

namespace Tests\Feature\OrderEngine;

use App\Models\Booking;
use App\Models\Country;
use App\Models\ServiceZone;
use App\Services\Catalog\GeoGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les règles géographiques du catalogue, testées sans interface.
 *
 * POURQUOI ICI ET NON DANS UN TEST D'ÉCRAN. Une règle de suppression fausse ne se manifeste qu'au
 * moment où elle détruit quelque chose. La tester à travers un composant Livewire, c'est la tester
 * à travers le rendu, la validation et l'autorisation — trois raisons de passer au vert pour de
 * mauvaises raisons.
 */
class GeoGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_modele_pays_accepte_les_colonnes_operationnelles(): void
    {
        // `booking_enabled` existait en base sans être `fillable` : un create le perdait en
        // silence, et l'écran d'administration aurait affiché « réservations fermées » sans
        // qu'aucune erreur ne soit levée.
        $pays = Country::create([
            'iso_code' => 'FR',
            'name' => 'France',
            'currency_code' => 'EUR',
            'is_active' => true,
            'booking_enabled' => true,
            'market_stage' => 'pilot',
        ]);

        $this->assertTrue($pays->fresh()->booking_enabled);
        $this->assertSame('pilot', $pays->fresh()->market_stage);
    }

    public function test_un_pays_qui_porte_des_zones_ne_se_supprime_pas(): void
    {
        $pays = Country::factory()->create();
        ServiceZone::factory()->count(3)->create(['country_id' => $pays->id]);

        $raisons = app(GeoGuard::class)->raisonsDeNePasSupprimerPays($pays);

        // Le message porte le COMPTE. « Ça ne se supprime pas » sans dire pourquoi oblige à
        // ouvrir la base pour comprendre — ce à quoi un administrateur n'a pas accès.
        $this->assertNotEmpty($raisons);
        $this->assertStringContainsString('3', $raisons[0]);
    }

    public function test_un_pays_sans_rien_se_supprime(): void
    {
        $pays = Country::factory()->create();

        $this->assertSame([], app(GeoGuard::class)->raisonsDeNePasSupprimerPays($pays));
    }

    public function test_une_zone_qui_porte_des_reservations_ne_se_supprime_pas(): void
    {
        $zone = ServiceZone::factory()->create();
        Booking::factory()->create(['service_zone_id' => $zone->id]);

        $this->assertNotEmpty(app(GeoGuard::class)->raisonsDeNePasSupprimerZone($zone));
    }

    public function test_une_zone_vide_se_supprime(): void
    {
        $zone = ServiceZone::factory()->create();

        $this->assertSame([], app(GeoGuard::class)->raisonsDeNePasSupprimerZone($zone));
    }

    public function test_eteindre_le_pays_rend_ses_zones_injoignables_sans_les_modifier(): void
    {
        $pays = Country::factory()->create(['is_active' => true]);
        $zone = ServiceZone::factory()->create([
            'country_id' => $pays->id,
            'is_bookable' => true,
            'status' => 'active',
        ]);

        $this->assertTrue(app(GeoGuard::class)->zoneEstJoignable($zone->fresh()));

        $pays->update(['is_active' => false]);

        /*
         * La zone devient injoignable — mais SON PROPRE état n'a pas bougé.
         *
         * C'est ce qui permet à la réactivation du pays de restaurer exactement l'état d'avant, y
         * compris les zones qui étaient déjà éteintes pour leur propre raison. Une propagation en
         * écriture les rallumerait toutes, et personne ne s'en apercevrait avant qu'un client
         * réserve dans une zone fermée.
         */
        $this->assertFalse(app(GeoGuard::class)->zoneEstJoignable($zone->fresh()));
        $this->assertTrue($zone->fresh()->is_bookable);
        $this->assertSame('active', $zone->fresh()->status);
    }

    public function test_une_zone_eteinte_reste_injoignable_meme_dans_un_pays_actif(): void
    {
        $pays = Country::factory()->create(['is_active' => true]);
        $zone = ServiceZone::factory()->create([
            'country_id' => $pays->id,
            'is_bookable' => false,
            'status' => 'active',
        ]);

        // La règle se lit dans les deux sens : le pays actif ne rachète pas une zone fermée.
        $this->assertFalse(app(GeoGuard::class)->zoneEstJoignable($zone));
    }
}
