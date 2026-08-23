<?php

namespace Tests\Feature\OrderEngine;

use App\Livewire\OrderEngine\OrderJourney;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\Availability\AvailabilityService;
use App\Services\GeolocationV2\GeocodingResult;
use App\Services\GeolocationV2\GeocodingService;
use App\Services\OrderEngine\ProviderShortlist;
use App\Services\OrderEngine\SlotFinder;
use Database\Seeders\OrderEngineCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

/** Le mode planifié — les créneaux, et la raison de ceux qui n'en sont pas. */
class ScheduledModeTest extends TestCase
{
    use RefreshDatabase;

    private const LAT = 50.8467;

    private const LNG = 4.3525;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OrderEngineCatalogSeeder::class);
        // Un mardi, pour que « demain » ne tombe jamais un week-end au fil des exécutions.
        Carbon::setTestNow(Carbon::parse('2026-09-01 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    /** LA garantie : un créneau indisponible n'est pas masqué, il est expliqué. */
    public function test_an_unavailable_slot_is_shown_with_its_reason(): void
    {
        $trade = $this->peinture();
        $this->providerAt($trade, 50.8470, 4.3530);
        $this->fakeWindows([]); // Aucun agenda ouvert.

        $slots = app(SlotFinder::class)->forDay($trade, self::LAT, self::LNG, Carbon::tomorrow());

        $this->assertNotEmpty($slots, 'La grille a disparu : le client croirait le service en panne.');
        $this->assertFalse($slots[0]['available']);
        $this->assertSame('Aucun professionnel disponible sur ce créneau.', $slots[0]['reason']);
    }

    /** Une zone non couverte a sa propre raison — distincte d'un agenda plein. */
    public function test_an_uncovered_area_says_so_rather_than_blaming_the_calendars(): void
    {
        $slots = app(SlotFinder::class)->forDay($this->peinture(), self::LAT, self::LNG, Carbon::tomorrow());

        $this->assertNotEmpty($slots);
        $this->assertStringContainsString('couvre encore cette zone', $slots[0]['reason']);
    }

    /** Les créneaux ouverts viennent des agendas réels, pas d'une grille théorique. */
    public function test_open_slots_come_from_real_calendars(): void
    {
        $trade = $this->peinture();
        $this->providerAt($trade, 50.8470, 4.3530);

        $day = Carbon::tomorrow();
        $this->fakeWindows([[
            'start' => $day->copy()->setTime(14, 0)->toIso8601String(),
            'end' => $day->copy()->setTime(18, 0)->toIso8601String(),
        ]]);

        $slots = collect(app(SlotFinder::class)->forDay($trade, self::LAT, self::LNG, $day));

        $this->assertTrue($slots->firstWhere('available', true)['start']->format('H:i') === '14:00');
        $this->assertFalse($slots->first()['available'], 'Un créneau du matin est ouvert alors qu’aucun agenda ne l’est.');
    }

    /** Une fenêtre trop courte n'ouvre pas de créneau. */
    public function test_a_window_too_short_for_the_job_opens_nothing(): void
    {
        $trade = $this->peinture(); // 240 minutes estimées
        $this->providerAt($trade, 50.8470, 4.3530);

        $day = Carbon::tomorrow();
        $this->fakeWindows([[
            'start' => $day->copy()->setTime(14, 0)->toIso8601String(),
            'end' => $day->copy()->setTime(15, 0)->toIso8601String(),
        ]]);

        $this->assertEmpty(
            collect(app(SlotFinder::class)->forDay($trade, self::LAT, self::LNG, $day))->where('available', true),
        );
    }

    /** Un créneau trop proche a SA raison, distincte de « personne n'est libre ». */
    public function test_a_slot_too_soon_is_refused_for_its_own_reason(): void
    {
        $trade = $this->peinture();
        $this->providerAt($trade, 50.8470, 4.3530);

        $today = Carbon::today();
        $this->fakeWindows([[
            'start' => $today->copy()->setTime(8, 0)->toIso8601String(),
            'end' => $today->copy()->setTime(20, 0)->toIso8601String(),
        ]]);

        $slots = collect(app(SlotFinder::class)->forDay($trade, self::LAT, self::LNG, $today));
        $early = $slots->first(fn (array $s) => $s['start']->format('H:i') === '08:00');

        $this->assertFalse($early['available']);
        $this->assertSame('Trop proche pour être organisé.', $early['reason']);
    }

    /** Un agenda illisible retire un professionnel du compte, il ne fait pas tomber la page. */
    public function test_a_broken_calendar_degrades_instead_of_crashing(): void
    {
        $trade = $this->peinture();
        $this->providerAt($trade, 50.8470, 4.3530);

        $this->mock(AvailabilityService::class, function ($mock) {
            $mock->shouldReceive('getAvailableWindows')->andThrow(new \RuntimeException('agenda cassé'));
        });

        $slots = app(SlotFinder::class)->forDay($trade, self::LAT, self::LNG, Carbon::tomorrow());

        $this->assertNotEmpty($slots);
        $this->assertFalse($slots[0]['available']);
    }

    // ─── Le parcours ─────────────────────────────────────────────────────────────────────────

    /** Choisir un créneau l'enregistre sur la commande. */
    public function test_choosing_a_slot_stores_it_on_the_order(): void
    {
        $component = $this->journeyWithOpenCalendar();
        $day = Carbon::tomorrow();

        $component->call('selectDate', $day->toDateString())->call('selectSlot', '14:00');

        $this->assertSame('14:00', $component->instance()->selectedSlot);
        $this->assertSame(
            $day->copy()->setTime(14, 0)->format('Y-m-d H:i'),
            $component->instance()->draft()->fresh()->scheduled_at->format('Y-m-d H:i'),
        );
    }

    /** Un créneau indisponible ne se retient pas, même si l'interface est contournée. */
    public function test_an_unavailable_slot_cannot_be_chosen_from_the_outside(): void
    {
        $component = $this->journeyWithOpenCalendar();

        $component->call('selectDate', Carbon::tomorrow()->toDateString())->call('selectSlot', '08:00');

        $this->assertNull($component->instance()->selectedSlot);
    }

    /** Changer de jour invalide le créneau : une heure retenue peut ne pas exister le lendemain. */
    public function test_changing_the_day_clears_the_slot(): void
    {
        $component = $this->journeyWithOpenCalendar();

        $component->call('selectDate', Carbon::tomorrow()->toDateString())
            ->call('selectSlot', '14:00')
            ->call('selectDate', Carbon::tomorrow()->addDay()->toDateString());

        $this->assertNull($component->instance()->selectedSlot);
    }

    /** Le professionnel n'est JAMAIS obligatoire. */
    public function test_the_order_is_ready_without_choosing_a_provider(): void
    {
        $component = $this->journeyWithOpenCalendar();

        $component->call('selectDate', Carbon::tomorrow()->toDateString())->call('selectSlot', '14:00');

        $this->assertNull($component->instance()->selectedProviderId);
        $this->assertTrue($component->instance()->readyToConfirm());
    }

    /** Un prestataire hors de la liste proposée n'est pas retenu : la valeur vient du navigateur. */
    public function test_a_provider_outside_the_shortlist_is_refused(): void
    {
        $component = $this->journeyWithOpenCalendar();
        $stranger = User::factory()->create(['role' => User::ROLE_PROVIDER]);

        $component->call('selectProvider', $stranger->id);

        $this->assertNull($component->instance()->selectedProviderId);
    }

    /** Tant qu'aucun créneau n'est retenu, rien n'est prêt à confirmer. */
    public function test_nothing_is_ready_before_a_slot_is_chosen(): void
    {
        $this->assertFalse($this->journeyWithOpenCalendar()->instance()->readyToConfirm());
    }

    // ─── Liste des professionnels ────────────────────────────────────────────────────────────

    /** La liste porte ce qui aide à choisir, et rien de décoratif. */
    public function test_the_shortlist_carries_what_helps_choosing(): void
    {
        $trade = $this->peinture();
        $provider = $this->providerAt($trade, 50.8470, 4.3530);
        $provider->providerProfile->update(['rating_avg' => 4.6, 'rating_count' => 12]);

        $row = app(ProviderShortlist::class)->forTrade($trade, self::LAT, self::LNG)->firstOrFail();

        $this->assertSame(4.6, $row['rating']);
        $this->assertSame(12, $row['rating_count']);
        $this->assertLessThan(1, $row['distance_km']);
    }

    /** Une note qui repose sur un seul avis n'est pas affichée. */
    public function test_a_rating_built_on_almost_nothing_is_withheld(): void
    {
        $trade = $this->peinture();
        $provider = $this->providerAt($trade, 50.8470, 4.3530);
        $provider->providerProfile->update(['rating_avg' => 5.0, 'rating_count' => 1]);

        $this->assertNull(
            app(ProviderShortlist::class)->forTrade($trade, self::LAT, self::LNG)->firstOrFail()['rating'],
        );
    }

    // ─── Fabriques ───────────────────────────────────────────────────────────────────────────

    private function peinture(): Trade
    {
        return Trade::where('slug', 'peinture')->firstOrFail();
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

    /** @param  list<array{start: string, end: string}>  $windows */
    private function fakeWindows(array $windows): void
    {
        $this->mock(AvailabilityService::class, function ($mock) use ($windows) {
            $mock->shouldReceive('getAvailableWindows')->andReturn($windows);
        });
    }

    /** Un parcours prêt : métier choisi, adresse située, agenda grand ouvert l'après-midi. */
    private function journeyWithOpenCalendar()
    {
        $trade = $this->peinture();
        $this->providerAt($trade, 50.8470, 4.3530);

        $day = Carbon::tomorrow();
        $this->fakeWindows([[
            'start' => $day->copy()->setTime(13, 0)->toIso8601String(),
            'end' => $day->copy()->setTime(20, 0)->toIso8601String(),
        ]]);

        $this->mock(GeocodingService::class, function ($mock) {
            $mock->shouldReceive('geocode')->andReturn(new GeocodingResult(self::LAT, self::LNG, 'Bruxelles'));
        });

        return Livewire::test(OrderJourney::class)
            ->call('selectTrade', $trade->id)
            ->set('address', 'Rue de la Loi 1, 1000 Bruxelles');
    }
}
