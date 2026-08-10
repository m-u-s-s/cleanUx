<?php

namespace Tests\Feature\OrderEngine;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Livewire\ClientCompany\BookingHub;
use App\Livewire\OrderEngine\OrderJourney;
use App\Models\OrganizationAccount;
use App\Models\OrganizationMember;
use App\Models\OrganizationSite;
use App\Models\Sector;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\TradeZonePricing;
use App\Models\User;
use App\Services\OrderEngine\OrderConfirmationService;
use App\Services\OrderEngine\OrderDraftManager;
use App\Support\Domain\OrderMode;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LES TROIS FAÇONS DE COMMANDER, SUR LE WEB, POUR LES DEUX SORTES DE CLIENTS.
 *
 * L'application mobile posait la question en premier — immédiat, rendez-vous, multi-services. Le web
 * arrivait directement sur le catalogue COMPLET : l'intervention immédiate ne se découvrait qu'après
 * avoir choisi un métier, parfois pour apprendre que ce métier ne la permet pas. Et une entreprise
 * cliente n'y avait aucun accès : son formulaire maison ne servait que le rendez-vous, si bien qu'une
 * société avec une fuite dans ses bureaux ne pouvait appeler personne tout de suite, là où un
 * particulier le pouvait — la même plateforme, deux promesses selon le type de compte.
 *
 * CE QUE CE FICHIER PROTÈGE SURTOUT : « intervention immédiate » ne doit contenir QUE ce qui se fait
 * dans l'heure. Un catalogue qui propose un ravalement de façade en immédiat fait cliquer dans le
 * vide, puis reculer — et la deuxième chose qu'un client apprend de la plateforme est qu'elle
 * propose ce qu'elle ne sait pas faire.
 */
class TroisModesWebTest extends TestCase
{
    use RefreshDatabase;

    private ServiceZone $zone;

    private Sector $secteurUrgent;

    private Sector $secteurLent;

    private Trade $plomberie;   // immédiat possible

    private Trade $ravalement;  // jamais en immédiat

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = ServiceZone::create([
            'name' => 'Zone modes', 'slug' => 'zone-modes', 'code' => 'ZMD',
            'status' => 'active', 'is_bookable' => true, 'is_visible' => true,
            'priority' => 10, 'coverage_type' => 'city_cluster',
        ]);

        $this->secteurUrgent = Sector::create([
            'name' => 'Dépannage', 'slug' => 'depannage-modes', 'is_active' => true, 'sort_order' => 1,
        ]);
        $this->secteurLent = Sector::create([
            'name' => 'Gros œuvre', 'slug' => 'gros-oeuvre-modes', 'is_active' => true, 'sort_order' => 2,
        ]);

        $this->plomberie = Trade::create([
            'sector_id' => $this->secteurUrgent->id,
            'slug' => 'plomberie-modes', 'code' => 'PLB-MD', 'name' => 'Plomberie',
            'is_active' => true, 'sort_order' => 1,
            'allows_scheduled' => true, 'allows_asap' => true, 'allows_bundle' => true,
        ]);

