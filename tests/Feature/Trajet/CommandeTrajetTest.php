<?php

namespace Tests\Feature\Trajet;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\Booking;
use App\Models\OrderDraft;
use App\Models\Question;
use App\Models\Trade;
use App\Models\User;
use App\Services\Geo\RoutingService;
use App\Services\OrderEngine\OrderConfirmationService;
use App\Services\OrderEngine\OrderDraftManager;
use App\Services\OrderEngine\ZonePricingResolver;
use App\Support\Domain\LocationRole;
use App\Support\Domain\QuestionType;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Feature\Dispatch\Concerns\OuvreLeCatalogue;
use Tests\TestCase;

/**
 * COMMANDER UNE COURSE : deux points, une distance, et un prix annoncé AVANT.
 *
 * Le point de DÉPART écrit les colonnes d'adresse qui existent déjà — c'est le choix central du
 * lot, et ce qui permet à la zone, au catalogue, au dispatch de proximité et à la geofence de
 * continuer à lire exactement ce qu'ils lisaient. Le point d'ARRIVÉE va dans ses propres colonnes :
 * lui donner `destination_lat/lng` ferait dire deux choses à une même colonne selon le métier, et
 * la clôture d'une course serait refusée pour éloignement du lieu où elle a commencé.
 *
 * LE TÉMOIN EST OBLIGATOIRE. Chaque test qui vérifie qu'une course se comporte autrement est
 * doublé du métier ordinaire qui, lui, ne doit RIEN voir changer.
 */
