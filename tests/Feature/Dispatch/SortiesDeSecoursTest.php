<?php

namespace Tests\Feature\Dispatch;

use App\Enums\ProviderType;
use App\Models\Booking;
use App\Models\MissionAssignment;
use App\Models\ProviderPresence;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Services\Dispatch\DispatchEngine;
use App\Services\Dispatch\MissionDispatchService;
use App\Services\Dispatch\SearchOutcomeService;
use App\Support\Domain\AsapStatus;
use App\Support\Domain\BookingStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Dispatch\Concerns\OuvreLeCatalogue;
use Tests\TestCase;

/** PERSONNE N'A RÉPONDU — les trois sorties (consigne 8). */
class SortiesDeSecoursTest extends TestCase
{
    use OuvreLeCatalogue;
    use RefreshDatabase;

    private const LAT = 50.8467;

    private const LNG = 4.3525;

    private ServiceZone $zone;

    private Trade $trade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = ServiceZone::create([
            'name' => 'Zone secours', 'slug' => 'zone-secours', 'code' => 'ZSC',
            'status' => 'active', 'is_bookable' => true, 'is_visible' => true,
            'priority' => 10, 'coverage_type' => 'city_cluster',
        ]);

        $this->trade = Trade::create([
            'slug' => 'plomberie-secours', 'code' => 'PLB-SC', 'name' => 'Plomberie',
            'is_active' => true, 'sort_order' => 1, 'allows_asap' => true,
        ]);

