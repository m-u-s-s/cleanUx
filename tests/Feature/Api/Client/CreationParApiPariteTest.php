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

/**
 * LA CRÉATION PAR L'API DOIT ÉGALER LE CHEMIN WEB (H7, H8).
 *
 * DEUX DÉFAUTS, DONT UN TOTALEMENT SILENCIEUX.
 *
 * H7 — seule une réservation IMMÉDIATE déclenchait quelque chose. Une réservation PLANIFIÉE créée
 * par l'API n'était jamais confiée au moteur de répartition : elle restait en base, visible du
 * client, et aucun prestataire ne la voyait jamais. Rien ne paraît anormal des deux côtés jusqu'au
 * jour de l'intervention, où personne ne vient.
 *
 * H8 — ni `service_zone_id` ni `trade_id` n'étaient écrits sur la réservation. Ces deux colonnes
 * sont exactement celles sur lesquelles `CandidateFinder` filtre les prestataires. Sans elles, la
 * recherche ne filtre plus ce qu'elle prétend filtrer.
 *
 * CE POINT D'ENTRÉE N'EST APPELÉ PAR AUCUN ÉCRAN AUJOURD'HUI — l'application mobile réserve par la
 * WebView `/commander`, et `useCreateBooking` n'a pas d'appelant. Il reste atteignable par tout
 * jeton client, et c'est déjà une raison suffisante. Ces tests figent aussi la parité pour le jour
 * où un écran s'y branchera : c'est précisément le moment où un défaut silencieux coûte le plus.
 */
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

    /**
     * H7 — LE MODE PLANIFIÉ DOIT ATTEINDRE LE MOTEUR.
     *
     * On espionne `DispatchEngine` plutôt que d'observer ses effets : ce qu'on veut prouver est
     * précisément que l'appel a lieu, indépendamment de ce que le moteur décide ensuite (il peut
     * légitimement ne trouver personne).
     */
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

    /**
     * L'IMMÉDIAT SE REFUSE QUAND LA ZONE NE L'AUTORISE PAS.
     *
     * Le couple (métier, zone) porte `asap_enabled`. Accepter la demande quand il vaut faux ouvre
     * une recherche qui ne trouvera jamais personne : le client attend, échoue, et rien n'explique
     * pourquoi. Le refus immédiat est plus honnête que l'attente.
     */
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