class CommandeTrajetTest extends TestCase
{
    use OuvreLeCatalogue, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Un métier de course, greffé sur le catalogue semé — sans toucher aux métiers existants. */
    private function course(): Trade
    {
        $trade = Trade::where('slug', 'peinture')->firstOrFail()->replicate();
        $trade->slug = 'course-vtc';
        $trade->code = 'VTC';
        $trade->name = 'Course';
        $trade->save();

        Question::create([
            'trade_id' => $trade->id,
            'code' => 'depart',
            'label' => 'Où êtes-vous ?',
            'type' => QuestionType::LOCATION,
            'location_role' => LocationRole::PICKUP,
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Question::create([
            'trade_id' => $trade->id,
            'code' => 'arrivee',
            'label' => 'Où allez-vous ?',
            'type' => QuestionType::LOCATION,
            'location_role' => LocationRole::DROPOFF,
            'is_required' => true,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        return $trade->fresh();
    }

    /** @return array{lat: float, lng: float, label: string, postal_code: string} */
    private function point(float $lat, float $lng, string $label, string $cp = '1000'): array
    {
        return ['label' => $label, 'lat' => $lat, 'lng' => $lng, 'postal_code' => $cp];
    }

    public function test_le_point_de_depart_alimente_l_adresse_de_la_commande(): void
    {
        $course = $this->course();

        $composant = Livewire::test(OrderJourney::class)
            ->call('selectSector', $course->sector_id)
            ->call('selectTrade', $course->id)
            ->call('recordAnswer', 'depart', $this->point(50.8467, 4.3525, 'Rue de la Loi 1, 1000 Bruxelles'), true);

        $draft = $composant->instance()->draft()->fresh();

        $this->assertSame('Rue de la Loi 1, 1000 Bruxelles', $draft->address);
        $this->assertEqualsWithDelta(50.8467, (float) $draft->lat, 0.0001);
        $this->assertEqualsWithDelta(4.3525, (float) $draft->lng, 0.0001);
        $this->assertNull(
            $draft->dropoff_lat,
            'Le départ ne doit rien écrire dans les colonnes de la dépose : elles décrivent l’autre bout.'
        );
    }

    public function test_le_point_d_arrivee_et_la_route_sont_mesures_a_la_commande(): void
    {
        $course = $this->course();

        $composant = Livewire::test(OrderJourney::class)
            ->call('selectSector', $course->sector_id)
            ->call('selectTrade', $course->id)
            ->call('recordAnswer', 'depart', $this->point(50.8467, 4.3525, 'Rue de la Loi 1, 1000 Bruxelles'), true)
            ->call('recordAnswer', 'arrivee', $this->point(50.9010, 4.4844, 'Aéroport de Bruxelles', '1930'), true);

        $draft = $composant->instance()->draft()->fresh();

        $this->assertSame('Aéroport de Bruxelles', $draft->dropoff_address);
        $this->assertEqualsWithDelta(50.9010, (float) $draft->dropoff_lat, 0.0001);
        $this->assertEqualsWithDelta(4.4844, (float) $draft->dropoff_lng, 0.0001);

        // La distance est connue AVANT le paiement : c'est elle qui rend un prix au kilomètre
        // annonçable, et un tarif découvert à l'arrivée est ce qu'on reproche aux taxis.
        $this->assertNotNull($draft->route_distance_m);
        $this->assertGreaterThan(9_000, $draft->route_distance_m);
        $this->assertNotNull($draft->route_source);
    }

    /** LE TÉMOIN : sans métier de trajet, rien de tout cela ne se déclenche. */
    public function test_un_metier_ordinaire_ne_produit_ni_arrivee_ni_route(): void
    {
        $peinture = Trade::where('slug', 'peinture')->firstOrFail();

        $composant = Livewire::test(OrderJourney::class)
            ->call('selectSector', $peinture->sector_id)
            ->call('selectTrade', $peinture->id)
            ->call('recordAnswer', 'surface_m2', 40, true);

        $draft = $composant->instance()->draft()->fresh();

        $this->assertNull($draft->dropoff_lat);
        $this->assertNull($draft->route_distance_m);
        $this->assertFalse($composant->instance()->estUnTrajet);
    }

    public function test_la_confirmation_refuse_une_course_sans_point_d_arrivee(): void
    {
        [$draft, $client] = $this->panier($this->course(), avecArrivee: false);

        $blocages = app(OrderConfirmationService::class)->blockers($draft);

        $this->assertNotEmpty($blocages);
        $this->assertStringContainsString('point d’arrivée', implode(' ', $blocages));

        $this->expectException(ValidationException::class);
        app(OrderConfirmationService::class)->confirm($draft, $client);
    }

    /** LE TÉMOIN du test précédent : avec le point d'arrivée, la même commande DOIT passer. */
    public function test_la_confirmation_accepte_une_course_complete(): void
    {
        [$draft, $client] = $this->panier($this->course(), avecArrivee: true);

        $this->assertSame([], app(OrderConfirmationService::class)->blockers($draft));

        app(OrderConfirmationService::class)->confirm($draft, $client);

        $booking = Booking::firstOrFail();

        $this->assertTrue($booking->estUneCourse());
        $this->assertSame('Aéroport de Bruxelles', $booking->dropoff_address);
        $this->assertEqualsWithDelta(50.9010, (float) $booking->dropoff_lat, 0.0001);
        // Le point A reste `destination_*` : c'est là que le prestataire se rend, et tout ce qui
        // lit ces colonnes (geofence, suivi, proximité) désigne cet endroit-là.
        $this->assertEqualsWithDelta(50.8467, (float) $booking->destination_lat, 0.0001);
        $this->assertNotNull($booking->route_distance_m);
    }

    /** LE TÉMOIN : une réservation ordinaire n'est pas une course, et le reste. */
    public function test_une_reservation_ordinaire_n_est_pas_une_course(): void
    {
        $peinture = Trade::where('slug', 'peinture')->firstOrFail();
        [$draft, $client] = $this->panier($peinture, avecArrivee: false);

        $this->assertSame([], app(OrderConfirmationService::class)->blockers($draft));

        app(OrderConfirmationService::class)->confirm($draft, $client);

        $booking = Booking::firstOrFail();

        $this->assertFalse($booking->estUneCourse());
        $this->assertNull($booking->dropoff_lat);
    }

    public function test_le_service_d_itineraire_rend_toujours_une_route(): void
    {
        $route = app(RoutingService::class)->route(50.8467, 4.3525, 50.9010, 4.4844);

        $this->assertGreaterThan(0, $route->distanceMeters);
        $this->assertCount(2, $route->points, 'Sans fournisseur d’itinéraire, le repli est la ligne droite.');
        $this->assertTrue($route->estUneLigneDroite());
    }

    /**
     * @return array{0: OrderDraft, 1: User}
     */
    private function panier(Trade $trade, bool $avecArrivee): array
    {
        $client = User::factory()->client()->create();
        $manager = app(OrderDraftManager::class);

        $draft = $manager->resumeOrCreate('jeton-'.uniqid());
        $draft->update([
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'lat' => 50.8467,
            'lng' => 4.3525,
            'scheduled_at' => Carbon::parse('2026-09-02 09:00:00'),
        ] + ($avecArrivee ? [
            'dropoff_address' => 'Aéroport de Bruxelles',
            'dropoff_lat' => 50.9010,
            'dropoff_lng' => 4.4844,
            'route_distance_m' => 12_400,
            'route_duration_s' => 1_200,
            'route_source' => 'mock',
        ] : []));

        /*
         * La zone est résolue ICI parce que `blockers()` la LIT sans la résoudre — c'est
         * `confirm()` qui appelle `ensureZoneFor()`. Un panier fabriqué à la main sans ce geste
         * décrirait une adresse hors couverture, et le test mesurerait ce défaut de fixture plutôt
         * que la règle qu'il prétend vérifier.
         */
        $zone = app(ZonePricingResolver::class)->ensureZoneFor($draft);
        $draft->refresh();

        // Absence de ligne = métier fermé : sans elle, la confirmation refuse pour la mauvaise raison.
        if ($zone) {
            $this->ouvrirAuCatalogue($trade, $zone);
        }

        $manager->itemFor($draft, $trade);

        return [$draft->fresh(), $client];
    }
}
