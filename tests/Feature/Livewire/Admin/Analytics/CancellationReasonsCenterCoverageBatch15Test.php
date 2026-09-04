<?php

namespace Tests\Feature\Livewire\Admin\Analytics;

use App\Livewire\Admin\Analytics\CancellationReasonsCenter;
use App\Models\BookingCancellationV2;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * `booking_cancellations_v2` fait foi : l'ecran ne lit plus les colonnes miroir de `bookings`.
 *
 * Ce test semait `'client'` et `'provider'` dans `bookings.cancelled_by` — une colonne
 * d'IDENTIFIANTS. Il disait donc, sans le savoir, que la carte voulait afficher un ROLE.
 */
class CancellationReasonsCenterCoverageBatch15Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function seedCancelled(?string $reason, string $actorRole, int $feeCents = 0): BookingCancellationV2
    {
        return BookingCancellationV2::factory()->create([
            'cancelled_at' => now()->subDays(3),
            'reason_text' => $reason,
            'reason_code' => null,
            'actor_role' => $actorRole,
            'fee_amount_cents' => $feeCents,
        ]);
    }

    public function test_renders_with_defaults_and_aggregates_reasons(): void
    {
        $this->seedCancelled('client_unavailable', 'client', 2500);
        $this->seedCancelled('client_unavailable', 'client');
        $this->seedCancelled('provider_no_show', 'provider');

        Livewire::actingAs($this->admin)
            ->test(CancellationReasonsCenter::class)
            ->assertOk()
            ->assertSet('period', '30d')
            ->assertSet('groupBy', 'reason')
            ->assertViewHas('totalCancelled', 3)
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 2 && $rows->first()['count'] === 2)
            ->assertViewHas('byActorRole', fn ($rows) => $rows->count() === 2)
            ->assertViewHas('cancellationRate', fn ($rate) => $rate > 0);
    }

    public function test_les_frais_viennent_de_la_table_qui_fait_foi(): void
    {
        $this->seedCancelled('client_unavailable', 'client', 2500);

        Livewire::actingAs($this->admin)
            ->test(CancellationReasonsCenter::class)
            ->assertViewHas('rows', fn ($rows) => $rows->first()['frais_euros'] === 25.0);
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
        $this->seedCancelled(null, 'admin');
        $this->seedCancelled('valid_reason', 'admin');

        Livewire::actingAs($this->admin)
            ->test(CancellationReasonsCenter::class)
            ->assertOk()
            // Trois annulations comptees, un seul motif renseigne dans le tableau.
            ->assertViewHas('totalCancelled', 3)
            ->assertViewHas('rows', fn ($rows) => $rows->count() === 1 && $rows->first()['raison'] === 'valid_reason');
    }

    public function test_zero_cancellations_yields_zero_rate(): void
    {
        Livewire::actingAs($this->admin)
            ->test(CancellationReasonsCenter::class)
            ->assertOk()
            ->assertViewHas('totalCancelled', 0)
            ->assertViewHas('cancellationRate', 0)
            ->assertViewHas('rows', fn ($rows) => $rows->isEmpty())
            ->assertViewHas('byActorRole', fn ($rows) => $rows->isEmpty());
    }
}
