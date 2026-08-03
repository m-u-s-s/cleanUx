<?php

namespace Tests\Feature\OrderEngine;

use App\Models\Country;
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
}
