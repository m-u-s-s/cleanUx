<?php

namespace Tests\Feature\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\User;
use App\Services\Analytics\AnalyticsCohorts;
use App\Services\Analytics\AnalyticsFunnel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsFunnelTest extends TestCase
{
    use RefreshDatabase;

    protected function makeEvent(string $name, int $userId, ?\DateTimeInterface $at = null): AnalyticsEvent
    {
        return AnalyticsEvent::create([
            'event_name' => $name,
            'event_category' => 'lifecycle',
            'user_id' => $userId,
            'occurred_at' => $at ?? now(),
        ]);
    }

    public function test_funnel_computes_counts_and_rates(): void
    {
        $alice = User::factory()->client()->create()->id;
        $bob = User::factory()->client()->create()->id;
        $carol = User::factory()->client()->create()->id;

        // All 3 did search
        $this->makeEvent('search.performed', $alice);
        $this->makeEvent('search.performed', $bob);
        $this->makeEvent('search.performed', $carol);

        // 2 viewed provider
        $this->makeEvent('provider.viewed', $alice);
        $this->makeEvent('provider.viewed', $bob);

        // 1 confirmed booking
        $this->makeEvent('booking.confirmed', $alice);

        $funnel = AnalyticsFunnel::for(now()->subHour(), now()->addHour())
            ->steps(['search.performed', 'provider.viewed', 'booking.confirmed'])
            ->groupBy('user_id')
            ->compute();

        $this->assertCount(3, $funnel);
        $this->assertSame(3, $funnel[0]['count']);
        $this->assertSame(2, $funnel[1]['count']);
        $this->assertSame(1, $funnel[2]['count']);

        $this->assertEqualsWithDelta(1.0, $funnel[0]['rate_from_start'], 0.001);
        $this->assertEqualsWithDelta(0.6667, $funnel[1]['rate_from_start'], 0.001);
        $this->assertEqualsWithDelta(0.3333, $funnel[2]['rate_from_start'], 0.001);

        $this->assertEqualsWithDelta(0.6667, $funnel[1]['rate_from_prev'], 0.001);
        $this->assertEqualsWithDelta(0.5, $funnel[2]['rate_from_prev'], 0.001);
    }

    public function test_funnel_returns_empty_when_no_steps(): void
    {
        $funnel = AnalyticsFunnel::for(now()->subHour(), now())
            ->steps([])
            ->compute();

        $this->assertSame([], $funnel);
    }

    public function test_funnel_distinct_users_per_step_not_inflated_by_duplicates(): void
    {
        $u = User::factory()->client()->create()->id;
        $this->makeEvent('search.performed', $u);
        $this->makeEvent('search.performed', $u);
        $this->makeEvent('search.performed', $u);

        $funnel = AnalyticsFunnel::for(now()->subHour(), now()->addHour())
            ->steps(['search.performed'])
            ->compute();

        $this->assertSame(1, $funnel[0]['count']);
    }

    public function test_cohort_weekly_groups_users_and_measures_retention(): void
    {
        $alice = User::factory()->client()->create()->id;
        $bob = User::factory()->client()->create()->id;

        $weekStart = now()->startOfWeek();

        // Both registered in the same week
        $this->makeEvent('user.registered', $alice, $weekStart->copy()->addDays(1));
        $this->makeEvent('user.registered', $bob, $weekStart->copy()->addDays(2));

        // Both made a booking in week 0
        $this->makeEvent('booking.created', $alice, $weekStart->copy()->addDays(2));
        $this->makeEvent('booking.created', $bob, $weekStart->copy()->addDays(3));

        // Alice made another booking in week 1
        $this->makeEvent('booking.created', $alice, $weekStart->copy()->addWeek()->addDays(1));

        $cohorts = AnalyticsCohorts::weekly(
            from: $weekStart,
            to: $weekStart->copy()->addWeeks(3),
            entryEvent: 'user.registered',
            returnEvent: 'booking.created',
            maxWeeks: 2,
        );

        $this->assertCount(1, $cohorts);
        $this->assertSame(2, $cohorts[0]['cohort_size']);
        $this->assertSame(2, $cohorts[0]['retention'][0]);
        $this->assertSame(1, $cohorts[0]['retention'][1]);
        $this->assertSame(0, $cohorts[0]['retention'][2]);
    }
}
