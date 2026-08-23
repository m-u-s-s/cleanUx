<?php

namespace Tests\Feature\Payments;

use App\Models\Booking;
use App\Services\Payments\CommissionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

/** LA DEVISE VIENT DE LA RÉSERVATION, PAS D'UNE CONSTANTE. */
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

    /** TÉMOIN — l'euro continue de fonctionner exactement comme avant. */
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

    /** `bookings.currency` EST NOT NULL — mesuré, pas supposé. */
    public function test_la_devise_dune_reservation_est_toujours_renseignee(): void
    {
        $scenario = SpineScenario::make()->build();

        // ON RELIT AVANT D'ASSERTER, et c'est le piege documente de ce depot : la valeur vient d'un DEFAUT SQL, et un defaut SQL ne peuple pas l'objet en memoire.
        $this->assertNotNull($scenario->booking->refresh()->currency);

        $this->expectException(QueryException::class);

        Booking::query()->whereKey($scenario->booking->getKey())->update(['currency' => null]);
    }

    /** L'APPEL PAR MONTANT accepte aussi la devise — c'est celui qu'utilisent les suppléments et le règlement du temps supplémentaire, deux chemins qui portent de l'argent réel. */
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