        $this->ravalement = Trade::create([
            'sector_id' => $this->secteurLent->id,
            'slug' => 'ravalement-modes', 'code' => 'RAV-MD', 'name' => 'Ravalement de façade',
            'is_active' => true, 'sort_order' => 1,
            'allows_scheduled' => true, 'allows_asap' => false, 'allows_bundle' => true,
        ]);
    }

    private function client(): User
    {
        return User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
    }

    // ─── Le catalogue suit l'intention ───────────────────────────────────────────────────────

    #[Test]
    public function sans_intention_le_catalogue_est_complet(): void
    {
        $composant = Livewire::test(OrderJourney::class);

        // Le parcours historique : on voit tout, et le mode se choisit métier par métier.
        $this->assertEqualsCanonicalizing(
            [$this->secteurUrgent->id, $this->secteurLent->id],
            $composant->instance()->sectors->pluck('id')->all(),
        );
    }

    #[Test]
    public function l_intervention_immediate_ne_montre_que_les_metiers_qui_la_permettent(): void
    {
        $composant = Livewire::test(OrderJourney::class)->call('chooseIntent', OrderMode::ASAP);

        $secteurs = $composant->instance()->sectors->pluck('id')->all();

        // Le gros œuvre n'a rien à offrir dans l'heure : il disparaît entièrement, plutôt que de
        // s'ouvrir sur une liste vide.
        $this->assertSame([$this->secteurUrgent->id], $secteurs);

        $composant->set('sectorId', $this->secteurUrgent->id);
        $this->assertSame([$this->plomberie->id], $composant->instance()->trades->pluck('id')->all());
    }

    #[Test]
    public function un_metier_ferme_a_l_immediat_dans_l_a_zone_disparait_aussi(): void
    {
        // Le métier PEUT se faire dans l'heure — mais l'exploitation ne l'a pas ouvert ici. Les deux
        // verrous ne disent pas la même chose, et c'est le second qui décide sur place.
        TradeZonePricing::create([
            'trade_id' => $this->plomberie->id,
            'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 5000, 'surge_multiplier' => '1.00',
            'is_active' => true, 'asap_enabled' => false,
        ]);

        $composant = Livewire::test(OrderJourney::class)
            ->set('serviceZoneId', $this->zone->id)
            ->call('chooseIntent', OrderMode::ASAP);

        $this->assertSame([], $composant->instance()->sectors->pluck('id')->all());
    }

    #[Test]
    public function le_meme_metier_reste_visible_quand_la_zone_l_ouvre(): void
    {
        TradeZonePricing::create([
            'trade_id' => $this->plomberie->id,
            'service_zone_id' => $this->zone->id,
            'base_rate_cents' => 5000, 'surge_multiplier' => '1.00',
            'is_active' => true, 'asap_enabled' => true,
        ]);

        $composant = Livewire::test(OrderJourney::class)
            ->set('serviceZoneId', $this->zone->id)
            ->call('chooseIntent', OrderMode::ASAP);

        $this->assertSame([$this->secteurUrgent->id], $composant->instance()->sectors->pluck('id')->all());
    }

    #[Test]
    public function tant_qu_aucune_adresse_n_est_saisie_la_zone_ne_filtre_pas(): void
    {
        // Aucune ligne de tarif n'existe : si la zone filtrait sans adresse, l'écran serait vide
        // pour TOUT LE MONDE, y compris là où l'immédiat est ouvert.
        $composant = Livewire::test(OrderJourney::class)->call('chooseIntent', OrderMode::ASAP);

        $this->assertSame([$this->secteurUrgent->id], $composant->instance()->sectors->pluck('id')->all());
    }

    #[Test]
    public function changer_d_intention_repart_du_catalogue(): void
    {
        $composant = Livewire::test(OrderJourney::class)
            ->set('sectorId', $this->secteurLent->id)
            ->call('selectTrade', $this->ravalement->id);

        $this->assertSame($this->ravalement->id, $composant->get('tradeId'));

        $composant->call('chooseIntent', OrderMode::ASAP);

        // Garder le métier donnerait un écran qui contredit le choix qu'on vient de faire :
        // « intervention immédiate » affichant un ravalement de façade.
        $this->assertNull($composant->get('tradeId'));
        $this->assertNull($composant->get('sectorId'));
    }

    #[Test]
    public function une_intention_inventee_dans_l_url_est_ignoree(): void
    {
        $composant = Livewire::test(OrderJourney::class)->call('chooseIntent', 'n-importe-quoi');

        $this->assertNull($composant->get('intendedMode'));
        $this->assertCount(2, $composant->instance()->sectors);
    }

    #[Test]
    public function les_trois_cartes_sont_rendues_sur_le_web(): void
    {
        $this->get(route('order.journey'))
            ->assertOk()
            ->assertSee('mode-card-asap', false)
            ->assertSee('mode-card-scheduled', false)
            ->assertSee('mode-card-bundle', false);
    }

    // ─── L'entreprise cliente ────────────────────────────────────────────────────────────────

    /** @return array{0: User, 1: OrganizationSite} */
    private function societeCliente(): array
    {
        $org = OrganizationAccount::factory()->create([
            'type' => OrganizationType::CLIENT_COMPANY->value, 'status' => 'active',
        ]);

        $user = User::factory()->create([
            'role' => User::ROLE_ENTREPRISE,
            'is_active' => true,
            'current_organization_id' => $org->id,
            'organization_account_id' => $org->id,
        ]);

        OrganizationMember::create([
            'organization_account_id' => $org->id,
            'user_id' => $user->id,
            'role' => OrganizationRole::OWNER->value,
            'status' => 'active',
            'invited_at' => now(),
            'joined_at' => now(),
        ]);

        $site = OrganizationSite::factory()->create([
            'organization_account_id' => $org->id,
            'address' => 'Rue de la Loi 12',
            'postal_code' => '1000',
            'service_zone_id' => $this->zone->id,
            'is_primary' => true,
        ]);

        return [$user->fresh(), $site];
    }

    #[Test]
    public function l_entreprise_cliente_voit_les_trois_modes(): void
    {
        [$user] = $this->societeCliente();

        Livewire::actingAs($user)
            ->test(BookingHub::class)
            ->assertSee('company-mode-asap', false)
            ->assertSee('company-mode-scheduled', false)
            ->assertSee('company-mode-bundle', false);
    }

    #[Test]
    public function commander_pour_un_local_situe_le_parcours_d_avance(): void
    {
        [$user, $site] = $this->societeCliente();

        $composant = Livewire::actingAs($user)->withQueryParams(['site' => $site->id])
            ->test(OrderJourney::class);

        // Sans cela, le client d'une société retaperait l'adresse de son propre bureau — celle-là
        // même que la plateforme connaît, avec sa zone.
        $this->assertSame('Rue de la Loi 12', $composant->get('address'));
        $this->assertSame($this->zone->id, $composant->get('serviceZoneId'));
    }

    /**
     * LA COMMANDE APPARTIENT À LA SOCIÉTÉ, pas seulement à la personne qui l'a passée.
     *
     * Sans ce rattachement, la facture part au nom du collaborateur, la commande n'apparaît pas dans
     * les réservations de l'entreprise, et le local desservi n'est nulle part — trois conséquences
     * d'une même colonne oubliée.
     */
    #[Test]
    public function la_commande_d_une_societe_porte_sa_societe_et_son_local(): void
    {
        $this->seed(OrderEngineCatalogSeeder::class);

        [$user, $site] = $this->societeCliente();
        $manager = app(OrderDraftManager::class);

        $draft = $manager->resumeOrCreate('jeton-societe-'.uniqid());
        $draft->update([
            'address' => 'Rue de la Loi 12',
            'lat' => 50.8467,
            'lng' => 4.3525,
            // La zone vient du LOCAL : c'est tout l'intérêt de commander pour une adresse que la
            // plateforme connaît déjà. Sans elle, la confirmation refuse — « nous n'intervenons pas
            // encore à cette adresse ».
            'service_zone_id' => $site->service_zone_id,
            'scheduled_at' => now()->addWeek()->setTime(9, 0),
            'metadata' => [
                'organization_account_id' => $user->current_organization_id,
                'organization_site_id' => $site->id,
            ],
        ]);

        $trade = Trade::where('slug', 'peinture')->firstOrFail();

        TradeZonePricing::updateOrCreate(
            ['trade_id' => $trade->id, 'service_zone_id' => $site->service_zone_id],
            ['base_rate_cents' => 5000, 'surge_multiplier' => '1.00', 'is_active' => true, 'asap_enabled' => false],
        );

        $item = $manager->itemFor($draft, $trade);
        $manager->saveAnswers(
            $item,
            $trade->questions()->with(['options', 'conditions'])->get(),
            ['surface_m2' => 40, 'etendue' => 'murs_plafonds'],
        );

        app(OrderConfirmationService::class)->confirm($draft->fresh(), $user);

        $this->assertDatabaseHas('bookings', [
            'client_id' => $user->id,
            'customer_organization_id' => $user->current_organization_id,
            'organization_site_id' => $site->id,
        ]);
    }

    #[Test]
    public function un_panier_dont_l_auteur_a_quitte_la_societe_ne_la_rattache_plus(): void
    {
        $this->seed(OrderEngineCatalogSeeder::class);

        [$user, $site] = $this->societeCliente();
        $manager = app(OrderDraftManager::class);

        $draft = $manager->resumeOrCreate('jeton-parti-'.uniqid());
        $draft->update([
            'address' => 'Rue de la Loi 12',
            'lat' => 50.8467, 'lng' => 4.3525,
            'service_zone_id' => $site->service_zone_id,
            'scheduled_at' => now()->addWeek()->setTime(9, 0),
            'metadata' => [
                'organization_account_id' => $user->current_organization_id,
                'organization_site_id' => $site->id,
            ],
        ]);

        $trade = Trade::where('slug', 'peinture')->firstOrFail();

        TradeZonePricing::updateOrCreate(
            ['trade_id' => $trade->id, 'service_zone_id' => $site->service_zone_id],
            ['base_rate_cents' => 5000, 'surge_multiplier' => '1.00', 'is_active' => true, 'asap_enabled' => false],
        );

        $item = $manager->itemFor($draft, $trade);
        $manager->saveAnswers(
            $item,
            $trade->questions()->with(['options', 'conditions'])->get(),
            ['surface_m2' => 40, 'etendue' => 'murs_plafonds'],
        );

        /*
         * Le panier a pu être ouvert hier. Faire confiance à ce qu'il porte rattacherait la
         * commande — et sa facture — à une société dont l'auteur n'est plus membre.
         */
        OrganizationMember::query()
            ->where('user_id', $user->id)
            ->update(['status' => 'removed']);

        app(OrderConfirmationService::class)->confirm($draft->fresh(), $user);

        $this->assertDatabaseMissing('bookings', [
            'client_id' => $user->id,
            'customer_organization_id' => $user->current_organization_id,
        ]);
    }

    #[Test]
    public function le_local_d_une_autre_societe_est_ignore(): void
    {
        [$user] = $this->societeCliente();

        $concurrent = OrganizationAccount::factory()->create([
            'type' => OrganizationType::CLIENT_COMPANY->value, 'status' => 'active',
        ]);
        $adverse = OrganizationSite::factory()->create([
            'organization_account_id' => $concurrent->id,
            'address' => 'Siège du concurrent',
        ]);

        $composant = Livewire::actingAs($user)->withQueryParams(['site' => $adverse->id])
            ->test(OrderJourney::class);

        // L'identifiant vient de la barre d'adresse : le local d'une autre société révélerait son
        // adresse à qui devine un numéro.
        $this->assertNotSame('Siège du concurrent', $composant->get('address'));
    }
}
