<?php

namespace Tests\Feature\Api\Client;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\ClientPlace;
use App\Models\Country;
use App\Models\OrderDraft;
use App\Models\Sector;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use App\Support\Domain\OrderDraftStatus;
use App\Support\Domain\OrderMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** LE CATALOGUE NATIF LIT CE QUE LE MOTEUR ACCEPTE — ni plus, ni moins, ni à un autre prix. */
class CatalogueApiTest extends TestCase
{
    use RefreshDatabase;

    private ServiceZone $zone;

    private Sector $urgent;

    private Sector $lent;

    private Trade $plomberie;

    private Trade $ravalement;

    protected function setUp(): void
    {
        parent::setUp();
        config(['fx.base_currency' => 'EUR']);

        $this->zone = ServiceZone::create([
            'name' => 'Zone api', 'slug' => 'zone-api', 'code' => 'ZAP',
            'status' => 'active', 'is_bookable' => true, 'is_visible' => true,
            'priority' => 10, 'coverage_type' => 'city_cluster',
        ]);

        $this->urgent = Sector::create(['name' => 'Dépannage', 'slug' => 'depannage-api', 'icon' => 'hammer', 'is_active' => true, 'sort_order' => 1]);
        $this->lent = Sector::create(['name' => 'Gros œuvre', 'slug' => 'gros-oeuvre-api', 'icon' => 'hammer', 'is_active' => true, 'sort_order' => 2]);

        $this->plomberie = Trade::create([
            'sector_id' => $this->urgent->id, 'slug' => 'plomberie-api', 'code' => 'PLB-AP', 'name' => 'Plomberie',
            'icon' => 'wrench', 'short_description' => 'Fuites, débouchages, sanitaires',
            'is_active' => true, 'sort_order' => 1, 'allows_scheduled' => true, 'allows_asap' => true, 'allows_bundle' => true,
            'base_price_cents' => 8500, 'hourly_billing' => false,
        ]);
        $this->ravalement = Trade::create([
            'sector_id' => $this->lent->id, 'slug' => 'ravalement-api', 'code' => 'RAV-AP', 'name' => 'Ravalement',
            'icon' => 'home', 'is_active' => true, 'sort_order' => 1,
            'allows_scheduled' => true, 'allows_asap' => false, 'allows_bundle' => true,
        ]);
    }

    private function client(?string $langue = null): User
    {
        $client = User::factory()->client()->create();

        if ($langue !== null) {
            $client->forceFill(['locale' => $langue])->save();
        }

        return $client->fresh();
    }

    private function lieuParDefaut(User $client): void
    {
        ClientPlace::factory()->parDefaut()->create(['user_id' => $client->id, 'service_zone_id' => $this->zone->id]);
    }

    /** Une seconde zone, dans un autre pays : sa devise suffit à dire quelle zone l'API a lue. */
    private function zoneDuPanier(): ServiceZone
    {
        $maroc = Country::factory()->create(['currency_code' => 'MAD']);

        return ServiceZone::create([
            'name' => 'Zone du panier', 'slug' => 'zone-panier-api', 'code' => 'ZPN',
            'status' => 'active', 'is_bookable' => true, 'is_visible' => true,
            'priority' => 20, 'coverage_type' => 'city_cluster', 'country_id' => $maroc->id,
        ]);
    }

    /** @param  array<string, mixed>  $attributs */
    private function panier(User $client, array $attributs): OrderDraft
    {
        return OrderDraft::create($attributs + [
            'reference' => OrderDraft::generateReference(),
            'client_id' => $client->id,
            'session_token' => Str::random(48),
            'mode' => OrderMode::SCHEDULED,
            'status' => OrderDraftStatus::DRAFT,
            'source' => 'web',
        ]);
    }

    #[Test]
    public function sans_jeton_la_route_refuse(): void
    {
        $this->getJson('/api/client/catalogue?mode=asap')->assertUnauthorized();
    }

    #[Test]
    public function temoin_un_client_authentifie_recoit_le_catalogue(): void
    {
        $this->actingAs($this->client(), 'sanctum')->getJson('/api/client/catalogue?mode=asap')->assertOk();
    }

