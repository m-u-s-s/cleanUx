<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\OrderEngine\OrderConfirmation;
use App\Livewire\OrderEngine\OrderJourney;
use App\Models\Booking;
use App\Models\OrderDraft;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\GeolocationV2\GeocodingResult;
use App\Services\GeolocationV2\GeocodingService;
use App\Services\OrderEngine\OrderDraftManager;
use App\Support\Domain\OrderDraftStatus;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** L'écran de confirmation : le dernier, et le seul qui demande une identité. */
class OrderConfirmationScreenTest extends TestCase
{
    use RefreshDatabase;

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

    /** LA loi du parcours : le prix avant l'identité. */
    public function test_a_visitor_without_an_account_sees_the_full_price(): void
    {
        $this->prepareBasket();

        Livewire::test(OrderConfirmation::class)
            ->assertSee('Total estimé')
            ->assertSee('Créer un compte')
            // Et le détail par métier, pas seulement un total opaque.
            ->assertSee($this->peinture()->name);
    }

    /** La route reste publique : l'identité est demandée DANS l'écran, pas devant. */
    public function test_the_confirmation_page_is_reachable_without_logging_in(): void
    {
        $this->get(route('order.confirmation'))->assertOk();
    }

    /** `/commander/{sector?}` ne doit pas avaler « recapitulatif ». */
    public function test_the_confirmation_route_is_not_swallowed_by_the_journey_route(): void
    {
        $this->assertSame(
            OrderConfirmation::class,
            app('router')->getRoutes()->match(
                Request::create(route('order.confirmation'))
            )->getActionName(),
        );
    }

    /** Un panier vide n'est pas un cul-de-sac : il propose de repartir. */
    public function test_an_empty_basket_offers_a_way_out(): void
    {
        Livewire::test(OrderConfirmation::class)
            ->assertSee('Composer une commande')
            ->assertDontSee('Confirmer la commande');
    }

    /** Ce qui bloque est ÉCRIT, pas seulement grisé. */
    public function test_what_is_missing_is_spelled_out(): void
    {
        $client = User::factory()->client()->create();
        $manager = app(OrderDraftManager::class);
        $draft = $manager->resumeOrCreate('jeton', $client);
        $manager->itemFor($draft, $this->peinture());

        Livewire::actingAs($client)
            ->test(OrderConfirmation::class)
            ->assertSee('adresse de l’intervention');
    }

    public function test_confirming_creates_the_booking_and_shows_the_reference(): void
    {
        $client = $this->prepareBasket();

        Livewire::actingAs($client)
            ->test(OrderConfirmation::class)
            ->call('confirm')
            ->assertSee('Commande confirmée');

        $this->assertSame(1, Booking::count());
        $this->assertSame(OrderDraftStatus::CONVERTED, OrderDraft::firstOrFail()->status);
    }

    /** Un double clic sur « Confirmer » ne produit pas deux réservations. */
    public function test_clicking_confirm_twice_creates_a_single_booking(): void
    {
        $client = $this->prepareBasket();

        Livewire::actingAs($client)
            ->test(OrderConfirmation::class)
            ->call('confirm')
            ->call('confirm');

        $this->assertSame(1, Booking::count());
    }

    /** Une commande confirmée n'est pas lisible par quelqu'un qui devine sa référence. */
    public function test_another_client_cannot_open_a_confirmed_order_by_its_reference(): void
    {
        $client = $this->prepareBasket();

        Livewire::actingAs($client)
            ->test(OrderConfirmation::class)
            ->call('confirm');

        $reference = OrderDraft::firstOrFail()->reference;

        Livewire::actingAs(User::factory()->client()->create())
            ->test(OrderConfirmation::class, ['confirmedReference' => $reference])
            ->assertDontSee('Rue de la Loi')
            ->assertDontSee('Commande confirmée');
    }

    /** Sans prestataire assigné, le paiement attend — et l'écran le DIT. */
    public function test_the_screen_explains_that_payment_waits_for_a_provider(): void
    {
        $client = $this->prepareBasket();

        Livewire::actingAs($client)
            ->test(OrderConfirmation::class)
            ->call('confirm')
            ->assertSee('dès qu’un professionnel')
            ->assertDontSee('Autoriser le paiement');
    }

