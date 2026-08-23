<?php

namespace Tests\Feature\Api\Client;

use App\Actions\Booking\CreateBookingFromApiAction;
use App\Models\PostalCode;
use App\Models\ServiceCatalog;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Services\Dispatch\DispatchEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Dispatch\Concerns\OuvreLeCatalogue;
use Tests\TestCase;

/** LA CRÉATION PAR L'API DOIT ÉGALER LE CHEMIN WEB (H7, H8). */
class CreationParApiPariteTest extends TestCase
{
    use OuvreLeCatalogue;
    use RefreshDatabase;

    /** @return array{0: User, 1: ServiceCatalog, 2: ServiceZone, 3: Trade} */
    private function catalogueOuvert(bool $immediatAutorise = true): array
    {
        $client = User::factory()->client()->create();

        // `status` doit rester parmi active|paused : ZoneCoverageService écarte les autres.
        $zone = ServiceZone::factory()->create(['status' => 'active']);
        // `allows_asap` est le PREMIER verrou, global au métier : un ravalement de façade
        // n'est jamais immédiat, nulle part. La ligne de zone n'est consultée qu'ensuite.
        $trade = Trade::factory()->create(['allows_asap' => $immediatAutorise]);

        $this->ouvrirAuCatalogue($trade, $zone, immediat: $immediatAutorise);

        // LE RATTACHEMENT PASSE PAR LA TABLE PIVOT, pas par une colonne sur postal_codes :
        // `resolveServiceZoneWithSource` interroge la relation `postalCodes`. Une fixture qui
        // poserait une colonne inexistante résoudrait vers le repli national, et le test
        // mesurerait le repli au lieu de mesurer la couverture.
        $codePostal = PostalCode::factory()->create(['code' => '1000']);
        $zone->postalCodes()->attach($codePostal->id, ['is_primary' => true]);

        $catalogue = ServiceCatalog::factory()->create(['trade_id' => $trade->id]);

        return [$client, $catalogue, $zone, $trade];
    }

    /** @return array<string, mixed> */
    private function donnees(ServiceCatalog $catalogue, string $mode): array
    {
        return [
            'service_catalog_id' => $catalogue->id,
            'address' => 'Grand-Place 1',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'scheduled_date' => now()->addDays(3)->toDateString(),
            'scheduled_time' => '09:00',
            'booking_mode' => $mode,
        ];
    }

    #[Test]
    public function la_zone_et_le_metier_sont_ecrits_sur_la_reservation(): void
    {
        [$client, $catalogue, $zone, $trade] = $this->catalogueOuvert();

        $booking = app(CreateBookingFromApiAction::class)
            ->execute($client, $this->donnees($catalogue, 'scheduled'));

        $this->assertSame(
            $zone->id,
            $booking->service_zone_id,
            'Sans zone persistée, CandidateFinder ne peut pas filtrer sur la couverture.'
        );
        $this->assertSame(
            $trade->id,
            $booking->trade_id,
            'Sans métier persisté, la recherche propose la course à des prestataires d’un autre métier.'
        );
    }

    /** H7 — LE MODE PLANIFIÉ DOIT ATTEINDRE LE MOTEUR. */
    #[Test]
    public function une_reservation_planifiee_est_confiee_au_moteur(): void
    {
        [$client, $catalogue] = $this->catalogueOuvert();

        $moteur = Mockery::mock(DispatchEngine::class);
        $moteur->shouldReceive('dispatchBooking')->once()->andReturnNull();
        $this->app->instance(DispatchEngine::class, $moteur);

        app(CreateBookingFromApiAction::class)
            ->execute($client, $this->donnees($catalogue, 'scheduled'));
    }

    /** L'IMMÉDIAT SE REFUSE QUAND LA ZONE NE L'AUTORISE PAS. */
    #[Test]
    public function l_immediat_est_refuse_la_ou_la_zone_ne_l_autorise_pas(): void
    {
        [$client, $catalogue] = $this->catalogueOuvert(immediatAutorise: false);

        $this->expectException(ValidationException::class);

        app(CreateBookingFromApiAction::class)
            ->execute($client, $this->donnees($catalogue, 'asap'));
    }

    #[Test]
    public function l_immediat_reste_possible_la_ou_la_zone_l_autorise(): void
    {
        [$client, $catalogue] = $this->catalogueOuvert(immediatAutorise: true);

        $booking = app(CreateBookingFromApiAction::class)
            ->execute($client, $this->donnees($catalogue, 'asap'));

        $this->assertSame('asap', $booking->booking_mode);
        $this->assertNotNull($booking->service_zone_id);
    }
}
