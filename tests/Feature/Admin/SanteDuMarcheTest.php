<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\MarketplaceHealthCenter;
use App\Models\AsapDispatchRequest;
use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use App\Services\Admin\DemandForecastService;
use App\Services\Admin\FailedSearchRecoveryService;
use App\Services\Admin\MarketplaceHealthService;
use App\Services\Admin\SurgeOverviewService;
use App\Services\Moderation\AiModerationProvider;
use App\Support\Domain\AsapStatus;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * PHASE 6 — SURGE PILOTÉ (E28), PRÉVISION (E29), SANTÉ DU MARCHÉ (E30), RATTRAPAGE (E31) ET
 * MODÉRATION IA (E32).
 *
 * CE QUE CE FICHIER PROTÈGE EN PRIORITÉ, ce sont les cinq décisions qui font que ces modules
 * informent au lieu de tromper :
 *
 *   1. le taux « sans candidat » se calcule sur les recherches ÉPUISÉES, pas sur les annulées —
 *      un client qui renonce n'est pas un marché qui manque de bras ;
 *   2. on ne PROJETTE PAS sous quatre semaines d'observation : la moyenne décrirait un accident ;
 *   3. on ne RELANCE PAS une recherche encore ouverte — deux prestataires se déplaceraient ;
 *   4. le geste commercial est NOMINATIF : un code générique fuit ;
 *   5. l'IA ne bloque JAMAIS seule, et son indisponibilité ne vaut pas validation.
 */
class SanteDuMarcheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    private function admin(): User
    {
        return User::factory()->create([
            'platform_role' => 'admin',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }

    /** Une recherche épuisée sur une zone donnée. */
    private function rechercheEpuisee(?ServiceZone $zone = null, ?User $client = null): AsapDispatchRequest
    {
        $booking = Booking::factory()->create([
            'service_zone_id' => $zone?->id,
            'client_id' => $client?->id,
        ]);

        return AsapDispatchRequest::query()->create([
            'booking_id' => $booking->id,
            // NOT NULL : le métier est un invariant de la recherche — sans lui, le dispatch ne
            // cherche personne plutôt que de chercher n'importe qui.
            'trade_id' => $booking->trade_id ?? Trade::factory()->create()->id,
            'status' => AsapStatus::EXPIRED,
            'lat' => 50.85,
            'lng' => 4.35,
            'radius_m' => 5000,
        ]);
    }

    // ─── Les portes ──────────────────────────────────────────────────────────

    #[Test]
    public function l_ecran_de_sante_repond(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.marketplace.health'))
            ->assertOk();
    }

    #[Test]
    public function le_module_figure_au_repertoire(): void
    {
        $entrees = collect(config('modules.catalogue'))
            ->where('context', 'admin')
            ->pluck('route')
            ->all();

        $this->assertContains('admin.marketplace.health', $entrees);
    }

    // ─── E30 : la santé du marché ────────────────────────────────────────────

    #[Test]
    public function le_taux_sans_candidat_ne_compte_que_les_epuisees(): void
    {
        $zone = ServiceZone::factory()->create();

        $this->rechercheEpuisee($zone);

        // Une ANNULÉE : un client qui renonce n'est pas un marché qui manque de bras.
        $annulee = Booking::factory()->create(['service_zone_id' => $zone->id]);

        AsapDispatchRequest::query()->create([
            'booking_id' => $annulee->id,
            'trade_id' => $annulee->trade_id ?? Trade::factory()->create()->id,
            'status' => AsapStatus::CANCELLED,
            'lat' => 50.85,
            'lng' => 4.35,
            'radius_m' => 5000,
        ]);

        $ligne = collect(app(MarketplaceHealthService::class)->parZone())
            ->firstWhere('zone_id', $zone->id);

        $this->assertSame(2, $ligne['searches_count']);
        $this->assertSame(1, $ligne['exhausted_count']);
        $this->assertSame(50.0, $ligne['no_candidate_rate']);
    }

    #[Test]
    public function une_zone_sans_demande_reste_visible(): void
    {
        $zone = ServiceZone::factory()->create(['name' => 'Zone jamais servie']);

        $lignes = collect(app(MarketplaceHealthService::class)->parZone());

        /*
         * LA MASQUER FERAIT DISPARAÎTRE DU TABLEAU exactement les zones où l'on n'a jamais rien
         * vendu — celles qu'il faut regarder.
         */
        $ligne = $lignes->firstWhere('zone_id', $zone->id);

        $this->assertNotNull($ligne);
        $this->assertFalse($ligne['has_data']);
        $this->assertNull($ligne['no_candidate_rate']);
    }

    #[Test]
    public function le_diagnostic_distingue_absence_et_refus(): void
    {
        $recherche = $this->rechercheEpuisee();

        // Sans aucune offre envoyée : personne n'était là. Les deux cas appellent des actions
        // opposées — recruter, ou comprendre pourquoi la course est refusée.
        $this->assertSame(
            'no_provider_found',
            app(MarketplaceHealthService::class)->diagnostiquer($recherche),
        );
    }

    // ─── E29 : la prévision ──────────────────────────────────────────────────

    #[Test]
    public function on_ne_projette_pas_sans_historique(): void
    {
        $zone = ServiceZone::factory()->create();

        Booking::factory()->create([
            'service_zone_id' => $zone->id,
            'scheduled_at' => Carbon::now()->subDays(3),
        ]);

        $lignes = app(DemandForecastService::class)->projection();

        /*
         * EN DESSOUS DE QUATRE SEMAINES, la moyenne mobile décrit un accident, pas une tendance :
         * `has_enough_history` à faux vaut mieux qu'un chiffre lu comme un objectif.
         */
        $this->assertNotEmpty($lignes);
        $this->assertFalse($lignes[0]['has_enough_history']);
    }

    #[Test]
    public function la_projection_porte_son_intervalle(): void
    {
        $zone = ServiceZone::factory()->create();

        // Quatre semaines d'observation, deux réservations par semaine.
        foreach (range(1, 4) as $semaine) {
            Booking::factory()->count(2)->create([
                'service_zone_id' => $zone->id,
                'scheduled_at' => Carbon::now()->subWeeks($semaine)->startOfWeek()->addDays(2),
            ]);
        }

        $ligne = collect(app(DemandForecastService::class)->projection())
            ->firstWhere('zone_id', $zone->id);

        $this->assertTrue($ligne['has_enough_history']);
        $this->assertSame(2, $ligne['next_week_forecast']);
        // L'intervalle est le chiffre honnête : une projection nue ferait prendre une
        // extrapolation pour une mesure.
        $this->assertArrayHasKey('forecast_low', $ligne);
        $this->assertArrayHasKey('forecast_high', $ligne);
    }

    // ─── E31 : le rattrapage ─────────────────────────────────────────────────

    #[Test]
    public function on_ne_relance_pas_une_recherche_encore_ouverte(): void
    {
        $admin = $this->admin();

        $enCours = Booking::factory()->create();

        $ouverte = AsapDispatchRequest::query()->create([
            'booking_id' => $enCours->id,
            'trade_id' => $enCours->trade_id ?? Trade::factory()->create()->id,
            'status' => AsapStatus::SEARCHING,
            'lat' => 50.85,
            'lng' => 4.35,
            'radius_m' => 5000,
        ]);

        /*
         * LE MOTEUR EN OUVRIRAIT UNE SECONDE sur la même réservation, et deux prestataires se
         * déplaceraient — le défaut exact que la porte amont unique a corrigé.
         */
        $this->expectException(DomainException::class);

        app(FailedSearchRecoveryService::class)->relancer($ouverte, $admin);
    }

    #[Test]
    public function le_geste_commercial_est_nominatif(): void
    {
        $admin = $this->admin();
        $client = User::factory()->client()->create();

        $recherche = $this->rechercheEpuisee(null, $client);

        $code = app(FailedSearchRecoveryService::class)->offrirUnGeste($recherche, $admin, 20);

        /*
         * UN CODE GÉNÉRIQUE FUIT : il se retrouve sur un forum, et un dédommagement devient une
         * promotion publique que personne n'a budgétée. `issued_to_user_id` est la colonne que le
         * module lit réellement — se tromper aurait produit un code nominatif EN APPARENCE.
         */
        $this->assertSame($client->id, $code->issued_to_user_id);
        $this->assertSame(1, (int) $code->max_total_uses);
        $this->assertSame(PromoCode::STATUS_ACTIVE, $code->status);

        // Et la trace vit sur la recherche : sans elle, on offrirait deux fois.
        $this->assertSame($code->code, data_get($recherche->fresh()->metadata, 'gesture_code'));
    }

    #[Test]
    public function un_geste_demesure_est_refuse(): void
    {
        $admin = $this->admin();
        $recherche = $this->rechercheEpuisee(null, User::factory()->client()->create());

        // Au-delà de la moitié, ce n'est plus un geste : c'est une décision commerciale qui se
        // prend ailleurs qu'en un clic.
        $this->expectException(DomainException::class);

        app(FailedSearchRecoveryService::class)->offrirUnGeste($recherche, $admin, 80);
    }

    #[Test]
    public function l_ecran_refuse_de_relancer_et_le_dit(): void
    {
        $admin = $this->admin();

        $enCours = Booking::factory()->create();

        $ouverte = AsapDispatchRequest::query()->create([
            'booking_id' => $enCours->id,
            'trade_id' => $enCours->trade_id ?? Trade::factory()->create()->id,
            'status' => AsapStatus::SEARCHING,
            'lat' => 50.85,
            'lng' => 4.35,
            'radius_m' => 5000,
        ]);

        // Une règle métier est une réponse à LIRE, pas une panne.
        Livewire::actingAs($admin)
            ->test(MarketplaceHealthCenter::class)
            ->call('relancer', $ouverte->id)
            ->assertSet('refus', 'Cette recherche n’est pas épuisée : elle suit encore son cours.');
    }

    // ─── E28 : la carte des majorations ──────────────────────────────────────

    #[Test]
    public function la_carte_signale_les_depassements_de_plafond(): void
    {
        $zone = ServiceZone::factory()->create();
        $metier = Trade::factory()->create();

        TradeZonePricing::query()->create([
            'trade_id' => $metier->id,
            'service_zone_id' => $zone->id,
            'base_rate_cents' => 3000,
            // Au-dessus du plafond : le moteur la ramènera, l'écran doit le dire.
            'surge_multiplier' => '5.00',
            'is_active' => true,
        ]);

        $carte = app(SurgeOverviewService::class)->carte();

        /*
         * LE DÉPASSEMENT EST SIGNALÉ, PAS CORRIGÉ. Sans ce rappel, l'écran afficherait 5,00 et le
         * client paierait 3,00 : deux chiffres pour la même chose, et personne pour expliquer.
         */
        $this->assertSame(1, $carte['exceeding_cap_count']);
        $this->assertTrue($carte['rows'][0]['exceeds_cap']);
        $this->assertSame($carte['cap'], $carte['rows'][0]['effective_multiplier']);
    }

    #[Test]
    public function la_carte_montre_aussi_les_grilles_neutres(): void
    {
        $zone = ServiceZone::factory()->create();
        $metier = Trade::factory()->create();

        TradeZonePricing::query()->create([
            'trade_id' => $metier->id,
            'service_zone_id' => $zone->id,
            'base_rate_cents' => 3000,
            'surge_multiplier' => '1.00',
            'is_active' => true,
        ]);

        $carte = app(SurgeOverviewService::class)->carte();

        // Ce qu'il faut voir est la PROPORTION de lignes majorées : les masquer donnerait
        // l'impression que seules quelques zones le sont.
        $this->assertSame(1, $carte['rows_count']);
        $this->assertSame(0, $carte['surged_count']);
    }

    // ─── E32 : la modération IA ──────────────────────────────────────────────

    #[Test]
    public function la_moderation_ia_est_coupee_par_defaut(): void
    {
        $verdict = app(AiModerationProvider::class)->analyser('un message quelconque');

        /*
         * NI BLANC-SEING, NI CONDAMNATION. Rendre `clean` ferait passer l'indisponibilité pour une
         * validation — exactement le mensonge à éviter dans un module de modération.
         */
        $this->assertSame(AiModerationProvider::VERDICT_INCONNU, $verdict['verdict']);
        $this->assertSame(0.0, $verdict['confidence']);
        $this->assertSame('feature_off', $verdict['reason']);
    }

    #[Test]
    public function sans_cle_l_ia_reste_indisponible_meme_drapeau_leve(): void
    {
        config(['features.ai_moderation' => true, 'services.anthropic.key' => '']);

        $verdict = app(AiModerationProvider::class)->analyser('un message quelconque');

        // Un service qui dépend d'un tiers doit pouvoir disparaître sans emporter la messagerie :
        // la chaîne déterministe continue seule.
        $this->assertSame(AiModerationProvider::VERDICT_INCONNU, $verdict['verdict']);
    }

    #[Test]
    public function le_centre_est_ferme_aux_non_admins(): void
    {
        $this->actingAs(User::factory()->client()->create())
            ->get(route('admin.marketplace.health'))
            ->assertForbidden();
    }
}
