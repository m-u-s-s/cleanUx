<?php

namespace Tests\Feature\Livewire\Admin\Analytics;

use App\Livewire\Admin\Analytics\CancellationReasonsCenter;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CancellationReasonsCenterCoverageBatch15Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function seedCancelled(string $reason, string $by, array $overrides = []): Booking
    {
        $booking = Booking::factory()->create(array_merge([
            'status' => 'annule',
            'cancelled_at' => now()->subDays(3),
            'cancellation_reason' => $reason,
            'cancelled_by' => $by,
        ], $overrides));

        // cancellation_fee_amount is guarded; set it directly to exercise the SUM().
        if (array_key_exists('cancellation_fee_amount', $overrides)) {
            $booking->forceFill(['cancellation_fee_amount' => $overrides['cancellation_fee_amount']])->save();
        }

        return $booking;
    }

    public function test_renders_with_defaults_and_aggregates_reasons(): void
    {
        $this->seedCancelled('client_unavailable', 'client', ['cancellation_fee_amount' => 25.00]);
        $this->seedCancelled('client_unavailable', 'client');
        $this->seedCancelled('provider_no_show', 'provider');

        Livewire::actingAs($this->admin)
            ->test(CancellationReasonsCenter::class)
            ->assertOk()
            ->assertSet('period', '30d')
            ->assertSet('groupBy', 'reason')
            ->assertViewHas('totalCancelled', 3)
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 2 && (int) $rows->first()->count === 2)
            ->assertViewHas('byCancelledBy', fn ($rows) => $rows->count() === 2)
            ->assertViewHas('cancellationRate', fn ($rate) => $rate > 0);
    }

    public function test_set_period_and_group_by_mutate_state(): void
    {
        $this->seedCancelled('client_unavailable', 'client');

        Livewire::actingAs($this->admin)
            ->test(CancellationReasonsCenter::class)
            ->call('setPeriod', '7d')
            ->assertSet('period', '7d')
            ->assertOk()
            ->call('setGroupBy', 'cancelled_by')
            ->assertSet('groupBy', 'cancelled_by')
            ->assertOk()
            ->call('setPeriod', '90d')
            ->assertSet('period', '90d')
            ->assertOk()
            ->call('setPeriod', 'all')
            ->assertSet('period', 'all')
            ->assertOk();
    }

    public function test_unknown_period_falls_back_to_default_window(): void
    {
        $this->seedCancelled('client_unavailable', 'client');

        Livewire::actingAs($this->admin)
            ->test(CancellationReasonsCenter::class)
            ->call('setPeriod', 'weird')
            ->assertSet('period', 'weird')
            ->assertOk()
            ->assertViewHas('totalCancelled', 1);
    }

    public function test_blank_and_null_reasons_are_excluded_from_rows(): void
    {
        $this->seedCancelled('', 'client');
        $this->seedCancelled('valid_reason', 'admin');
        $this->seedCancelled('valid_reason', 'admin', ['cancellation_reason' => null]);

        Livewire::actingAs($this->admin)
            ->test(CancellationReasonsCenter::class)
            ->assertOk()
            // 3 cancelled bookings counted, but only 1 distinct non-empty reason in rows.
            ->assertViewHas('totalCancelled', 3)
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 1 && $rows->first()->cancellation_reason === 'valid_reason');
    }

    public function test_zero_cancellations_yields_zero_rate(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CancellationReasonsCenter::class)
            ->assertOk()
            ->assertViewHas('totalCancelled', 0)
            ->assertViewHas('cancellationRate', 0)
            ->assertViewHas('rows', fn ($rows) => $rows->isEmpty())
            ->assertViewHas('byCancelledBy', fn ($rows) => $rows->isEmpty());
    }
}