    /** Le professionnel choisi par le client suit jusqu'à la réservation. */
    public function test_a_chosen_provider_reaches_the_booking(): void
    {
        $client = $this->prepareBasket();
        $provider = User::factory()->create(['role' => 'employe']);

        $draft = OrderDraft::firstOrFail();
        $draft->items()->first()->update(['provider_id' => $provider->id]);

        Livewire::actingAs($client)
            ->test(OrderConfirmation::class)
            ->call('confirm');

        $this->assertSame($provider->id, Booking::firstOrFail()->employe_id);
    }

    /** Le choix d'un professionnel est ÉCRIT, pas seulement gardé à l'écran. */
    public function test_choosing_a_provider_survives_a_reload(): void
    {
        $client = User::factory()->client()->create();
        $trade = $this->peinture();

        $shortlistable = $this->providerAt($trade, 50.8470, 4.3530);
        $stranger = User::factory()->create(['role' => User::ROLE_PROVIDER]);

        $this->mock(GeocodingService::class, function ($mock) {
            $mock->shouldReceive('geocode')
                ->andReturn(new GeocodingResult(50.8467, 4.3525, 'Bruxelles'));
        });

        $component = Livewire::actingAs($client)
            ->test(OrderJourney::class)
            ->call('selectTrade', $trade->id)
            ->set('address', 'Rue de la Loi 1, 1000 Bruxelles');

        // La liste proposée doit vraiment contenir quelqu'un, sinon le test ne prouve rien.
        $this->assertTrue($component->get('providerOptions')->contains('id', $shortlistable->id));

        // Un prestataire ABSENT de la liste n'est pas retenu : la valeur vient du navigateur.
        $component->call('selectProvider', $stranger->id);
        $this->assertNull(
            OrderDraft::firstOrFail()->items()->where('trade_id', $trade->id)->value('provider_id'),
        );

        // Celui de la liste, lui, est écrit sur la ligne.
        $component->call('selectProvider', $shortlistable->id);

        $this->assertSame(
            $shortlistable->id,
            OrderDraft::firstOrFail()->items()->where('trade_id', $trade->id)->value('provider_id'),
        );

        Livewire::actingAs($client)
            ->test(OrderJourney::class)
            ->call('selectTrade', $trade->id)
            ->assertSet('selectedProviderId', $shortlistable->id);
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function peinture(): Trade
    {
        return Trade::where('slug', 'peinture')->firstOrFail();
    }

    /** Un prestataire réellement proposable : rattaché au métier, situé, et actif. */
    private function providerAt(Trade $trade, float $lat, float $lng): User
    {
        $provider = User::factory()->create(['role' => User::ROLE_PROVIDER]);

        ProviderProfile::create([
            'user_id' => $provider->id,
            'status' => 'active',
            'current_lat' => $lat,
            'current_lng' => $lng,
        ]);

        DB::table('trade_user')->insert([
            'user_id' => $provider->id,
            'trade_id' => $trade->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $provider->fresh();
    }

    /** Un panier prêt à confirmer, composé par les chemins de production. */
    private function prepareBasket(): User
    {
        $client = User::factory()->client()->create();
        $manager = app(OrderDraftManager::class);

        $draft = $manager->resumeOrCreate('jeton');
        $draft->update([
            'address' => 'Rue de la Loi 1, 1000 Bruxelles',
            'lat' => 50.8467,
            'lng' => 4.3525,
            'scheduled_at' => Carbon::parse('2026-09-02 09:00:00'),
        ]);

        $trade = $this->peinture();
        $item = $manager->itemFor($draft, $trade);
        $manager->saveAnswers(
            $item,
            $trade->questions()->with(['options', 'conditions'])->get(),
            ['surface_m2' => 40, 'etendue' => 'murs_plafonds'],
        );

        session()->put('order_draft_token', 'jeton');

        return $client;
    }
}
