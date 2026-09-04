<?php

namespace Tests\Feature\Livewire\Admin\CancellationV2;

use App\Livewire\Admin\CancellationV2\CancellationsCenter;
use App\Models\Booking;
use App\Models\BookingCancellationV2;
use App\Models\CancellationPolicy;
use App\Models\CancellationPolicyTier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class CancellationsCenterCoverageBatch17Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // `adminComplet` PLUTOT QUE `admin` : le centre des annulations exige desormais
        // `manage-finance` dans le composant lui-meme. Un admin nu passait ici parce que
        // `Livewire::test()` ne rejoue aucun middleware de route — pas parce qu'il en avait le droit.
        $this->admin = User::factory()->adminComplet()->create();
    }

    private function makeCancellation(array $attributes = []): BookingCancellationV2
    {
        $booking = Booking::factory()->create();

        return BookingCancellationV2::create(array_merge([
            'booking_id' => $booking->id,
            'actor_role' => 'client',
            'fee_percent_applied' => 0,
            'fee_amount_cents' => 0,
            'refund_amount_cents' => 0,
            'currency' => 'EUR',
            'refund_method' => 'stripe',
            'exempt_applied' => false,
            'booking_status_before' => 'confirme',
            'booking_status_after' => 'annule',
            'integrations_log' => [],
            'idempotency_key' => (string) Str::uuid(),
            'cancelled_at' => now()->subDay(),
            'metadata' => [],
        ], $attributes));
    }

    public function test_recent_tab_renders_with_kpis_and_recent_items(): void
    {
        $policy = CancellationPolicy::factory()->create(['is_active' => true]);
        $client = User::factory()->client()->create();

        $this->makeCancellation([
            'cancelled_by_user_id' => $client->id,
            'actor_role' => 'client',
            'policy_id' => $policy->id,
            'fee_amount_cents' => 1500,
        ]);

        Livewire::actingAs($this->admin)
            ->test(CancellationsCenter::class)
            ->assertOk()
            ->assertSet('tab', 'recent')
            ->assertViewHas('kpis', fn ($kpis) => $kpis['cancellations_7d'] === 1
                && $kpis['fees_collected_7d_cents'] === 1500
                && $kpis['active_policies'] === 1)
            ->assertViewHas('items', fn ($items) => $items->total() === 1);
    }

    public function test_recent_tab_filters_by_actor_role(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();

        $this->makeCancellation([
            'cancelled_by_user_id' => $client->id,
            'actor_role' => 'client',
        ]);
        $this->makeCancellation([
            'cancelled_by_user_id' => $provider->id,
            'actor_role' => 'provider',
        ]);

        Livewire::actingAs($this->admin)
            ->test(CancellationsCenter::class)
            ->set('filterActorRole', 'provider')
            ->assertOk()
            ->assertViewHas('items', fn ($items) => $items->total() === 1);
    }

    public function test_recent_tab_searches_by_canceller_email(): void
    {
        $needle = User::factory()->client()->create(['email' => 'needle-batch17@example.test']);
        $other = User::factory()->client()->create(['email' => 'other-batch17@example.test']);

        $this->makeCancellation([
            'cancelled_by_user_id' => $needle->id,
            'actor_role' => 'client',
        ]);
        $this->makeCancellation([
            'cancelled_by_user_id' => $other->id,
            'actor_role' => 'client',
        ]);

        Livewire::actingAs($this->admin)
            ->test(CancellationsCenter::class)
            ->set('search', 'needle-batch17')
            ->assertOk()
            ->assertViewHas('items', fn ($items) => $items->total() === 1);
    }

    public function test_overrides_tab_only_lists_overridden_rows(): void
    {
        $this->makeCancellation([
            'override_admin_user_id' => $this->admin->id,
        ]);
        $this->makeCancellation([
            'override_admin_user_id' => null,
        ]);

        Livewire::actingAs($this->admin)
            ->test(CancellationsCenter::class)
            ->set('tab', 'overrides')
            ->assertOk()
            ->assertViewHas('items', fn ($items) => $items->total() === 1)
            ->assertViewHas('kpis', fn ($kpis) => $kpis['overrides_7d'] === 1);
    }

    public function test_policies_tab_lists_policies_with_tier_counts(): void
    {
        $policy = CancellationPolicy::factory()->create();
        foreach ([1, 2] as $position) {
            CancellationPolicyTier::create([
                'policy_id' => $policy->id,
                'position' => $position,
                'min_hours_before' => 0,
                'max_hours_before' => 48,
                'fee_percent' => '25.00',
                'fee_flat_cents' => 0,
                'description' => 'Tier '.$position,
                'metadata' => [],
            ]);
        }

        Livewire::actingAs($this->admin)
            ->test(CancellationsCenter::class)
            ->set('tab', 'policies')
            ->assertOk()
            ->assertViewHas('items', fn ($items) => $items->total() === 1
                && (int) $items->first()->tiers_count === 2);
    }

    public function test_override_waives_fee_and_dispatches_success_toast(): void
    {
        $client = User::factory()->client()->create();
        $row = $this->makeCancellation([
            'cancelled_by_user_id' => $client->id,
            'actor_role' => 'client',
            'fee_amount_cents' => 2000,
            'refund_amount_cents' => 8000,
        ]);

        Livewire::actingAs($this->admin)
            ->test(CancellationsCenter::class)
            ->call('override', $row->id)
            ->assertDispatched('toast');

        $row->refresh();
        $this->assertSame(0, (int) $row->fee_amount_cents);
        $this->assertSame(10000, (int) $row->refund_amount_cents);
        $this->assertSame($this->admin->id, $row->override_admin_user_id);
    }

    public function test_override_with_short_reason_dispatches_error_toast(): void
    {
        $client = User::factory()->client()->create();
        $row = $this->makeCancellation([
            'cancelled_by_user_id' => $client->id,
            'actor_role' => 'client',
            'fee_amount_cents' => 2000,
            'refund_amount_cents' => 8000,
        ]);

        Livewire::actingAs($this->admin)
            ->test(CancellationsCenter::class)
            ->call('override', $row->id, 'short')
            ->assertDispatched('toast');

        $row->refresh();
        // Engine threw before persisting → fee untouched, no admin override recorded.
        $this->assertSame(2000, (int) $row->fee_amount_cents);
        $this->assertNull($row->override_admin_user_id);
    }
}