    #[Test]
    public function seuls_l_immediat_et_le_rendez_vous_ont_un_catalogue_natif(): void
    {
        $client = $this->client();

        $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode=bundle')->assertStatus(422);
        $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode=n-importe-quoi')->assertStatus(422);
        $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue')->assertStatus(422);

        // Témoin : le rendez-vous est accepté.
        $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode=scheduled')->assertOk();
    }

    #[Test]
    public function le_contrat_de_reponse_sans_lieu_par_defaut(): void
    {
        $reponse = $this->actingAs($this->client(), 'sanctum')->getJson('/api/client/catalogue?mode=asap');

        $reponse->assertOk()->assertExactJson([
            'mode' => 'asap',
            'zone_known' => false,
            'currency' => 'EUR',
            'sectors' => [[
                'slug' => 'depannage-api',
                'name' => 'Dépannage',
                'icon' => 'hammer',
                'trades' => [[
                    'slug' => 'plomberie-api',
                    'name' => 'Plomberie',
                    'icon' => 'wrench',
                    'short_description' => 'Fuites, débouchages, sanitaires',
                    // Le minimum du moteur en immédiat : 8500 × 1,30.
                    'floor_price_cents' => 11050,
                    'hourly' => false,
                ]],
            ]],
        ]);
    }

    #[Test]
    public function le_lieu_par_defaut_fixe_la_zone_et_son_tarif(): void
    {
        $client = $this->client();
        $this->lieuParDefaut($client);
        TradeZonePricing::create([
            'trade_id' => $this->plomberie->id, 'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 9900, 'surge_multiplier' => '1.00', 'is_active' => true, 'asap_enabled' => true,
        ]);

        $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode=asap')
            ->assertOk()
            ->assertJsonPath('zone_known', true)
            // Le tarif de la zone, majoré de l'immédiat : 9900 × 1,30.
            ->assertJsonPath('sectors.0.trades.0.floor_price_cents', 12870);
    }

    #[Test]
    public function une_zone_fermee_a_l_immediat_vide_le_catalogue_immediat(): void
    {
        $client = $this->client();
        $this->lieuParDefaut($client);
        TradeZonePricing::create([
            'trade_id' => $this->plomberie->id, 'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 9900, 'surge_multiplier' => '1.00', 'is_active' => true, 'asap_enabled' => false,
        ]);

        $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode=asap')
            ->assertOk()->assertJsonPath('sectors', []);

        // Témoin : le rendez-vous, lui, garde les deux secteurs.
        $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode=scheduled')
            ->assertOk()->assertJsonCount(2, 'sectors');
    }

    #[Test]
    public function les_libelles_suivent_la_langue_du_compte(): void
    {
        $this->plomberie->setTranslation('name', 'nl', 'Loodgieter');

        $this->actingAs($this->client('nl'), 'sanctum')->getJson('/api/client/catalogue?mode=asap')
            ->assertJsonPath('sectors.0.trades.0.name', 'Loodgieter');

        // Témoin : un compte francophone garde le libellé d'origine.
        $this->actingAs($this->client('fr'), 'sanctum')->getJson('/api/client/catalogue?mode=asap')
            ->assertJsonPath('sectors.0.trades.0.name', 'Plomberie');
    }

    #[Test]
    public function l_application_voit_exactement_ce_que_propose_le_web(): void
    {
        $client = $this->client();
        $this->lieuParDefaut($client);
        TradeZonePricing::create([
            'trade_id' => $this->plomberie->id, 'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 9900, 'surge_multiplier' => '1.00', 'is_active' => true, 'asap_enabled' => true,
        ]);

        foreach ([OrderMode::ASAP, OrderMode::SCHEDULED] as $mode) {
            $api = $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode='.$mode)->json('sectors');

            $web = Livewire::actingAs($client)->test(OrderJourney::class)->call('chooseIntent', $mode);

            $this->assertSame(
                $web->instance()->sectors->pluck('slug')->all(),
                array_column($api, 'slug'),
                "Secteurs divergents en mode $mode",
            );

            foreach ($web->instance()->sectors as $index => $secteur) {
                $web->set('sectorId', $secteur->id);
                $this->assertSame(
                    $web->instance()->trades->pluck('slug')->all(),
                    array_column($api[$index]['trades'], 'slug'),
                    "Métiers divergents en mode $mode pour {$secteur->slug}",
                );
            }
        }
    }

