<?php

namespace Tests\Feature\OrderEngine;

use App\Enums\ProviderType;
use App\Livewire\OrderEngine\AsapSearch;
use App\Livewire\OrderEngine\OrderConfirmation;
use App\Models\AsapDispatchRequest;
use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\OrderDraft;
use App\Models\ProviderPresence;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\Dispatch\MissionDispatchService;
use App\Services\OrderEngine\OrderConfirmationService;
use App\Services\OrderEngine\OrderDraftManager;
use App\Support\Domain\AsapStatus;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\OrderMode;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** L'écran d'attente d'une course immédiate. Le plus anxiogène du parcours. */
class AsapSearchScreenTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 50.8467;

    private const LNG = 4.3525;

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

    /** Confirmer en mode immédiat OUVRE la recherche. */
    public function test_confirming_an_immediate_order_opens_the_search(): void
    {
        // Un candidat, sinon la recherche s'épuise dans la seconde : le moteur ne laisse pas une
        // recherche « en cours » sans personne à qui l'offrir.
        $this->providerAt($this->peinture());

        [$draft, $client] = $this->asapDraft();

        app(OrderConfirmationService::class)->confirm($draft, $client);

        $request = AsapDispatchRequest::firstOrFail();
        $this->assertSame(AsapStatus::SEARCHING, $request->status);
        // La recherche appartient à la RÉSERVATION ; la ligne de panier reste rattachée pour que
        // le devis soit explicable six mois plus tard.
        $this->assertSame($draft->id, $request->order_draft_id);
        $this->assertNotNull($request->booking_id);
        $this->assertNotNull($request->mission_id);
    }

    /** Un mode planifié n'ouvre aucune recherche : ce serait prévenir des prestataires pour rien. */
    public function test_a_scheduled_order_opens_no_search(): void
    {
        [$draft, $client] = $this->asapDraft();
        $draft->update(['mode' => OrderMode::SCHEDULED]);

        app(OrderConfirmationService::class)->confirm($draft->fresh(), $client);

        $this->assertSame(0, AsapDispatchRequest::count());
    }

    /** Le client est emmené sur l'écran d'attente : c'est là que se joue la suite. */
    public function test_confirming_lands_the_client_on_the_waiting_screen(): void
    {
        [$draft, $client] = $this->asapDraft();

        Livewire::actingAs($client)
            ->test(OrderConfirmation::class)
            ->call('confirm')
            ->assertRedirect(route('order.asap.search', AsapDispatchRequest::firstOrFail()->id));
    }

    // ─── Ce que l'écran montre ───────────────────────────────────────────────────────────────

    /** Le rayon, le nombre de prestataires prévenus et le temps écoulé — des chiffres vrais. */
    public function test_the_wait_is_inhabited_by_real_numbers(): void
    {
        [$request, $client] = $this->searching();

        Livewire::actingAs($client)
            ->test(AsapSearch::class, ['request' => $request->id])
            ->assertSee('Rayon')
            ->assertSee('Prévenus')
            ->assertSee('5 km');
    }

    /** L'annulation est TOUJOURS atteignable, et son coût est ANNONCÉ avant le clic. */
    public function test_cancelling_is_always_reachable_and_its_cost_is_announced(): void
    {
        [$request, $client] = $this->searching();

        Livewire::actingAs($client)
            ->test(AsapSearch::class, ['request' => $request->id])
            ->assertSee('Annuler la demande')
            ->assertSee('l’annulation est gratuite');
    }

    /** Une fois le professionnel en route, le montant est annoncé AVANT, pas découvert après. */
    public function test_the_fee_is_shown_before_the_click_not_after(): void
    {
        [$request, $client] = $this->searching();
        $accepted = $this->accepterLaCourse($request);

        // La fenêtre gratuite est passée : l'annulation coûte, et l'écran l'écrit.
        $accepted->update(['free_cancellation_until' => now()->subMinute()]);
        Config::set('order_engine.asap_cancellation_fee_cents', 500);

        Livewire::actingAs($client)
            ->test(AsapSearch::class, ['request' => $accepted->id])
            ->assertSee('l’annulation coûte 5,00 €')
            ->assertSee('Annuler la demande');
    }

    /** Le montant appliqué est celui qui a été affiché : l'écran et la facture ne divergent pas. */
    public function test_the_amount_charged_is_the_one_displayed(): void
    {
        [$request, $client] = $this->searching();
        $accepted = $this->accepterLaCourse($request);
        $accepted->update(['free_cancellation_until' => now()->subMinute()]);
        Config::set('order_engine.asap_cancellation_fee_cents', 700);

        Livewire::actingAs($client)
            ->test(AsapSearch::class, ['request' => $accepted->id])
            ->call('cancel');

        $this->assertSame(700, (int) $accepted->fresh()->cancellation_fee_cents);
        $this->assertSame(AsapStatus::CANCELLED, $accepted->fresh()->status);
    }

    /** Personne n'a répondu : jamais un simple constat d'échec. */
    public function test_nobody_answering_is_never_a_dead_end(): void
    {
        [$request, $client] = $this->searching();

        // Le délai est dépassé : le battement de l'écran le constate.
        Carbon::setTestNow(now()->addSeconds((int) Config::get('dispatch.search_deadline_seconds', 300) + 5));

        $component = Livewire::actingAs($client)
            ->test(AsapSearch::class, ['request' => $request->id])
            ->call('tick');

        $this->assertSame(AsapStatus::EXPIRED, $request->fresh()->status);

        // LES TROIS SORTIES, ET AUCUNE N'EST UN CONSTAT. Chacune est un GESTE : la recherche
        // repart, la demande devient un rendez-vous sans repayer, ou elle s'annule sans frais.
        $component
            ->assertSee('Chercher encore')
            ->assertSee('Prendre rendez-vous')
            ->assertSee('Annuler sans frais');
    }

    /** Élargir se voit : le rayon monte, et le client le lit. */
    public function test_expanding_widens_the_visible_radius(): void
    {
        [$request, $client] = $this->searching();

        Livewire::actingAs($client)
            ->test(AsapSearch::class, ['request' => $request->id])
            ->call('expand')
            ->assertSee('10 km');
    }

    /** Une demande ne se lit pas parce qu'on connaît son numéro : elle porte une adresse. */
    public function test_another_client_cannot_watch_someone_elses_search(): void
    {
        [$request, $owner] = $this->searching();

        $this->actingAs(User::factory()->client()->create())
            ->get(route('order.asap.search', $request->id))
            ->assertNotFound();

        // Et son propriétaire, lui, la voit bien : le test ne passerait pas par simple 404 partout.
        $this->actingAs($owner)
            ->get(route('order.asap.search', $request->id))
            ->assertOk()
            ->assertSee('Annuler la demande');
    }

    /** Non connecté, on ne regarde pas une recherche : elle n'existe qu'après le compte. */
    public function test_a_visitor_is_not_let_into_the_waiting_screen(): void
    {
        [$request] = $this->searching();

        $this->get(route('order.asap.search', $request->id))->assertRedirect(route('login'));
    }

    // ─── La reprise en intervention réelle ───────────────────────────────────────────────────

    /** Accepter une course la transforme en intervention RÉELLE. */
    public function test_accepting_hands_the_ride_over_to_a_real_mission(): void
    {
        $provider = $this->providerAt($this->peinture());
        [$request, , $booking] = $this->confirmedSearch();

        $this->accepterParLOffre($request, $provider);

        $booking->refresh();
        $this->assertSame($provider->id, $booking->employe_id);
        $this->assertSame(BookingStatus::CONFIRME, $booking->status);

        // Et la mission existe : c'est elle que l'application prestataire montre.
        $this->assertTrue(Mission::query()->where('booking_id', $booking->id)
            ->orWhere('rendez_vous_id', $booking->id)->exists());
    }

    /** UN SEUL CANAL D'ASSIGNATION. */
    public function test_a_booking_already_assigned_is_not_stolen(): void
    {
        $provider = $this->providerAt($this->peinture());
        [$request, , $booking] = $this->confirmedSearch();

        // La mission est déjà partie : l'offre n'est plus acceptable, et le refus est EXPLICITE.
        $this->accepterParLOffre($request, $provider);

        $offre = MissionAssignment::query()
            ->where('mission_id', $request->mission_id)
            ->where('user_id', $provider->id)
            ->firstOrFail();

        $this->expectException(\DomainException::class);
        app(MissionDispatchService::class)->accept($offre->fresh());
    }

    /** Le second à cliquer est refusé proprement, pas silencieusement écrasé. */
    public function test_the_second_provider_to_accept_is_refused(): void
    {
        // Deux prestataires très loin : la recherche atteint son plafond et diffuse aux deux.
        $premier = $this->providerAt($this->peinture(), 51.0000, 4.3525);
        $second = $this->providerAt($this->peinture(), 51.0010, 4.3530);

        [$request] = $this->confirmedSearch();

        $this->accepterParLOffre($request, $premier);

        $offreSeconde = MissionAssignment::query()
            ->where('mission_id', $request->mission_id)
            ->where('user_id', $second->id)
            ->firstOrFail();

        $this->expectException(\DomainException::class);
        app(MissionDispatchService::class)->accept($offreSeconde->fresh());
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function peinture(): Trade
    {
        return Trade::where('slug', 'peinture')->firstOrFail();
    }

    /** Un candidat REEL au sens du moteur. */
    private function providerAt(Trade $trade, float $lat = self::LAT, float $lng = self::LNG): User
    {
        $provider = User::factory()->create([
            'role' => User::ROLE_EMPLOYE,
            'is_active' => true,
        ]);

        ProviderProfile::create([
            'user_id' => $provider->id,
            'provider_type' => ProviderType::INDEPENDENT->value,
            'status' => 'active',
            'verification_status' => 'verified',
            'current_lat' => $lat,
            'current_lng' => $lng,
        ]);

        ProviderPresence::create([
            'provider_user_id' => $provider->id,
            'status' => 'online',
            'current_lat' => $lat,
            'current_lng' => $lng,
            'heartbeat_at' => now(),
        ]);

        DB::table('trade_user')->insert([
            'user_id' => $provider->id,
            'trade_id' => $trade->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $provider->fresh();
    }

    /** L'acceptation par le chemin de PRODUCTION : l'offre nominative, pas la recherche. */
    private function accepterParLOffre(AsapDispatchRequest $request, User $provider): void
    {
        $offre = MissionAssignment::query()
            ->where('mission_id', $request->mission_id)
            ->where('user_id', $provider->id)
            ->where('assignment_status', 'assigned')
            ->firstOrFail();

        app(MissionDispatchService::class)->accept($offre);
    }

    /** @return array{0: OrderDraft, 1: User} */
    private function asapDraft(): array
    {
        $client = User::factory()->client()->create();
        $manager = app(OrderDraftManager::class);

        $draft = $manager->resumeOrCreate('jeton', null, OrderMode::ASAP);
        $draft->update([
            'mode' => OrderMode::ASAP,
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'lat' => self::LAT,
            'lng' => self::LNG,
        ]);

        $trade = $this->peinture();
        $item = $manager->itemFor($draft->fresh(), $trade);
        $manager->saveAnswers(
            $item,
            $trade->questions()->with(['options', 'conditions'])->get(),
            ['surface_m2' => 40, 'etendue' => 'murs_plafonds'],
        );

        session()->put('order_draft_token', 'jeton');

        return [$draft->fresh(), $client];
    }

    /**
     * Une recherche ouverte par le chemin de production — confirmation comprise.
     *
     * @return array{0: AsapDispatchRequest, 1: User, 2: Booking}
     */
    private function confirmedSearch(): array
    {
        [$draft, $client] = $this->asapDraft();
        app(OrderConfirmationService::class)->confirm($draft, $client);

        return [AsapDispatchRequest::firstOrFail(), $client, Booking::firstOrFail()];
    }

    /** Accepte la course par l'offre nominative en cours, et rend la recherche à jour. */
    private function accepterLaCourse(AsapDispatchRequest $request): AsapDispatchRequest
    {
        $offre = MissionAssignment::query()
            ->where('mission_id', $request->mission_id)
            ->where('assignment_status', 'assigned')
            ->firstOrFail();

        app(MissionDispatchService::class)->accept($offre);

        return $request->fresh();
    }

    /** @return array{0: AsapDispatchRequest, 1: User} */
    private function searching(): array
    {
        // Un prestataire joignable, sinon la recherche s'épuise à l'ouverture et l'écran dit autre
        // chose. Il est créé AVANT la confirmation : c'est elle qui ouvre la recherche.
        $this->providerAt($this->peinture());

        [$request, $client] = $this->confirmedSearch();

        return [$request, $client];
    }
}
