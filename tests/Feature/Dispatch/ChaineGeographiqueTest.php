<?php

namespace Tests\Feature\Dispatch;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\Booking;
use App\Models\OrderDraft;
use App\Models\PostalCode;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use App\Services\OrderEngine\OrderConfirmationService;
use App\Services\OrderEngine\PricingEngine;
use App\Services\OrderEngine\ZonePricingResolver;
use App\Support\Domain\OrderMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LA CHAÎNE GÉOGRAPHIQUE, D'UN BOUT À L'AUTRE — consignes 2, 5, 7 et 11.
 *
 * Elle était coupée au dernier maillon : le pivot `service_zone_postal_code` avait zéro ligne, le
 * panier ne portait ni code postal ni zone, et `PricingEngine` acceptait un `zone_multiplier` que
 * personne ne fournissait. Résultat : une grille de prix par zone qui existait en base et
 * n'atteignait jamais un client, et un dispatch qui devait redeviner la zone d'une adresse.
 *
 * CE QUI EST VÉRIFIÉ ICI est le trajet complet — code postal → zone → prix → réservation — sur des
 * données réellement semées. Un test qui poserait la zone à la main ne dirait rien du maillon qu'on
 * vient de réparer.
 */
class ChaineGeographiqueTest extends TestCase
{
    use RefreshDatabase;

    private ServiceZone $zone;

    private Trade $metier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = ServiceZone::create([
            'name' => 'Zone Liège',
            'slug' => 'zone-liege-test',
            'code' => 'TEST-LIE',
            'status' => 'active',
            'is_bookable' => true,
            'is_visible' => true,
            'priority' => 10,
            'coverage_type' => 'city_cluster',
        ]);

        $postal = PostalCode::create([
            'code' => '4000',
            'city_name' => 'Liège',
            'is_active' => true,
        ]);

        // LE PIVOT — c'est lui qui faisait défaut. `ZoneCoverageService` le consulte AVANT tout
        // repli : sans ligne, deux adresses de villes différentes obtenaient la même zone.
        DB::table('service_zone_postal_code')->insert([
            'service_zone_id' => $this->zone->id,
            'postal_code_id' => $postal->id,
        ]);

