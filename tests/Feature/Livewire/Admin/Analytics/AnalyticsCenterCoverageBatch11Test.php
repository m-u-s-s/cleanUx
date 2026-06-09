<?php

namespace Tests\Feature\Livewire\Admin\Analytics;

use App\Livewire\Admin\Analytics\AnalyticsCenter;
use App\Models\AnalyticsEvent;
use App\Models\AnalyticsSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsCenterCoverageBatch11Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function seedEvent(string $name, array $overrides = []): AnalyticsEvent
    {
        return AnalyticsEvent::create(array_merge([
            'event_name' => $name,
            'event_category' => AnalyticsEvent::CATEGORY_FUNNEL,
            'user_id' => null,
            'occurred_at' => now()->subHour(),
        ], $overrides));
    }

    public function test_renders_ok_with_defaults_and_passes_view_data(): void
    {
        $user = User::factory()->client()->create();

        $this->seedEvent('search.performed', ['user_id' => $user->id, 'revenue_cents' => 1500]);
        $this->seedEvent('booking.created', ['user_id' => $user->id, 'revenue_cents' => 2500]);

        AnalyticsSession::create([
            'session_id' => 'sess-default-1',
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'last_seen_at' => now()->subMinutes(10),
        ]);

        Livewire::actingAs($this->admin)
            ->test(AnalyticsCenter::class)
            ->assertOk()
            ->assertSet('rangeKey', '7d')
            ->assertSet('funnelType', 'booking')
            ->assertViewHas('kpis', function (array $kpis) {
                return $kpis['events'] === 2
                    && $kpis['unique_users'] === 1
                    && $kpis['sessions'] === 1
                    && $kpis['revenue_cents'] === 4000;
            })
            ->assertViewHas('topEvents', fn ($rows) => $rows->count() === 2)
            ->assertViewHas('funnel')
            ->assertViewHas('from')
            ->assertViewHas('to');
    }

    public function test_range_24h_excludes_older_events(): void
    {
        $recent = $this->seedEvent('search.performed', ['occurred_at' => now()->subHours(2)]);
        $old = $this->seedEvent('search.performed', ['occurred_at' => now()->subDays(5)]);

        Livewire::actingAs($this->admin)
            ->test(AnalyticsCenter::class)
            ->set('rangeKey', '24h')
            ->assertOk()
            ->assertViewHas('kpis', fn (array $kpis) => $kpis['events'] === 1);
    }

    public function test_range_30d_includes_older_events(): void
    {
        $this->seedEvent('search.performed', ['occurred_at' => now()->subDays(20)]);
        $this->seedEvent('search.performed', ['occurred_at' => now()->subDays(2)]);

        Livewire::actingAs($this->admin)
            ->test(AnalyticsCenter::class)
            ->set('rangeKey', '30d')
            ->assertOk()
            ->assertViewHas('kpis', fn (array $kpis) => $kpis['events'] === 2);
    }

    public function test_booking_funnel_computes_steps(): void
    {
        $u1 = User::factory()->client()->create()->id;
        $u2 = User::factory()->client()->create()->id;

        $this->seedEvent('search.performed', ['user_id' => $u1]);
        $this->seedEvent('search.performed', ['user_id' => $u2]);
        $this->seedEvent('provider.viewed', ['user_id' => $u1]);
        $this->seedEvent('booking.created', ['user_id' => $u1]);

        Livewire::actingAs($this->admin)
            ->test(AnalyticsCenter::class)
            ->set('funnelType', 'booking')
            ->assertOk()
            ->assertViewHas('funnel', function (array $funnel) {
                return count($funnel) === 6
                    && $funnel[0]['step'] === 'search.performed'
                    && $funnel[0]['count'] === 2
                    && $funnel[1]['count'] === 1;
            });
    }

    public function test_registration_funnel_uses_registration_steps(): void
    {
        $u1 = User::factory()->client()->create()->id;

        $this->seedEvent('page.viewed', ['user_id' => $u1]);
        $this->seedEvent('user.registered', ['user_id' => $u1]);

        Livewire::actingAs($this->admin)
            ->test(AnalyticsCenter::class)
            ->set('funnelType', 'registration')
            ->assertOk()
            ->assertViewHas('funnel', function (array $funnel) {
                return count($funnel) === 3
                    && $funnel[0]['step'] === 'page.viewed'
                    && $funnel[1]['step'] === 'user.registered';
            });
    }

    public function test_unknown_funnel_type_yields_empty_funnel(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AnalyticsCenter::class)
            ->set('funnelType', 'nope')
            ->assertOk()
            ->assertViewHas('funnel', fn (array $funnel) => $funnel === []);
    }
}
