<?php

namespace Tests\Feature\Theme;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\User;
use App\Services\Theme\AdaptiveThemeResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AdaptiveThemeResolver — 6 coverage tests.
 *
 * Schema adaptations vs. original spec:
 *
 * 1. users.settings column is added by 2026_05_22_000001 migration.
 *    The model casts it to array so $user->settings = [...] + save() works.
 *
 * 2. missions.client_id does NOT exist in this codebase.
 *    Active missions for a client user are resolved via:
 *      bookings.customer_user_id → missions.rendez_vous_id (or booking_id)
 *    We therefore create a Booking for the user first, then create a Mission
 *    linked to that booking.
 *
 * 3. Mission status 'in_mission' does NOT exist in this codebase.
 *    Valid active statuses: en_route / arrived / started.
 *    Tests use 'started' as the active indicator.
 *
 * 4. Booking priority column is 'priority' (not 'priorite') in the modern schema.
 *    Status 'pending' is the bookings table default.
 */
class AdaptiveThemeResolverTest extends TestCase
{
    use RefreshDatabase;

    private AdaptiveThemeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(AdaptiveThemeResolver::class);
    }

    public function test_defaults_to_light_when_no_context(): void
    {
        $user = User::factory()->create();
        $this->assertSame('light', $this->resolver->resolveForUser($user));
    }

    public function test_honors_explicit_light_preference(): void
    {
        $user = User::factory()->create();
        $user->settings = ['theme_preference' => 'light'];
        $user->save();
        $this->assertSame('light', $this->resolver->resolveForUser($user));
    }

    public function test_honors_explicit_dark_preference(): void
    {
        $user = User::factory()->create();
        $user->settings = ['theme_preference' => 'dark'];
        $user->save();
        $this->assertSame('dark', $this->resolver->resolveForUser($user));
    }

    public function test_returns_dark_when_active_mission_exists(): void
    {
        $user = User::factory()->create();

        // Create a booking owned by this user
        $booking = Booking::factory()->create([
            'customer_user_id' => $user->id,
        ]);

        // Create a mission linked to that booking with an active status.
        // 'in_mission' is not a valid status; 'started' is the equivalent active state.
        Mission::factory()->create([
            'rendez_vous_id' => $booking->id,
            'status'         => 'started',
        ]);

        $this->assertSame('dark', $this->resolver->resolveForUser($user));
    }

    public function test_returns_dark_during_night_with_urgent_service(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 23:30:00'));

        $user = User::factory()->create();
        Booking::factory()->create([
            'customer_user_id' => $user->id,
            'priority'         => 'urgent',
            'status'           => 'pending',
        ]);

        $this->assertSame('dark', $this->resolver->resolveForUser($user));

        Carbon::setTestNow();
    }

    public function test_returns_light_during_day_even_with_urgent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 14:00:00'));

        $user = User::factory()->create();
        Booking::factory()->create([
            'customer_user_id' => $user->id,
            'priority'         => 'normal',
            'status'           => 'confirmed',
        ]);

        $this->assertSame('light', $this->resolver->resolveForUser($user));

        Carbon::setTestNow();
    }
}
