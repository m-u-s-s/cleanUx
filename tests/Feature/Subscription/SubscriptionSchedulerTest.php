<?php

namespace Tests\Feature\Subscription;

use App\Models\Booking;
use App\Models\ClientSubscription;
use App\Models\User;
use App\Services\Subscription\SubscriptionScheduler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** L12 — the recurring scheduler must batch its duplicate-check (one query, not one per subscription) and stay idempotent. */
class SubscriptionSchedulerTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_one_booking_per_active_subscription(): void
    {
        ClientSubscription::factory()->create([
            'client_id' => User::factory()->create()->id,
            'day_of_week' => 1,
            'heure' => '09:00',
            'status' => 'active',
        ]);
        ClientSubscription::factory()->create([
            'client_id' => User::factory()->create()->id,
            'day_of_week' => 2,
            'heure' => '10:00',
            'status' => 'active',
        ]);

        app(SubscriptionScheduler::class)->generateUpcomingBookings();

        $this->assertSame(2, Booking::count(), 'one booking generated per active subscription');
    }

    public function test_is_idempotent_across_runs(): void
    {
        ClientSubscription::factory()->create([
            'client_id' => User::factory()->create()->id,
            'day_of_week' => 3,
            'heure' => '11:00',
            'status' => 'active',
        ]);

        $scheduler = app(SubscriptionScheduler::class);
        $scheduler->generateUpcomingBookings();
        $scheduler->generateUpcomingBookings();

        $this->assertSame(1, Booking::count(), 'must not duplicate on a second run');
    }

    public function test_skips_when_a_booking_already_exists_at_the_slot(): void
    {
        $client = User::factory()->create();
        $sub = ClientSubscription::factory()->create([
            'client_id' => $client->id,
            'day_of_week' => 4,
            'heure' => '12:00',
            'status' => 'active',
        ]);

        $nextDate = now()->next(4);
        Booking::create([
            'client_id' => $client->id,
            'date' => $nextDate,
            'heure' => '12:00',
            'status' => 'confirme',
        ]);

        app(SubscriptionScheduler::class)->generateUpcomingBookings();

        // Only the pre-existing booking — no recurring duplicate created at the same slot.
        $this->assertSame(1, Booking::where('client_id', $client->id)->count());
    }
}