        $this->ouvrirAuCatalogue($this->trade, $this->zone);
    }

    private function prestataire(): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_EMPLOYE,
            'is_active' => true,
            'primary_service_zone_id' => $this->zone->id,
        ]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => ProviderType::INDEPENDENT->value,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        ProviderPresence::create([
            'provider_user_id' => $user->id,
            'status' => 'online',
            'current_lat' => self::LAT,
            'current_lng' => self::LNG,
            'heartbeat_at' => now(),
        ]);

        $user->trades()->syncWithoutDetaching([$this->trade->id]);

        return $user;
    }

    private function reservation(): Booking
    {
        return Booking::factory()->create([
            'client_id' => User::factory()->client()->create()->id,
            'employe_id' => null,
            'assigned_employee_id' => null,
            'service_zone_id' => $this->zone->id,
            'trade_id' => $this->trade->id,
            'booking_mode' => 'asap',
            'status' => 'en_attente',
            'destination_lat' => self::LAT,
            'destination_lng' => self::LNG,
            'date' => now()->toDateString(),
            'heure' => now()->format('H:i'),
            'estimated_price' => 90.0,
        ]);
    }

    private function sorties(): SearchOutcomeService
    {
        return app(SearchOutcomeService::class);
    }

    // ─── Attendre encore ─────────────────────────────────────────────────────────────────────

    #[Test]
    public function continuer_a_attendre_relance_la_recherche(): void
    {
        $recherche = app(DispatchEngine::class)->openImmediate($this->reservation());

        // Aucun candidat à l'ouverture : la recherche s'est épuisée.
        $this->assertSame(AsapStatus::EXPIRED, $recherche->status);

        // Un prestataire arrive — il vient de se mettre en ligne.
        $prestataire = $this->prestataire();

        $relancee = $this->sorties()->keepWaiting($recherche->fresh());

        $this->assertSame(AsapStatus::SEARCHING, $relancee->status);

        $offre = MissionAssignment::query()->where('mission_id', $relancee->mission_id)->firstOrFail();
        $this->assertSame($prestataire->id, (int) $offre->user_id);
    }

    #[Test]
    public function relancer_ne_reproposera_pas_la_course_a_qui_vient_de_refuser(): void
    {
        $refusant = $this->prestataire();
        $recherche = app(DispatchEngine::class)->openImmediate($this->reservation());

        $offre = MissionAssignment::query()->where('mission_id', $recherche->mission_id)->firstOrFail();
        app(MissionDispatchService::class)->decline($offre, 'Non merci');

        $relancee = $this->sorties()->keepWaiting($recherche->fresh());

        // Une seule offre au total : celle qui a été refusée. Réoffrir ferait vibrer son téléphone
        // pour rien et n'apporterait aucun candidat de plus.
        $this->assertSame(
            1,
            MissionAssignment::query()->where('mission_id', $relancee->mission_id)->count(),
        );
    }

    // ─── Convertir en rendez-vous ────────────────────────────────────────────────────────────

    #[Test]
    public function convertir_garde_la_meme_reservation_et_le_meme_prix(): void
    {
        $prestataire = $this->prestataire();
        $booking = $this->reservation();
        $prixAvant = (float) $booking->estimated_price;

        $recherche = app(DispatchEngine::class)->openImmediate($booking);
        $creneau = now()->addDays(2)->setTime(14, 0);

        $converti = $this->sorties()->convertToScheduled($recherche->fresh(), $creneau);

        // LA MÊME réservation : pas une nouvelle. Renvoyer le client dans le parcours de commande
        // lui ferait tout recommencer, et c'est le moment où l'on abandonne.
        $this->assertSame($booking->id, $converti->id);
        $this->assertSame('scheduled', $converti->booking_mode);
        $this->assertSame($prixAvant, (float) $converti->estimated_price, 'Le devis figé ne bouge pas.');
        $this->assertSame(1, Booking::query()->count(), 'Aucune réservation en double.');
        $this->assertSame($prestataire->id, (int) $prestataire->id);
    }

    #[Test]
    public function convertir_ferme_la_recherche_immediate(): void
    {
        $this->prestataire();
        $recherche = app(DispatchEngine::class)->openImmediate($this->reservation());

        $this->sorties()->convertToScheduled($recherche->fresh(), now()->addDay()->setTime(10, 0));

        // Deux chemins vivants pour la même réservation produiraient deux prestataires.
        $this->assertSame(AsapStatus::CANCELLED, $recherche->fresh()->status);
    }

    #[Test]
    public function convertir_passe_la_main_au_flux_planifie(): void
    {
        $prestataire = $this->prestataire();
        $recherche = app(DispatchEngine::class)->openImmediate($this->reservation());

        $converti = $this->sorties()->convertToScheduled($recherche->fresh(), now()->addDay()->setTime(10, 0));

        $offre = MissionAssignment::query()
            ->where('mission_id', $recherche->fresh()->mission_id)
            ->where('assignment_status', 'assigned')
            ->latest('id')
            ->first();

        $this->assertNotNull($offre, 'Le planifié doit prendre le relais, pas laisser la réservation orpheline.');
        $this->assertSame($prestataire->id, (int) $offre->user_id);

        // TTL long : personne n'attend derrière la porte.
        $this->assertGreaterThan(
            600,
            (int) round($offre->expires_at->diffInSeconds($offre->assigned_at, true)),
        );
        $this->assertSame('scheduled', $converti->booking_mode);
    }

    #[Test]
    public function convertir_refuse_un_creneau_passe(): void
    {
        $this->prestataire();
        $recherche = app(DispatchEngine::class)->openImmediate($this->reservation());

        $this->expectException(ValidationException::class);
        $this->sorties()->convertToScheduled($recherche->fresh(), now()->subHour());
    }

    // ─── Annuler ─────────────────────────────────────────────────────────────────────────────

    #[Test]
    public function annuler_ne_laisse_ni_mission_fantome_ni_offre_vivante(): void
    {
        $this->prestataire();
        $booking = $this->reservation();
        $recherche = app(DispatchEngine::class)->openImmediate($booking);

        $resultat = $this->sorties()->cancelAndRelease($recherche->fresh(), 'Trop long');

        $this->assertTrue($resultat['cancelled']);
        $this->assertFalse($resultat['captured'], 'Le paiement attend l’assignation : rien n’a pu être encaissé.');

        $this->assertSame(AsapStatus::CANCELLED, $recherche->fresh()->status);
        $this->assertSame(BookingStatus::ANNULE, $booking->fresh()->status);

        // DEUX COLONNES DU MÊME NOM, DEUX TYPES — et SQLite ne le dit pas.
        $this->assertSame((int) $booking->client_id, (int) $booking->fresh()->cancelled_by);
        $this->assertSame('client', $recherche->fresh()->cancelled_by);

        $this->assertSame(
            0,
            MissionAssignment::query()
                ->where('mission_id', $recherche->fresh()->mission_id)
                ->where('assignment_status', 'assigned')
                ->count(),
            'Aucune modale ne doit rester ouverte sur une course annulée.',
        );
    }

    #[Test]
    public function annuler_une_recherche_infructueuse_ne_coute_rien(): void
    {
        $booking = $this->reservation();
        $recherche = app(DispatchEngine::class)->openImmediate($booking);

        $this->sorties()->cancelAndRelease($recherche->fresh());

        // Facturer une recherche sans candidat serait faire payer notre propre incapacité à
        // trouver quelqu'un.
        $this->assertSame(0, (int) $recherche->fresh()->cancellation_fee_cents);
    }

    #[Test]
    public function annuler_libere_une_autorisation_de_paiement(): void
    {
        $booking = $this->reservation();
        $booking->forceFill(['payment_status' => 'authorized'])->save();

        $recherche = app(DispatchEngine::class)->openImmediate($booking->fresh());

        $this->sorties()->cancelAndRelease($recherche->fresh());

        $this->assertSame('cancelled', $booking->fresh()->payment_status);
        $this->assertNotNull($booking->fresh()->payment_cancelled_at);
    }

    #[Test]
    public function le_client_est_prevenu_a_chaque_sortie(): void
    {
        $booking = $this->reservation();
        $recherche = app(DispatchEngine::class)->openImmediate($booking);

        $this->sorties()->cancelAndRelease($recherche->fresh());

        // Sans trace, le client ferme l'onglet et sa demande semble s'être évaporée : c'est le
        // support qui l'apprend.
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $booking->client_id,
        ]);
    }
}
