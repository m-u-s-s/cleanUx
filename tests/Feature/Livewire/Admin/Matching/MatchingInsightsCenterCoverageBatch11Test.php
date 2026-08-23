<?php

namespace Tests\Feature\Livewire\Admin\Matching;

use App\Livewire\Admin\Matching\MatchingInsightsCenter;
use App\Models\Booking;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\Trade;
use App\Models\User;
use Database\Factories\BookingMatchingDecisionFactory;
use Database\Factories\ProviderPerformanceMetricFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MatchingInsightsCenterCoverageBatch11Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected ServiceZone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->zone = ServiceZone::factory()->create();
    }

    /** Le métier commun aux fixtures — obligatoire depuis que le filtre n'a plus de repli : une réservation sans métier résolvable ne rend AUCUN candidat, au lieu de les rendre tous. */
    private function trade(): Trade
    {
        return Trade::firstOrCreate(
            ['slug' => 'matching-insights-trade'],
            ['code' => 'MIT', 'name' => 'Nettoyage', 'is_active' => true, 'sort_order' => 1],
        );
    }

    private function makeBooking(): Booking
    {
        $client = User::factory()->client()->create();

        return Booking::create([
            'client_id' => $client->id,
            'service_zone_id' => $this->zone->id,
            'trade_id' => $this->trade()->id,
            'date' => now()->addDay(),
            'heure' => '10:00',
            'status' => 'en_attente',
            'devis_estime' => 100,
            'booking_mode' => 'scheduled',
        ]);
    }

    private function makeProvider(): User
    {
        $user = User::factory()->create([
            'role' => 'employe',
            'primary_service_zone_id' => $this->zone->id,
            'is_active' => true,
        ]);

        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'verified',
            'rating_avg' => 5.0,
            'rating_count' => 30,
            'is_online' => true,
        ]);

        $user->serviceZones()->attach($this->zone->id, [
            'assignment_type' => 'primary',
            'is_active' => true,
        ]);

        $user->trades()->syncWithoutDetaching([$this->trade()->id]);

        return $user;
    }

    public function test_default_tab_renders_recent_decisions_with_kpis(): void
    {
        $booking = $this->makeBooking();
        $provider = $this->makeProvider();

        BookingMatchingDecisionFactory::new()->create([
            'booking_id' => $booking->id,
            'selected_user_id' => $provider->id,
            'top_score' => 90.00,
            'candidates_count' => 5,
        ]);

        Livewire::actingAs($this->admin)
            ->test(MatchingInsightsCenter::class)
            ->assertOk()
            ->assertSet('tab', 'recent_decisions')
            ->assertViewHas('currentView', 'decisions')
            ->assertViewHas('kpis', fn ($kpis) => $kpis['decisions_total'] === 1)
            ->assertViewHas('weights')
            ->assertViewHas('config')
            ->assertViewHas('items');
    }

    public function test_provider_metrics_tab_renders(): void
    {
        $provider = $this->makeProvider();

        ProviderPerformanceMetricFactory::new()->create([
            'user_id' => $provider->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(MatchingInsightsCenter::class)
            ->set('tab', 'provider_metrics')
            ->assertOk()
            ->assertViewHas('currentView', 'metrics')
            ->assertViewHas('items', fn ($items) => $items->total() === 1);
    }

    public function test_simulator_tab_renders_empty_items(): void
    {
        Livewire::actingAs($this->admin)
            ->test(MatchingInsightsCenter::class)
            ->set('tab', 'simulator')
            ->assertOk()
            ->assertViewHas('currentView', 'simulator')
            ->assertViewHas('items', fn ($items) => $items->isEmpty());
    }

    public function test_simulate_requires_booking_id(): void
    {
        Livewire::actingAs($this->admin)
            ->test(MatchingInsightsCenter::class)
            ->set('tab', 'simulator')
            ->set('simulateBookingId', null)
            ->call('simulate')
            ->assertHasErrors('simulateBookingId')
            ->assertSet('simulationResult', null);
    }

    public function test_simulate_with_unknown_booking_id_errors(): void
    {
        Livewire::actingAs($this->admin)
            ->test(MatchingInsightsCenter::class)
            ->set('tab', 'simulator')
            ->set('simulateBookingId', 999999)
            ->call('simulate')
            ->assertHasErrors('simulateBookingId')
            ->assertSet('simulationResult', null);
    }

    public function test_simulate_success_populates_result(): void
    {
        $booking = $this->makeBooking();
        $this->makeProvider();

        $component = Livewire::actingAs($this->admin)
            ->test(MatchingInsightsCenter::class)
            ->set('tab', 'simulator')
            ->set('simulateBookingId', $booking->id)
            ->call('simulate')
            ->assertHasNoErrors('simulateBookingId');

        $result = $component->get('simulationResult');
        $this->assertIsArray($result);
        $this->assertSame($booking->id, $result['booking_id']);
        $this->assertArrayHasKey('candidates', $result);
        $this->assertNotEmpty($result['candidates']);
        $this->assertArrayHasKey('score', $result['candidates'][0]);
        $this->assertArrayHasKey('components', $result['candidates'][0]);
    }
}
