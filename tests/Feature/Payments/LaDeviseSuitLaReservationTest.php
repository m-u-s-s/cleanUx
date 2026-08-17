<?php

namespace Tests\Feature\Payments;

use App\Models\Booking;
use App\Services\Payments\CommissionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/**
 * LA DEVISE VIENT DE LA RÉSERVATION, PAS D'UNE CONSTANTE.
 *
 * `CommissionService` rendait `'currency' => 'eur'` en dur. Une commande en francs suisses
 * produisait donc une ligne de commission libellée en euros, pour un montant qui n'en était pas —
 * et le versement au prestataire, qui recopie cette valeur, héritait du même mensonge.
 *
 * Ce n'est pas théorique sur cette plateforme : le module FX existe, `config('fx.base_currency')`
 * est configurable, et `bookings.currency` est écrite. La seule chose qui manquait était de les
 * lire.
 *
 * LE REPLI EST LA DEVISE DE BASE DE LA PLATEFORME, jamais une valeur réinventée dans un service :
 * deux défauts différents finiraient par diverger, et c'est celui qu'on n'a pas relu qui
 * libellerait les versements.
 */
class LaDeviseSuitLaReservationTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_commission_prend_la_devise_de_la_reservation(): void
    {
        $scenario = SpineScenario::make()->build();

        Booking::query()->whereKey($scenario->booking->getKey())->update([
            'currency' => 'CHF',
            'devis_estime' => 120,
        ]);

        $commission = app(CommissionService::class)->calculateForBooking($scenario->booking->refresh());

        $this->assertSame('chf', $commission['currency']);
    }

    /**
     * TÉMOIN — l'euro continue de fonctionner exactement comme avant.
     *
     * Sans lui, le test précédent passerait au vert sur une implémentation qui aurait cassé le cas
     * nominal, c'est-à-dire la totalité des réservations existantes.
     */
    public function test_temoin_une_reservation_en_euros_reste_en_euros(): void
    {
        $scenario = SpineScenario::make()->build();

        Booking::query()->whereKey($scenario->booking->getKey())->update([
            'currency' => 'EUR',
            'devis_estime' => 120,
        ]);

        $this->assertSame(
            'eur',
            app(CommissionService::class)->calculateForBooking($scenario->booking->refresh())['currency'],
        );
    }

    /**
     * `bookings.currency` EST NOT NULL — mesuré, pas supposé.
     *
     * Le repli sur la devise de la plateforme n'est donc JAMAIS emprunté depuis une réservation :
     * la contrainte de base garantit la valeur. Il sert au calcul par MONTANT, qui n'a pas de
     * réservation sous la main. Le dire ici évite qu'on croie plus tard avoir couvert un cas que
     * le schéma rend impossible — et qu'on écrive un test qui ne mesure rien.
     */
    public function test_la_devise_dune_reservation_est_toujours_renseignee(): void
    {
        $scenario = SpineScenario::make()->build();

        /*
         * ON RELIT AVANT D'ASSERTER, et c'est le piege documente de ce depot : la valeur vient d'un
         * DEFAUT SQL, et un defaut SQL ne peuple pas l'objet en memoire. `$scenario->booking->currency`
         * rend `null` juste apres la creation alors que la ligne, elle, porte bien sa devise.
         */
        $this->assertNotNull($scenario->booking->refresh()->currency);

        $this->expectException(QueryException::class);

        Booking::query()->whereKey($scenario->booking->getKey())->update(['currency' => null]);
    }

    /**
     * L'APPEL PAR MONTANT accepte aussi la devise — c'est celui qu'utilisent les suppléments et le
     * règlement du temps supplémentaire, deux chemins qui portent de l'argent réel.
     */
    public function test_le_calcul_par_montant_accepte_une_devise(): void
    {
        $service = app(CommissionService::class);

        $this->assertSame('chf', $service->calculateForAmount(5000, null, 'CHF')['currency']);
        $this->assertSame(
            strtolower((string) config('fx.base_currency', 'EUR')),
            $service->calculateForAmount(5000)['currency'],
            'Sans devise passée, le repli reste celui de la plateforme.',
        );
    }
}
