<?php

namespace Tests\Feature\Availability;

use App\Models\AvailabilityException;
use App\Models\AvailabilityHold;
use App\Models\AvailabilitySlot;
use App\Models\User;
use App\Services\Availability\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('availability.enabled', true);
        Config::set('availability.max_lookahead_days', 90);
    }

    protected function provider(): User
    {
        return User::factory()->employe()->create();
    }

    protected function nextMonday(): CarbonImmutable
    {
        return CarbonImmutable::now()->next(CarbonImmutable::MONDAY)->startOfDay();
    }

    public function test_provider_with_no_slots_has_no_windows(): void
    {
        $provider = $this->provider();
        $monday = $this->nextMonday();

        $windows = app(AvailabilityService::class)->getAvailableWindows(
            $provider, $monday, $monday->copy()->addDays(7),
        );

        $this->assertSame([], $windows);
    }

    public function test_weekly_slot_produces_windows_for_matching_weekday(): void
    {
        $provider = $this->provider();
        AvailabilitySlot::create([
            'provider_user_id' => $provider->id,
            'weekday' => AvailabilitySlot::WEEKDAY_MONDAY,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'timezone' => 'Europe/Brussels',
            'is_active' => true,
        ]);

        $monday = $this->nextMonday();
        $windows = app(AvailabilityService::class)->getAvailableWindows(
            $provider, $monday, $monday->copy()->addDays(7),
        );

        $this->assertCount(1, $windows);
        $this->assertSame('09:00', $windows[0]['start']->format('H:i'));
        $this->assertSame('17:00', $windows[0]['end']->format('H:i'));
    }

    public function test_is_available_true_within_slot_window(): void
    {
        $provider = $this->provider();
        AvailabilitySlot::create([
            'provider_user_id' => $provider->id,
            'weekday' => AvailabilitySlot::WEEKDAY_MONDAY,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        $monday = $this->nextMonday();
        $ok = app(AvailabilityService::class)->isAvailable(
            $provider,
            $monday->copy()->setTime(10, 0),
            $monday->copy()->setTime(12, 0),
        );

        $this->assertTrue($ok);
    }

    public function test_is_available_false_outside_slot_window(): void
    {
        $provider = $this->provider();
        AvailabilitySlot::create([
            'provider_user_id' => $provider->id,
            'weekday' => AvailabilitySlot::WEEKDAY_MONDAY,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        $monday = $this->nextMonday();
        $ok = app(AvailabilityService::class)->isAvailable(
            $provider,
            $monday->copy()->setTime(18, 0),
            $monday->copy()->setTime(19, 0),
        );

        $this->assertFalse($ok);
    }

    public function test_closed_exception_blocks_entire_day(): void
    {
        $provider = $this->provider();
        AvailabilitySlot::create([
            'provider_user_id' => $provider->id,
            'weekday' => AvailabilitySlot::WEEKDAY_MONDAY,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        $monday = $this->nextMonday();

        AvailabilityException::create([
            'provider_user_id' => $provider->id,
            'date' => $monday->format('Y-m-d'),
            'exception_type' => AvailabilityException::TYPE_CLOSED,
        ]);

        $windows = app(AvailabilityService::class)->getAvailableWindows(
            $provider, $monday, $monday->copy()->endOfDay(),
        );

        $this->assertSame([], $windows);
    }

    public function test_partial_exception_subtracts_time_range(): void
    {
        $provider = $this->provider();
        AvailabilitySlot::create([
            'provider_user_id' => $provider->id,
            'weekday' => AvailabilitySlot::WEEKDAY_MONDAY,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        $monday = $this->nextMonday();

        AvailabilityException::create([
            'provider_user_id' => $provider->id,
            'date' => $monday->format('Y-m-d'),
            'exception_type' => AvailabilityException::TYPE_PARTIAL,
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
        ]);

        $windows = app(AvailabilityService::class)->getAvailableWindows(
            $provider, $monday, $monday->copy()->endOfDay(),
        );

        $this->assertCount(2, $windows);
        $this->assertSame('09:00', $windows[0]['start']->format('H:i'));
        $this->assertSame('12:00', $windows[0]['end']->format('H:i'));
        $this->assertSame('13:00', $windows[1]['start']->format('H:i'));
        $this->assertSame('17:00', $windows[1]['end']->format('H:i'));
    }

    public function test_open_override_replaces_slot_for_that_day(): void
    {
        $provider = $this->provider();
        AvailabilitySlot::create([
            'provider_user_id' => $provider->id,
            'weekday' => AvailabilitySlot::WEEKDAY_SUNDAY,
            'start_time' => '00:00:00',
            'end_time' => '00:00:00',
            'is_active' => false,
        ]);

        $sunday = CarbonImmutable::now()->next(CarbonImmutable::SUNDAY)->startOfDay();

        AvailabilityException::create([
            'provider_user_id' => $provider->id,
            'date' => $sunday->format('Y-m-d'),
            'exception_type' => AvailabilityException::TYPE_OPEN_OVERRIDE,
            'start_time' => '10:00:00',
            'end_time' => '14:00:00',
            'reason' => 'special opening',
        ]);

        $windows = app(AvailabilityService::class)->getAvailableWindows(
            $provider, $sunday, $sunday->copy()->endOfDay(),
        );

        $this->assertCount(1, $windows);
        $this->assertSame('10:00', $windows[0]['start']->format('H:i'));
        $this->assertSame('14:00', $windows[0]['end']->format('H:i'));
    }

    public function test_active_hold_blocks_is_available(): void
    {
        $provider = $this->provider();
        AvailabilitySlot::create([
            'provider_user_id' => $provider->id,
            'weekday' => AvailabilitySlot::WEEKDAY_MONDAY,
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_active' => true,
        ]);

        $monday = $this->nextMonday();

        AvailabilityHold::create([
            'provider_user_id' => $provider->id,
            'starts_at' => $monday->copy()->setTime(10, 0),
            'ends_at' => $monday->copy()->setTime(12, 0),
            'reason' => 'booking_flow',
            'expires_at' => now()->addMinutes(10),
        ]);

        $ok = app(AvailabilityService::class)->isAvailable(
            $provider,
            $monday->copy()->setTime(10, 30),
            $monday->copy()->setTime(11, 0),
        );

        $this->assertFalse($ok);
    }

    public function test_create_hold_is_idempotent(): void
    {
        $provider = $this->provider();
        $monday = $this->nextMonday();
        $svc = app(AvailabilityService::class);

        $a = $svc->createHold($provider, $monday->copy()->setTime(10, 0), $monday->copy()->setTime(11, 0), null, 'flow', 'idem-001');
        $b = $svc->createHold($provider, $monday->copy()->setTime(10, 0), $monday->copy()->setTime(11, 0), null, 'flow', 'idem-001');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, AvailabilityHold::count());
    }

    public function test_release_hold_marks_released_at(): void
    {
        $provider = $this->provider();
        $svc = app(AvailabilityService::class);

        $hold = $svc->createHold($provider, now()->addHour(), now()->addHours(2));
        $this->assertNull($hold->released_at);

        $svc->releaseHold($hold);

        $hold->refresh();
        $this->assertNotNull($hold->released_at);
    }

    public function test_cleanup_expired_holds_marks_released(): void
    {
        $provider = $this->provider();

        $expired = AvailabilityHold::create([
            'provider_user_id' => $provider->id,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
            'reason' => 'booking_flow',
            'expires_at' => now()->subMinute(),
        ]);

        $count = app(AvailabilityService::class)->cleanupExpiredHolds();

        $this->assertSame(1, $count);
        $expired->refresh();
        $this->assertNotNull($expired->released_at);
    }
}