        $this->metier = Trade::create([
            'slug' => 'plomberie-test',
            'code' => 'PLUMB-T',
            'name' => 'Plomberie',
            'is_active' => true,
            'sort_order' => 1,
            'base_price_cents' => 5000,
            'allows_scheduled' => true,
            'allows_asap' => true,
        ]);
    }

    #[Test]
    public function le_code_postal_resout_la_zone(): void
    {
        $zone = app(ZonePricingResolver::class)->resolveZone('4000', 'Liège');

        $this->assertNotNull($zone, 'Le pivot service_zone_postal_code doit résoudre la zone.');
        $this->assertSame($this->zone->id, $zone->id);
    }

    #[Test]
    public function le_prix_vient_de_la_grille_de_la_zone(): void
    {
        TradeZonePricing::create([
            'trade_id' => $this->metier->id,
            'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 9000,
            'surge_multiplier' => '1.20',
            'is_active' => true,
        ]);

        $contexte = app(ZonePricingResolver::class)->pricingContext((int) $this->metier->id, (int) $this->zone->id);

        $this->assertSame(9000, $contexte['zone_base_cents']);
        $this->assertSame(1.2, $contexte['zone_multiplier']);

        $devis = app(PricingEngine::class)->quoteItem(
            $this->metier,
            collect(),
            [],
            ['mode' => OrderMode::SCHEDULED] + $contexte,
        );

        // 9000 (grille de zone, PAS les 5000 du métier) × 1,20.
        $this->assertSame(10800, $devis->minCents);
    }

    #[Test]
    public function le_mode_immediat_n_est_offert_que_si_la_zone_l_autorise(): void
    {
        $ligne = TradeZonePricing::create([
            'trade_id' => $this->metier->id,
            'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 5000,
            'surge_multiplier' => '1.00',
            'is_active' => true,
            'asap_enabled' => false,
        ]);

        $resolver = app(ZonePricingResolver::class);

        $this->assertFalse(
            $resolver->allowsImmediate($this->metier, (int) $this->zone->id),
            'Le métier autorise l’immédiat en général, mais pas dans cette zone.',
        );

        // Le basculement admin change l'offre du parcours client SANS déploiement.
        $ligne->update(['asap_enabled' => true]);

        $this->assertTrue($resolver->allowsImmediate($this->metier->fresh(), (int) $this->zone->id));
    }

    #[Test]
    public function un_metier_ferme_dans_la_zone_ne_fait_jamais_d_immediat(): void
    {
        TradeZonePricing::create([
            'trade_id' => $this->metier->id,
            'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 5000,
            'surge_multiplier' => '1.00',
            // Fermé, mais l'immédiat resté coché d'une ouverture précédente : la fermeture doit
            // primer, sans quoi un métier retiré du catalogue continuerait de recevoir des courses.
            'is_active' => false,
            'asap_enabled' => true,
        ]);

        $this->assertFalse(app(ZonePricingResolver::class)->allowsImmediate($this->metier, (int) $this->zone->id));
    }

    #[Test]
    public function une_zone_non_couverte_bloque_la_confirmation(): void
    {
        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $panier = OrderDraft::create([
            'reference' => 'CLX-TEST1',
            'client_id' => $client->id,
            'mode' => OrderMode::SCHEDULED,
            'status' => 'draft',
            'address' => 'Rue inconnue 1, Nulle-Part',
            'lat' => 50.0,
            'lng' => 5.0,
            // Pas de zone résolue : le géocodeur a bien situé l'adresse, mais on n'y intervient pas.
            'service_zone_id' => null,
        ]);

        $panier->items()->create([
            'trade_id' => $this->metier->id,
            'sequence' => 1,
            'status' => 'draft',
        ]);

        $blocages = app(OrderConfirmationService::class)->blockers($panier->fresh());

        $this->assertNotEmpty($blocages, 'Sans zone, la confirmation doit être refusée AVANT le clic.');
        $this->assertStringContainsString('adresse', mb_strtolower(implode(' ', $blocages)));
    }

    #[Test]
    public function la_reservation_confirmee_porte_metier_zone_et_code_postal(): void
    {
        TradeZonePricing::create([
            'trade_id' => $this->metier->id,
            'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 5000,
            'surge_multiplier' => '1.00',
            'is_active' => true,
        ]);

        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $panier = OrderDraft::create([
            'reference' => 'CLX-TEST2',
            'client_id' => $client->id,
            'mode' => OrderMode::SCHEDULED,
            'status' => 'draft',
            'address' => 'Rue Saint-Gilles 1, 4000 Liège',
            'lat' => 50.6326,
            'lng' => 5.5797,
            'postal_code' => '4000',
            'service_zone_id' => $this->zone->id,
        ]);

        $panier->items()->create([
            'trade_id' => $this->metier->id,
            'sequence' => 1,
            'status' => 'draft',
        ]);

        app(OrderConfirmationService::class)->confirm($panier->fresh(), $client);

        $reservation = Booking::query()->where('client_id', $client->id)->firstOrFail();

        // LES TROIS COLONNES QUI MANQUAIENT AU DISPATCH. Sans elles, la requête candidate ne peut
        // imposer ni le métier ni la zone : c'est la porte par laquelle un peintre reçoit du
        // babysitting.
        $this->assertSame($this->metier->id, (int) $reservation->trade_id);
        $this->assertSame($this->zone->id, (int) $reservation->service_zone_id);
        $this->assertSame('4000', $reservation->postal_code);
    }

    #[Test]
    public function le_parcours_resout_la_zone_et_la_retient_sur_le_panier(): void
    {
        TradeZonePricing::create([
            'trade_id' => $this->metier->id,
            'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 5000,
            'surge_multiplier' => '1.00',
            'is_active' => true,
        ]);

        $client = User::factory()->create(['role' => User::ROLE_CLIENT]);

        $composant = Livewire::actingAs($client)->test(OrderJourney::class);
        $composant->instance()->selectTrade($this->metier->id);

        // Le géocodeur de test résout ce qu'il peut ; on vérifie que le composant ÉCRIT bien ce
        // qu'il a résolu — c'est le maillon réparé, pas la qualité du géocodage.
        $composant->set('address', 'Rue Saint-Gilles 1, 4000 Liège');

        $panier = $composant->instance()->draft();

        $this->assertSame(
            $panier->postal_code,
            $composant->instance()->postalCode,
            'Le panier doit retenir le code postal résolu pendant le parcours.',
        );
        $this->assertSame(
            $panier->service_zone_id !== null ? (int) $panier->service_zone_id : null,
            $composant->instance()->serviceZoneId,
            'Le panier doit retenir la zone résolue pendant le parcours.',
        );
    }
}