    #[Test]
    public function le_plancher_annonce_est_le_minimum_du_devis_web_avant_toute_reponse(): void
    {
        $client = $this->client();
        $this->lieuParDefaut($client);
        TradeZonePricing::create([
            'trade_id' => $this->plomberie->id, 'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 9900, 'surge_multiplier' => '1.00', 'is_active' => true, 'asap_enabled' => true,
        ]);

        foreach ([OrderMode::ASAP => 12870, OrderMode::SCHEDULED => 9900] as $mode => $attendu) {
            // L'API d'abord : le montage du parcours web écrit le lieu par défaut sur le panier.
            $metier = $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode='.$mode)
                ->assertOk()
                ->assertJsonPath('sectors.0.trades.0.slug', 'plomberie-api')
                ->json('sectors.0.trades.0');

            $web = Livewire::actingAs($client)->test(OrderJourney::class)
                ->call('chooseIntent', $mode)
                ->call('selectTrade', $this->plomberie->id);

            // Témoins : le web chiffre bien CE métier, dans CE mode, dans CETTE zone, sans réponse.
            $this->assertSame($mode, $web->get('mode'));
            $this->assertSame($this->zone->id, $web->get('serviceZoneId'));
            $this->assertSame([], $web->get('answers'));
            $devis = $web->instance()->quote;
            $this->assertNotNull($devis);
            $this->assertFalse($devis->quoteOnly);

            $this->assertSame($devis->minCents, $metier['floor_price_cents'], "Plancher divergent du devis web en mode $mode");
            $this->assertSame($attendu, $metier['floor_price_cents']);
            $this->assertFalse($metier['hourly']);
        }
    }

    #[Test]
    public function un_panier_ouvert_avec_son_adresse_fixe_la_zone_comme_sur_le_web(): void
    {
        $client = $this->client();
        $this->lieuParDefaut($client);
        // Dans la zone du lieu par défaut, la plomberie accepte l'immédiat…
        TradeZonePricing::create([
            'trade_id' => $this->plomberie->id, 'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 9900, 'surge_multiplier' => '1.00', 'is_active' => true, 'asap_enabled' => true,
        ]);
        // … et dans celle du panier, non — à un autre tarif, dans une autre devise.
        $zoneDuPanier = $this->zoneDuPanier();
        TradeZonePricing::create([
            'trade_id' => $this->plomberie->id, 'service_zone_id' => $zoneDuPanier->id,
            'base_rate_cents' => 7700, 'surge_multiplier' => '1.00', 'is_active' => true, 'asap_enabled' => false,
        ]);
        $this->panier($client, ['address' => '12 rue du Panier', 'service_zone_id' => $zoneDuPanier->id]);

        $immediat = $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode=asap')
            ->assertOk()
            ->assertJsonPath('zone_known', true)
            ->assertJsonPath('currency', 'MAD')
            ->assertJsonPath('sectors', [])
            ->json('sectors');
        $rendezVous = $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode=scheduled')
            ->assertOk()
            ->assertJsonPath('currency', 'MAD')
            ->assertJsonPath('sectors.0.trades.0.floor_price_cents', 7700)
            ->json('sectors');

        // L'API lit le panier, elle n'en ouvre aucun.
        $this->assertSame(1, OrderDraft::query()->count());

        // Le web, pour ce même client, reprend ce même panier — et donc cette même zone.
        $web = Livewire::actingAs($client)->test(OrderJourney::class);
        $this->assertSame($zoneDuPanier->id, $web->get('serviceZoneId'));

        foreach ([OrderMode::ASAP => $immediat, OrderMode::SCHEDULED => $rendezVous] as $mode => $api) {
            $web->call('chooseIntent', $mode);
            $this->assertSame(
                $web->instance()->sectors->pluck('slug')->all(),
                array_column($api, 'slug'),
                "Secteurs divergents en mode $mode",
            );
        }
    }

