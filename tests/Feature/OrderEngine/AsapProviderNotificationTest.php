<?php

namespace Tests\Feature\OrderEngine;

use App\Models\AsapDispatchNotification;
use App\Models\AsapDispatchRequest;
use App\Models\Booking;
use App\Models\DeviceToken;
use App\Models\OrderDraft;
use App\Models\ProviderProfile;
use App\Models\PushNotification;
use App\Models\Trade;
use App\Models\User;
use App\Services\OrderEngine\AsapDispatchService;
use App\Services\OrderEngine\AsapProviderNotifier;
use App\Services\OrderEngine\OrderConfirmationService;
use App\Services\OrderEngine\OrderDraftManager;
use App\Services\Push\PushService;
use App\Support\Domain\AsapStatus;
use App\Support\Domain\BookingStatus;
use App\Support\Domain\OrderMode;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Prévenir les prestataires, pour de vrai.
 *
 * Le compteur de l'écran client disait combien de personnes étaient joignables ; personne n'était
 * prévenu. Une recherche qui n'atteint personne n'aboutit jamais.
 *
 * Deux garanties tiennent tout le reste. Chacun n'est prévenu QU'UNE FOIS par recherche — élargir
 * trois fois enverrait sinon quatre alertes au plus proche, et c'est ainsi qu'on se fait couper
 * les notifications. Et un envoi qui échoue n'arrête pas la recherche : un jeton mort chez l'un ne
 * doit pas priver les onze autres de la course.
 */
class AsapProviderNotificationTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 50.8467;

    private const LNG = 4.3525;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);

        // Un rayon initial modeste : c'est ce qui rend l'élargissement observable.
        Config::set('order_engine.asap_initial_radius_m', 3000);
        Config::set('order_engine.asap_radius_step_m', 12000);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Ouvrir une recherche PRÉVIENT les prestataires du rayon — pas seulement les compte. */
    public function test_opening_a_search_actually_notifies_the_providers(): void
    {
        $near = $this->providerWithPhone(50.8470, 4.3530);

        [$request] = $this->searchWithProviders();

        $this->assertTrue(
            AsapDispatchNotification::where('asap_dispatch_request_id', $request->id)
                ->where('user_id', $near->id)->exists(),
            'Le prestataire du rayon aurait dû recevoir la course.',
        );

        // Et la notification est réellement partie sur son téléphone.
        $this->assertTrue(
            PushNotification::query()->where('user_id', $near->id)->exists(),
            'Aucune notification poussée n’a été produite.',
        );
    }

    /** Un prestataire hors rayon n'est pas dérangé : on ne réveille pas Anvers pour Bruxelles. */
    public function test_a_provider_outside_the_radius_is_left_alone(): void
    {
        $this->providerWithPhone(50.8470, 4.3530);
        $far = $this->providerWithPhone(51.2100, 4.4200);   // Anvers, ~40 km

        [$request] = $this->searchWithProviders();

        $this->assertFalse(
            AsapDispatchNotification::where('asap_dispatch_request_id', $request->id)
                ->where('user_id', $far->id)->exists(),
        );
    }

    /**
     * LA garantie centrale : personne n'est prévenu deux fois.
     *
     * Élargir trois fois enverrait sinon quatre alertes au prestataire le plus proche. Un
     * prestataire qui reçoit quatre fois la même course coupe les notifications, et une fois
     * coupées elles ne reviennent pas.
     */
    public function test_expanding_notifies_only_the_new_providers(): void
    {
        $near = $this->providerWithPhone(50.8470, 4.3530);
        $this->providerWithPhone(50.9500, 4.4500);   // hors du rayon initial

        [$request] = $this->searchWithProviders();
        $this->assertSame(1, $request->notifications()->count());

        app(AsapDispatchService::class)->expand($request->fresh());

        // Le lointain est entré dans le rayon ; le proche n'a PAS été redérangé.
        $this->assertSame(2, $request->fresh()->notifications()->count());
        $this->assertSame(
            1,
            AsapDispatchNotification::where('asap_dispatch_request_id', $request->id)
                ->where('user_id', $near->id)->count(),
        );
    }

    /** Le compteur affiché au client est celui des prestataires RÉELLEMENT prévenus. */
    public function test_the_counter_reflects_who_was_actually_notified(): void
    {
        $this->providerWithPhone(50.8470, 4.3530);
        $this->providerWithPhone(50.8480, 4.3540);

        [$request] = $this->searchWithProviders();

        $this->assertSame(2, $request->fresh()->notified_count);
        $this->assertSame(
            $request->fresh()->notifications()->count(),
            $request->fresh()->notified_count,
        );
    }

    /**
     * Un envoi qui échoue n'arrête pas la recherche, et l'échec est ÉCRIT.
     *
     * Une recherche sans réponse alors que tous les envois ont échoué est un incident, pas un
     * manque de prestataires — encore faut-il pouvoir faire la différence après coup.
     */
    public function test_a_failing_send_neither_stops_the_search_nor_disappears(): void
    {
        $this->providerWithPhone(50.8470, 4.3530);
        $this->providerWithPhone(50.8480, 4.3540);

        $this->mock(PushService::class, function ($mock) {
            $mock->shouldReceive('dispatchToUser')->andThrow(new \RuntimeException('jeton mort'));
        });

        [$request] = $this->searchWithProviders();

        // Les deux ont bien été inscrits — la recherche ne s'est pas arrêtée au premier échec.
        $this->assertSame(2, $request->fresh()->notifications()->count());
        $this->assertSame(
            2,
            $request->notifications()->whereNotNull('delivery_error')->count(),
            'L’échec d’envoi aurait dû être conservé.',
        );
    }

    /**
     * Le module push entièrement indisponible ne fait pas tomber la confirmation du client — et le
     * compteur NE MENT PAS.
     *
     * C'est la distinction qui compte : le chiffre affiché est celui des prestataires réellement
     * prévenus, pas celui des joignables. Annoncer « 1 professionnel prévenu » alors que l'envoi
     * n'est jamais parti laisse le client attendre en confiance devant une recherche morte.
     */
    public function test_a_broken_push_module_does_not_break_the_order_nor_inflate_the_counter(): void
    {
        $this->providerWithPhone(50.8470, 4.3530);

        $this->mock(AsapProviderNotifier::class, function ($mock) {
            $mock->shouldReceive('notify')->andThrow(new \RuntimeException('module hors service'));
        });

        [$request] = $this->searchWithProviders();

        $this->assertSame(AsapStatus::SEARCHING, $request->fresh()->status);
        $this->assertSame(1, Booking::count());

        // Un prestataire était joignable, aucun n'a été prévenu : le compteur doit dire zéro.
        $this->assertSame(0, $request->fresh()->notified_count);
    }

    /**
     * Rejouer l'envoi ne fait pas vibrer deux fois le même téléphone.
     *
     * La garantie ne tient pas au filtre qui précède l'écriture — elle tient à l'index unique
     * (recherche, prestataire) en base. Le filtre évite un travail inutile ; l'index, lui, est ce
     * qui rend l'envoi idempotent même si deux appels se croisent.
     */
    public function test_replaying_the_send_does_not_buzz_the_same_phone_twice(): void
    {
        $provider = $this->providerWithPhone(50.8470, 4.3530);
        [$request] = $this->searchWithProviders();

        app(AsapProviderNotifier::class)->notify($request->fresh());
        app(AsapProviderNotifier::class)->notify($request->fresh());

        $this->assertSame(
            1,
            AsapDispatchNotification::where('asap_dispatch_request_id', $request->id)
                ->where('user_id', $provider->id)->count(),
        );
        $this->assertSame(1, PushNotification::where('user_id', $provider->id)->count());
    }

    /**
     * La base REFUSE un doublon — c'est là que vit la garantie.
     *
     * Le filtre applicatif masque le problème dans le chemin normal ; il ne protège rien si deux
     * envois se croisent. Ce test attaque l'index directement : le dégrader en index ordinaire le
     * fait tomber, alors qu'aucun test passant par le service ne s'en apercevrait.
     */
    public function test_the_database_itself_refuses_a_duplicate_offer(): void
    {
        $provider = $this->providerWithPhone(50.8470, 4.3530);
        [$request] = $this->searchWithProviders();

        $this->expectException(QueryException::class);

        AsapDispatchNotification::create([
            'asap_dispatch_request_id' => $request->id,
            'user_id' => $provider->id,
            'notified_at' => now(),
        ]);
    }

    // ─── Le côté prestataire ─────────────────────────────────────────────────────────────────

    /** Le prestataire voit ce qui lui est proposé, avec le montant que le client a accepté. */
    public function test_a_provider_sees_the_offers_made_to_them(): void
    {
        $provider = $this->providerWithPhone(50.8470, 4.3530);
        [$request] = $this->searchWithProviders();

        $response = $this->actingAs($provider, 'sanctum')->getJson('/api/provider/asap-offers');

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($request->id, $response->json('data.0.asap_dispatch_request_id'));
        $this->assertNotNull($response->json('data.0.estimate_min_cents'));
    }

    /** Il ne voit QUE les siennes : une course proposée à un autre ne le regarde pas. */
    public function test_a_provider_does_not_see_offers_made_to_someone_else(): void
    {
        $this->providerWithPhone(50.8470, 4.3530);
        $this->searchWithProviders();

        $outsider = $this->providerWithPhone(51.2100, 4.4200);

        $this->actingAs($outsider, 'sanctum')
            ->getJson('/api/provider/asap-offers')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /** Une course déjà prise disparaît de la liste : proposer l'impossible fait perdre du temps. */
    public function test_a_taken_ride_leaves_the_list(): void
    {
        $first = $this->providerWithPhone(50.8470, 4.3530);
        $second = $this->providerWithPhone(50.8480, 4.3540);

        [$request] = $this->searchWithProviders();
        app(AsapDispatchService::class)->accept($request, $first);

        $this->actingAs($second, 'sanctum')
            ->getJson('/api/provider/asap-offers')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    /** Accepter attribue la course — et l'intervention devient réelle. */
    public function test_accepting_through_the_api_assigns_the_ride(): void
    {
        $provider = $this->providerWithPhone(50.8470, 4.3530);
        [$request, , $booking] = $this->searchWithProviders();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/asap-offers/{$request->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', AsapStatus::ACCEPTED);

        $booking->refresh();
        $this->assertSame($provider->id, $booking->employe_id);
        $this->assertSame(BookingStatus::CONFIRME, $booking->status);
    }

    /**
     * Le second reçoit un refus EXPLICITE, pas un plantage.
     *
     * Un prestataire qui appuie sur « accepter » et voit une erreur technique croit à un bug de
     * l'application, pas à une course déjà prise.
     */
    public function test_the_second_provider_gets_a_clear_refusal(): void
    {
        $first = $this->providerWithPhone(50.8470, 4.3530);
        $second = $this->providerWithPhone(50.8480, 4.3540);

        [$request] = $this->searchWithProviders();
        app(AsapDispatchService::class)->accept($request, $first);

        $this->actingAs($second, 'sanctum')
            ->postJson("/api/provider/asap-offers/{$request->id}/accept")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Cette course vient d’être prise par un autre professionnel.');
    }

    /**
     * Une course ne se prend pas parce qu'on connaît son numéro.
     *
     * Sans cette vérification, un prestataire raflerait les courses des autres zones en énumérant
     * des identifiants.
     */
    public function test_a_provider_cannot_grab_a_ride_never_offered_to_them(): void
    {
        $this->providerWithPhone(50.8470, 4.3530);
        [$request] = $this->searchWithProviders();

        $outsider = $this->providerWithPhone(51.2100, 4.4200);

        $this->actingAs($outsider, 'sanctum')
            ->postJson("/api/provider/asap-offers/{$request->id}/accept")
            ->assertNotFound();

        $this->assertSame(AsapStatus::SEARCHING, $request->fresh()->status);
    }

    /**
     * Un refus est ENREGISTRÉ : la course n'est pas reproposée au prochain élargissement.
     *
     * Sans trace, le prestataire la refuserait à nouveau à chaque palier.
     */
    public function test_declining_is_remembered_across_expansions(): void
    {
        $provider = $this->providerWithPhone(50.8470, 4.3530);
        [$request] = $this->searchWithProviders();

        $this->actingAs($provider, 'sanctum')
            ->postJson("/api/provider/asap-offers/{$request->id}/decline", ['reason' => 'Trop loin'])
            ->assertOk();

        app(AsapDispatchService::class)->expand($request->fresh());

        $this->assertSame(
            1,
            AsapDispatchNotification::where('asap_dispatch_request_id', $request->id)
                ->where('user_id', $provider->id)->count(),
            'Le refus aurait dû empêcher une seconde proposition.',
        );

        // Et elle ne réapparaît pas dans sa liste.
        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/asap-offers')
            ->assertJsonCount(0, 'data');
    }

    /** Un client ne touche pas aux endpoints prestataire. */
    public function test_a_client_cannot_reach_the_offer_endpoints(): void
    {
        $this->providerWithPhone(50.8470, 4.3530);
        [$request] = $this->searchWithProviders();

        $this->actingAs(User::factory()->client()->create(), 'sanctum')
            ->getJson('/api/provider/asap-offers')
            ->assertForbidden();

        $this->actingAs(User::factory()->client()->create(), 'sanctum')
            ->postJson("/api/provider/asap-offers/{$request->id}/accept")
            ->assertForbidden();
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function peinture(): Trade
    {
        return Trade::where('slug', 'peinture')->firstOrFail();
    }

    /** Un prestataire situé, rattaché au métier, et joignable par notification. */
    private function providerWithPhone(float $lat, float $lng): User
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
            'trade_id' => $this->peinture()->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $raw = 'tok_'.uniqid();
        DeviceToken::create([
            'user_id' => $provider->id,
            'platform' => DeviceToken::PLATFORM_ANDROID,
            'provider' => DeviceToken::PROVIDER_MOCK,
            'token' => $raw,
            'token_hash' => DeviceToken::hashToken($raw),
            'preferences' => ['transactional' => true],
            'last_used_at' => now(),
        ]);

        return $provider->fresh();
    }

    /**
     * Une recherche ouverte par le chemin de production, prestataires déjà en place.
     *
     * @return array{0: AsapDispatchRequest, 1: User, 2: Booking}
     */
    private function searchWithProviders(): array
    {
        $client = User::factory()->client()->create();
        $manager = app(OrderDraftManager::class);

        $draft = $manager->resumeOrCreate('jeton-'.uniqid(), null, OrderMode::ASAP);
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

        app(OrderConfirmationService::class)->confirm($draft->fresh(), $client);

        return [
            AsapDispatchRequest::firstOrFail(),
            $client,
            Booking::firstOrFail(),
        ];
    }

    /** @noinspection PhpUnused — gardé pour la lisibilité des assertions sur le panier. */
    private function draft(): ?OrderDraft
    {
        return OrderDraft::first();
    }
}
