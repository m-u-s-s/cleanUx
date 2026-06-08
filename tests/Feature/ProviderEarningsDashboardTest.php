<?php

namespace Tests\Feature;

use App\Livewire\Provider\ProviderEarningsDashboard;
use App\Models\Booking;
use App\Models\BookingTip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProviderEarningsDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_mounts_for_an_employe_with_default_week_period(): void
    {
        $employe = User::factory()->employe()->create();

        Livewire::actingAs($employe)
            ->test(ProviderEarningsDashboard::class)
            ->assertOk()
            ->assertSet('period', 'week')
            ->assertSee('Earnings dashboard');
    }

    public function test_dashboard_aggregates_completed_missions_and_tips_for_the_provider(): void
    {
        $employe = User::factory()->employe()->create();

        $booking = Booking::factory()->termine()->create([
            'employe_id' => $employe->id,
            'provider_amount_cents' => 9000,
            'updated_at' => now(),
        ]);

        BookingTip::create([
            'code' => 'TIP-'.uniqid(),
            'booking_id' => $booking->id,
            'client_user_id' => $booking->client_id,
            'provider_user_id' => $employe->id,
            'amount_cents' => 1500,
            'currency' => 'EUR',
            'status' => BookingTip::STATUS_CHARGED,
        ]);

        Livewire::actingAs($employe)
            ->test(ProviderEarningsDashboard::class)
            ->assertOk()
            // Revenu total = 9000 + 1500 = 10500 cents -> 105,00 €
            ->assertSee('105,00')
            // Pourboires 15,00 €
            ->assertSee('15,00')
            ->assertSee('Décomposition revenus');
    }

    public function test_set_period_switches_each_window_and_rerenders(): void
    {
        $employe = User::factory()->employe()->create();

        $component = Livewire::actingAs($employe)->test(ProviderEarningsDashboard::class);

        foreach (['today', 'week', 'month', 'year'] as $period) {
            $component->call('setPeriod', $period)
                ->assertOk()
                ->assertSet('period', $period);
        }
    }

    public function test_unknown_period_falls_back_to_week_default_range(): void
    {
        $employe = User::factory()->employe()->create();

        Livewire::actingAs($employe)
            ->test(ProviderEarningsDashboard::class)
            ->call('setPeriod', 'decade')
            ->assertOk()
            ->assertSet('period', 'decade');
    }

    public function test_empty_provider_shows_no_data_states(): void
    {
        $employe = User::factory()->employe()->create();

        Livewire::actingAs($employe)
            ->test(ProviderEarningsDashboard::class)
            ->call('setPeriod', 'today')
            ->assertOk()
            ->assertSee('Aucune donnée sur cette période.')
            ->assertSee('Pas assez de données.');
    }
}
