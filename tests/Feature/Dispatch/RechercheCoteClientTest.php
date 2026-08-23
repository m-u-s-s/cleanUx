<?php

namespace Tests\Feature\Dispatch;

use App\Enums\ProviderType;
use App\Models\AsapDispatchRequest;
use App\Models\Booking;
use App\Models\ProviderPresence;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use App\Services\Dispatch\DispatchEngine;
use App\Services\OrderEngine\AsapDispatchService;
use App\Support\Domain\AsapStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Dispatch\Concerns\OuvreLeCatalogue;
use Tests\TestCase;

/** CE QUE LE CLIENT VOIT ET DÉCIDE PENDANT QU'IL ATTEND. */
class RechercheCoteClientTest extends TestCase
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
            'name' => 'Zone client', 'slug' => 'zone-client-recherche', 'code' => 'ZCR',
            'status' => 'active', 'is_bookable' => true, 'is_visible' => true,
            'priority' => 10, 'coverage_type' => 'city_cluster',
        ]);

        $this->trade = Trade::create([
            'slug' => 'plomberie-client', 'code' => 'PLB-C', 'name' => 'Plomberie',
            'is_active' => true, 'sort_order' => 1, 'allows_asap' => true,
        ]);

        $this->ouvrirAuCatalogue($this->trade, $this->zone);

        Config::set('dispatch.waves.initial_radius_m', 5000);
        Config::set('dispatch.waves.step_m', 5000);
        Config::set('dispatch.waves.max_radius_m', 20000);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function recherche(): AsapDispatchRequest
    {
        $booking = Booking::factory()->create([
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
        ]);

        return app(DispatchEngine::class)->openImmediate($booking);
    }

    private function prestataire(float $lat, float $lng): User
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
            'current_lat' => $lat,
            'current_lng' => $lng,
            'heartbeat_at' => now(),
        ]);

        $user->trades()->syncWithoutDetaching([$this->trade->id]);

        return $user;
    }

    private function service(): AsapDispatchService
    {
        return app(AsapDispatchService::class);
    }

    // ─── Le rayon ────────────────────────────────────────────────────────────────────────────

    #[Test]
    public function elargir_pousse_la_borne_d_un_palier(): void
    {
        $this->prestataire(50.8480, 4.3540);
        $recherche = $this->recherche();

        $avant = (int) $recherche->radius_m;
        $apres = $this->service()->expand($recherche->fresh());

        $this->assertSame($avant + 5000, (int) $apres->radius_m);
        $this->assertGreaterThan((int) $recherche->wave, (int) $apres->wave);
    }

    #[Test]
    public function le_rayon_ne_depasse_jamais_son_plafond(): void
    {
        $this->prestataire(50.8480, 4.3540);
        $recherche = $this->recherche();

        // Élargir indéfiniment enverrait un prestataire à quarante kilomètres pour une
        // intervention d'une heure, et le client attendrait un trajet qu'il n'a pas demandé.
        foreach (range(1, 10) as $ignored) {
            $recherche = $this->service()->expand($recherche->fresh());
        }

        $this->assertSame(20000, (int) $recherche->radius_m);
    }

    // ─── L'échéance ──────────────────────────────────────────────────────────────────────────

    #[Test]
    public function l_echeance_de_recherche_est_ecrite_pas_recalculee(): void
    {
        // Un candidat, sinon la recherche s'épuise à l'ouverture et n'est plus « en recherche » :
        // l'échéance ne concerne que les recherches vivantes.
        $this->prestataire(50.8480, 4.3540);

        $recherche = $this->recherche();

        $this->assertNotNull(
            $recherche->deadline_at,
            'L’échéance est posée à l’ouverture : la recalculer la ferait bouger sous les yeux du client.',
        );

        $this->assertFalse($this->service()->hasTimedOut($recherche));

        $recherche->update(['deadline_at' => now()->subSecond()]);

        $this->assertTrue($this->service()->hasTimedOut($recherche->fresh()));
    }

    // ─── Jamais de cul-de-sac ────────────────────────────────────────────────────────────────

    #[Test]
    public function une_recherche_expiree_propose_toujours_une_suite(): void
    {
        $recherche = $this->recherche();
        $suites = $this->service()->waysForward($recherche->fresh());

        $this->assertNotEmpty($suites, 'Un écran d’attente qui finit sur un constat est un bug produit.');
        $this->assertContains('schedule', array_column($suites, 'key'));
    }

    #[Test]
    public function au_rayon_maximal_il_reste_deux_portes(): void
    {
        $recherche = $this->recherche();
        $recherche->update(['radius_m' => 20000, 'status' => AsapStatus::SEARCHING]);

        $cles = array_column($this->service()->waysForward($recherche->fresh()), 'key');

        // Élargir n'est plus possible : il reste le rendez-vous et l'alerte. Jamais zéro.
        $this->assertNotContains('expand', $cles);
        $this->assertContains('schedule', $cles);
        $this->assertContains('notify', $cles);
    }

    // ─── Le coût, annoncé avant ──────────────────────────────────────────────────────────────

    #[Test]
    public function annuler_pendant_la_recherche_est_toujours_gratuit(): void
    {
        $recherche = $this->recherche();
        $recherche->update(['status' => AsapStatus::SEARCHING]);

        $devis = $this->service()->quoteCancellation($recherche->fresh());

        $this->assertTrue($devis['free']);
        $this->assertSame(0, $devis['fee_cents']);
    }

    #[Test]
    public function le_montant_annonce_est_celui_applique(): void
    {
        $recherche = $this->recherche();

        // Le professionnel est en route et la fenêtre gratuite est passée : l'annulation coûte.
        $recherche->update([
            'status' => AsapStatus::ACCEPTED,
            'free_cancellation_until' => now()->subMinute(),
        ]);

        $devis = $this->service()->quoteCancellation($recherche->fresh());
        $this->assertFalse($devis['free']);

        $annulee = $this->service()->cancel($recherche->fresh(), 'client');

        $this->assertSame(
            $devis['fee_cents'],
            (int) $annulee->cancellation_fee_cents,
            'L’écran et la facture ne peuvent pas diverger : le montant est relu du même service.',
        );
    }

    #[Test]
    public function la_fenetre_gratuite_est_figee_a_l_acceptation(): void
    {
        $recherche = $this->recherche();
        $recherche->update([
            'status' => AsapStatus::ACCEPTED,
            'free_cancellation_until' => now()->addMinutes(3),
        ]);

        // Un changement de configuration après coup ne doit pas raccourcir une fenêtre annoncée.
        Config::set('order_engine.asap_free_cancellation_minutes', 0);

        $this->assertTrue($this->service()->quoteCancellation($recherche->fresh())['free']);
    }

    // ─── Les états ───────────────────────────────────────────────────────────────────────────

    #[Test]
    public function l_etat_ne_saute_pas(): void
    {
        $recherche = $this->recherche();
        $recherche->update(['status' => AsapStatus::SEARCHING]);

        $this->expectException(ValidationException::class);
        $this->service()->transition($recherche->fresh(), AsapStatus::COMPLETED);
    }

    #[Test]
    public function une_intervention_commencee_ne_s_annule_plus(): void
    {
        $recherche = $this->recherche();
        $recherche->update(['status' => AsapStatus::IN_PROGRESS]);

        // Annuler ici priverait le prestataire d'un travail déjà fourni. Le litige se règle après.
        $this->expectException(ValidationException::class);
        $this->service()->cancel($recherche->fresh(), 'client');
    }

    #[Test]
    public function chaque_etat_laisse_son_horodatage(): void
    {
        $recherche = $this->recherche();
        $recherche->update(['status' => AsapStatus::ACCEPTED]);

        $enRoute = $this->service()->transition($recherche->fresh(), AsapStatus::EN_ROUTE);

        // L'écran d'attente dit « en route depuis 2 min » sans une jointure de plus à chaque
        // rafraîchissement.
        $this->assertNotNull($enRoute->en_route_at);
    }
}
