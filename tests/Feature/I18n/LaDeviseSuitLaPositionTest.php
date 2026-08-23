<?php

namespace Tests\Feature\I18n;

use App\Models\Country;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\International\CountryMarketResolver;
use App\Services\Localization\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DEUX PORTES D'ENTRÉE POUR UNE COMMANDE, UNE SEULE QUI SAVAIT SA MONNAIE.
 *
 * `CreateBookingFromApiAction` résout la devise depuis la position du client, et son commentaire
 * nomme le défaut qu'il corrigeait : « deux nombres, deux monnaies, aucune alerte ».
 *
 * `OrderConfirmationService` — le parcours WEB, celui par lequel passe un particulier — ne posait
 * AUCUNE `currency` sur la réservation qu'il crée. Elle tombait donc sur le défaut de la colonne,
 * `EUR`, quelle que soit la géographie. Un client marocain commandait en dirhams et sa réservation
 * se libellait en euros.
 *
 * Ce test fixe la règle sur le RÉSOLVEUR, qui est le point commun des deux portes : la devise suit
 * la position, jamais un défaut de schéma.
 */
class LaDeviseSuitLaPositionTest extends TestCase
{
    use RefreshDatabase;

    private function zonePour(string $iso, string $devise): ServiceZone
    {
        $pays = Country::query()->updateOrCreate(
            ['iso_code' => $iso],
            ['name' => $iso, 'currency_code' => $devise, 'is_active' => true],
        );

        return ServiceZone::query()->create([
            'country_id' => $pays->id,
            'name' => 'Zone '.$iso,
            'slug' => 'zone-'.strtolower($iso),
            'status' => 'active',
        ]);
    }

    /**
     * TÉMOIN — la position belge donne toujours l'euro.
     *
     * Sans ce contrôle, un résolveur qui rendrait n'importe quoi passerait le test marocain
     * ci-dessous par accident.
     */
    public function test_temoin_une_zone_belge_donne_l_euro(): void
    {
        $devise = app(CountryMarketResolver::class)->deviseAttendue(
            client: User::factory()->create(),
            zone: $this->zonePour('BE', 'EUR'),
        );

        $this->assertSame('EUR', $devise);
    }

    public function test_une_zone_marocaine_donne_le_dirham(): void
    {
        $devise = app(CountryMarketResolver::class)->deviseAttendue(
            client: User::factory()->create(),
            zone: $this->zonePour('MA', 'MAD'),
        );

        $this->assertSame('MAD', $devise, 'Une commande marocaine se libellait en euros.');
    }

    /**
     * ET LE MONTANT S'AFFICHE DANS CETTE DEVISE-LÀ.
     *
     * Résoudre la bonne devise ne sert à rien si le formateur la réécrit : `Money` ne connaissait
     * que cinq devises et rendait `MAD` en euros. Les deux moitiés doivent tenir ensemble.
     */
    public function test_le_montant_resolu_s_affiche_dans_la_bonne_devise(): void
    {
        $devise = app(CountryMarketResolver::class)->deviseAttendue(
            client: User::factory()->create(),
            zone: $this->zonePour('MA', 'MAD'),
        );

        $rendu = app(Money::class)->format(250.0, $devise, 'fr');

        $this->assertStringContainsString('MAD', $rendu);
        $this->assertStringNotContainsString('€', $rendu);
    }
}
