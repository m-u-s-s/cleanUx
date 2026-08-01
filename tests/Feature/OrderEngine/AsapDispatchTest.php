<?php

namespace Tests\Feature\OrderEngine;

use App\Models\AsapDispatchRequest;
use App\Models\OrderDraftItem;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\OrderEngine\AsapDispatchService;
use App\Services\OrderEngine\OrderDraftManager;
use App\Support\Domain\AsapStatus;
use App\Support\Domain\OrderMode;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Le service immédiat : chercher, accepter, annuler — sans jamais surprendre le client.
 *
 * Trois garanties tenues ici. L'état ne saute pas. Les frais d'annulation sont annonçables AVANT
 * le clic, et le montant appliqué est exactement celui qui a été annoncé. Et quand personne ne
 * répond, il reste toujours une suite à proposer.
 */
class AsapDispatchTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 50.8467;

    private const LNG = 4.3525;

    private AsapDispatchService $dispatch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
        $this->dispatch = app(AsapDispatchService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─── Recherche ───────────────────────────────────────────────────────────────────────────

    public function test_opening_a_search_counts_the_providers_actually_notified(): void
    {
        $trade = $this->plomberie();
        $this->providerAt($trade, 50.8470, 4.3530);
        $this->providerAt($trade, 50.8480, 4.3540);

        $request = $this->dispatch->open($this->item($trade), self::LAT, self::LNG);

        $this->assertSame(AsapStatus::SEARCHING, $request->status);
        $this->assertSame(2, $request->notified_count);
    }

    /** Rouvrir ne relance pas : deux recherches préviendraient deux fois et pourraient être acceptées deux fois. */
    public function test_reopening_returns_the_same_search(): void
    {
        $item = $this->item($this->plomberie());

        $first = $this->dispatch->open($item, self::LAT, self::LNG);
        $second = $this->dispatch->open($item, self::LAT, self::LNG);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, AsapDispatchRequest::count());
    }

    public function test_expanding_widens_the_radius_and_finds_more(): void
    {
        $trade = $this->plomberie();
        $this->providerAt($trade, 50.8470, 4.3530);       // ~50 m
        $this->providerAt($trade, 50.9000, 4.3525);       // ~6 km : hors du rayon initial

        $request = $this->dispatch->open($this->item($trade), self::LAT, self::LNG);
        $this->assertSame(1, $request->notified_count);

        $expanded = $this->dispatch->expand($request);

        $this->assertSame(10000, $expanded->radius_m);
        $this->assertSame(2, $expanded->notified_count);
    }

    /**
     * Le rayon est BORNÉ.
     *
     * Chercher indéfiniment enverrait un professionnel à quarante kilomètres pour une intervention
     * d'une heure : le client attendrait un trajet qu'il n'a pas demandé.
     */
    public function test_the_radius_stops_expanding_at_its_ceiling(): void
    {
        $request = $this->dispatch->open($this->item($this->plomberie()), self::LAT, self::LNG);

        foreach (range(1, 10) as $ignored) {
            $request = $this->dispatch->expand($request);
        }

        $this->assertSame(20000, $request->radius_m);
    }

    /** Le compteur ne redescend jamais : le voir baisser laisserait croire à un désistement. */
    public function test_the_notified_counter_never_goes_down(): void
    {
        $trade = $this->plomberie();
        $provider = $this->providerAt($trade, 50.8470, 4.3530);

        $request = $this->dispatch->open($this->item($trade), self::LAT, self::LNG);
        $this->assertSame(1, $request->notified_count);

        // Le prestataire s'éloigne : le compteur de ceux qui ont été prévenus ne change pas.
        $provider->providerProfile->update(['current_lat' => 52.0, 'current_lng' => 6.0]);

        $this->assertSame(1, $this->dispatch->expand($request)->notified_count);
    }

    public function test_a_search_times_out_after_the_configured_delay(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01 10:00:00'));
        $request = $this->dispatch->open($this->item($this->plomberie()), self::LAT, self::LNG);

        $this->assertFalse($this->dispatch->hasTimedOut($request));

        Carbon::setTestNow(Carbon::parse('2026-09-01 10:04:00'));

        $this->assertTrue($this->dispatch->hasTimedOut($request->fresh()));
    }

    /**
     * JAMAIS de cul-de-sac : au moins une suite est toujours proposée.
     *
     * Un écran d'attente qui finit sur « personne n'est disponible » sans rien d'autre est un bug
     * produit.
     */
    public function test_an_expired_search_always_offers_a_way_forward(): void
    {
        $request = $this->dispatch->open($this->item($this->plomberie()), self::LAT, self::LNG);
        $expired = $this->dispatch->expire($request);

        $ways = collect($this->dispatch->waysForward($expired));

        $this->assertGreaterThanOrEqual(2, $ways->count());
        $this->assertTrue($ways->contains('key', 'schedule'));
        $this->assertTrue($ways->contains('key', 'notify'));
    }

    /** Même au rayon maximal, deux portes restent ouvertes. */
    public function test_a_maxed_out_search_still_offers_two_doors(): void
    {
        $request = $this->dispatch->open($this->item($this->plomberie()), self::LAT, self::LNG);
        $request->update(['radius_m' => 20000]);

        $keys = collect($this->dispatch->waysForward($request->fresh()))->pluck('key');

        $this->assertFalse($keys->contains('expand'));
        $this->assertSame(['schedule', 'notify'], $keys->all());
    }

    // ─── Acceptation ─────────────────────────────────────────────────────────────────────────

    public function test_accepting_starts_the_free_cancellation_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01 10:00:00'));
        $trade = $this->plomberie();
        $request = $this->dispatch->open($this->item($trade), self::LAT, self::LNG);

        $accepted = $this->dispatch->accept($request, $this->providerAt($trade, 50.8470, 4.3530));

        $this->assertSame(AsapStatus::ACCEPTED, $accepted->status);
        $this->assertSame('2026-09-01 10:03:00', $accepted->free_cancellation_until->format('Y-m-d H:i:s'));
    }

    /**
     * Deux prestataires ne peuvent pas prendre la même course.
     *
     * Sans le verrou, ils partiraient tous les deux et un seul serait payé.
     */
    public function test_a_second_provider_cannot_take_the_same_job(): void
    {
        $trade = $this->plomberie();
        $request = $this->dispatch->open($this->item($trade), self::LAT, self::LNG);

        $this->dispatch->accept($request, $this->providerAt($trade, 50.8470, 4.3530));

        $this->expectException(ValidationException::class);
        $this->dispatch->accept($request->fresh(), $this->providerAt($trade, 50.8480, 4.3540));
    }

    /**
     * La fenêtre est FIGÉE à l'acceptation.
     *
     * Celle qu'on annonce au client est celle qui s'applique, même si la configuration change
     * entre-temps.
     */
    public function test_the_free_window_survives_a_configuration_change(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01 10:00:00'));
        $trade = $this->plomberie();
        $accepted = $this->dispatch->accept(
            $this->dispatch->open($this->item($trade), self::LAT, self::LNG),
            $this->providerAt($trade, 50.8470, 4.3530),
        );

        config(['order_engine.asap_free_cancellation_minutes' => 0]);

        $this->assertTrue($accepted->fresh()->cancellationIsFree());
    }

    // ─── Annulation ──────────────────────────────────────────────────────────────────────────

    /** Pendant la recherche, personne ne s'est déplacé : l'annulation est gratuite. */
    public function test_cancelling_during_the_search_is_always_free(): void
    {
        $request = $this->dispatch->open($this->item($this->plomberie()), self::LAT, self::LNG);

        $quote = $this->dispatch->quoteCancellation($request);

        $this->assertTrue($quote['free']);
        $this->assertSame(0, $this->dispatch->cancel($request)->cancellation_fee_cents);
    }

    /**
     * LA garantie : les frais s'annoncent AVANT le clic, et le montant appliqué est celui annoncé.
     *
     * Des frais découverts après font perdre un client pour de bon, et le montant récupéré ne
     * compense jamais.
     */
    public function test_the_fee_is_announced_before_it_applies_and_matches(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01 10:00:00'));
        $trade = $this->plomberie();
        $accepted = $this->dispatch->accept(
            $this->dispatch->open($this->item($trade), self::LAT, self::LNG),
            $this->providerAt($trade, 50.8470, 4.3530),
        );

        Carbon::setTestNow(Carbon::parse('2026-09-01 10:10:00'));

        $announced = $this->dispatch->quoteCancellation($accepted->fresh());
        $this->assertFalse($announced['free']);
        $this->assertSame(500, $announced['fee_cents']);
        $this->assertStringContainsString('5,00', $announced['reason']);

        $cancelled = $this->dispatch->cancel($accepted->fresh());
        $this->assertSame($announced['fee_cents'], $cancelled->cancellation_fee_cents);
    }

    /** Dans la fenêtre, l'annulation reste gratuite — et le temps restant est annoncé. */
    public function test_the_remaining_free_time_is_announced(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-01 10:00:00'));
        $trade = $this->plomberie();
        $accepted = $this->dispatch->accept(
            $this->dispatch->open($this->item($trade), self::LAT, self::LNG),
            $this->providerAt($trade, 50.8470, 4.3530),
        );

        Carbon::setTestNow(Carbon::parse('2026-09-01 10:01:00'));

        $quote = $this->dispatch->quoteCancellation($accepted->fresh());

        $this->assertTrue($quote['free']);
        $this->assertSame(120, $quote['free_seconds_left']);
    }

    // ─── Machine à états ─────────────────────────────────────────────────────────────────────

    public function test_the_journey_runs_through_its_states(): void
    {
        $trade = $this->plomberie();
        $request = $this->dispatch->accept(
            $this->dispatch->open($this->item($trade), self::LAT, self::LNG),
            $this->providerAt($trade, 50.8470, 4.3530),
        );

        foreach ([AsapStatus::EN_ROUTE, AsapStatus::ARRIVED, AsapStatus::IN_PROGRESS, AsapStatus::COMPLETED] as $state) {
            $request = $this->dispatch->transition($request, $state);
            $this->assertSame($state, $request->status);
        }

        $this->assertNotNull($request->completed_at);
        $this->assertFalse($request->isOpen());
    }

    /** L'état ne saute pas : une recherche ne devient pas une intervention terminée. */
    public function test_the_state_never_jumps(): void
    {
        $request = $this->dispatch->open($this->item($this->plomberie()), self::LAT, self::LNG);

        $this->expectException(ValidationException::class);
        $this->dispatch->transition($request, AsapStatus::COMPLETED);
    }

    /**
     * Une intervention COMMENCÉE ne s'annule plus.
     *
     * On la termine, et le litige se règle après : annuler ici priverait le prestataire d'un
     * travail déjà fourni.
     */
    public function test_a_started_job_can_no_longer_be_cancelled(): void
    {
        $trade = $this->plomberie();
        $request = $this->dispatch->accept(
            $this->dispatch->open($this->item($trade), self::LAT, self::LNG),
            $this->providerAt($trade, 50.8470, 4.3530),
        );

        $request = $this->dispatch->transition($request, AsapStatus::EN_ROUTE);
        $request = $this->dispatch->transition($request, AsapStatus::ARRIVED);
        $request = $this->dispatch->transition($request, AsapStatus::IN_PROGRESS);

        $this->expectException(ValidationException::class);
        $this->dispatch->cancel($request);
    }

    /** Une recherche expirée peut repartir, avec un rayon plus large. */
    public function test_an_expired_search_can_start_again_wider(): void
    {
        $request = $this->dispatch->open($this->item($this->plomberie()), self::LAT, self::LNG);
        $expired = $this->dispatch->expire($request);

        $retried = $this->dispatch->retry($expired);

        $this->assertSame(AsapStatus::SEARCHING, $retried->status);
        $this->assertSame(10000, $retried->radius_m);
    }

    /** Chaque état a un libellé destiné au client, pas un mot de code. */
    public function test_every_state_speaks_to_the_client(): void
    {
        foreach (AsapStatus::all() as $status) {
            $this->assertNotSame($status, AsapStatus::label($status), "L’état « {$status} » n’a pas de libellé client.");
        }
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function plomberie(): Trade
    {
        return Trade::where('slug', 'plumbing')->firstOrFail();
    }

    private function item(Trade $trade): OrderDraftItem
    {
        $manager = app(OrderDraftManager::class);
        $draft = $manager->resumeOrCreate('jeton-'.uniqid(), null, OrderMode::ASAP);

        return $manager->itemFor($draft, $trade);
    }

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

        return $provider->fresh('providerProfile');
    }
}