    #[Test]
    public function temoin_un_panier_ouvert_sans_adresse_laisse_la_zone_au_lieu_par_defaut(): void
    {
        $client = $this->client();
        $this->lieuParDefaut($client);
        TradeZonePricing::create([
            'trade_id' => $this->plomberie->id, 'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 9900, 'surge_multiplier' => '1.00', 'is_active' => true, 'asap_enabled' => true,
        ]);
        // Une zone sans adresse n'est pas une adresse saisie : le web pré-remplit alors depuis le carnet.
        $this->panier($client, ['address' => null, 'service_zone_id' => $this->zoneDuPanier()->id]);

        $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode=asap')
            ->assertOk()
            ->assertJsonPath('zone_known', true)
            ->assertJsonPath('currency', 'EUR')
            ->assertJsonPath('sectors.0.trades.0.floor_price_cents', 12870);

        $this->assertSame($this->zone->id, Livewire::actingAs($client)->test(OrderJourney::class)->get('serviceZoneId'));
    }

    #[Test]
    public function temoin_un_panier_qui_n_est_plus_ouvert_est_ignore(): void
    {
        $client = $this->client();
        $this->lieuParDefaut($client);
        TradeZonePricing::create([
            'trade_id' => $this->plomberie->id, 'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 9900, 'surge_multiplier' => '1.00', 'is_active' => true, 'asap_enabled' => true,
        ]);
        $zoneDuPanier = $this->zoneDuPanier();
        $this->panier($client, ['address' => '12 rue du Panier', 'service_zone_id' => $zoneDuPanier->id, 'status' => OrderDraftStatus::CONVERTED]);
        $this->panier($client, ['address' => '14 rue du Panier', 'service_zone_id' => $zoneDuPanier->id, 'status' => OrderDraftStatus::ABANDONED]);

        $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode=asap')
            ->assertOk()
            ->assertJsonPath('zone_known', true)
            ->assertJsonPath('currency', 'EUR')
            ->assertJsonPath('sectors.0.trades.0.floor_price_cents', 12870);

        // Aucun panier ouvert : l'API n'en crée pas pour autant.
        $this->assertSame(0, OrderDraft::query()->open()->count());

        $this->assertSame($this->zone->id, Livewire::actingAs($client)->test(OrderJourney::class)->get('serviceZoneId'));
    }

    #[Test]
    public function le_nombre_de_requetes_ne_depend_pas_du_nombre_de_metiers(): void
    {
        $client = $this->client();
        $this->lieuParDefaut($client);

        $compter = function () use ($client): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode=scheduled')->assertOk();
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        $avant = $compter();

        foreach (range(1, 6) as $n) {
            Trade::create([
                'sector_id' => $this->urgent->id, 'slug' => "metier-$n-api", 'code' => "M$n-AP", 'name' => "Métier $n",
                'is_active' => true, 'sort_order' => 10 + $n, 'allows_scheduled' => true, 'allows_asap' => false, 'allows_bundle' => true,
            ]);
        }

        $this->assertSame($avant, $compter());
    }

    #[Test]
    public function les_secteurs_et_les_metiers_sont_tries_par_la_requete(): void
    {
        // La preuve porte sur la CLAUSE, pas seulement sur l'ordre des résultats obtenus : sous
        // SQLite, l'index (is_active, sort_order) trie déjà les lignes gratuitement, si bien
        // qu'un ->orderBy('sort_order') supprimé du contrôleur passerait inaperçu si on ne
        // vérifiait que le résultat plutôt que le SQL exécuté.
        $client = $this->client();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $reponse = $this->actingAs($client, 'sanctum')->getJson('/api/client/catalogue?mode=scheduled');
        $requetes = DB::getQueryLog();
        DB::disableQueryLog();

        // Témoin positif : sans lui, les assertions ci-dessous pourraient mesurer un journal vide.
        $reponse->assertOk()->assertJsonCount(2, 'sectors');

        $requeteSecteurs = collect($requetes)->first(fn (array $q) => str_starts_with($q['query'], 'select * from "sectors"'));
        // Attention : la requête des secteurs contient elle-même `from "trades"` dans son
        // `exists (...)` — on distingue donc les deux requêtes par le DÉBUT de leur SQL.
        $requeteMetiers = collect($requetes)->first(fn (array $q) => str_starts_with($q['query'], 'select * from "trades"'));

        $this->assertNotNull($requeteSecteurs, 'La requête des secteurs est introuvable dans le journal.');
        $this->assertNotNull($requeteMetiers, 'La requête des métiers (chargement anticipé) est introuvable dans le journal.');

        $this->assertStringContainsString('order by "sort_order" asc, "name" asc', $requeteSecteurs['query']);
        $this->assertStringContainsString('order by "sort_order" asc', $requeteMetiers['query']);
    }
}
