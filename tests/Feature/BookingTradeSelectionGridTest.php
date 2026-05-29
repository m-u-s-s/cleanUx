<?php

namespace Tests\Feature;

use App\Livewire\Client\PrendreRendezVous;
use App\Models\ServiceCatalog;
use App\Models\Trade;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

/**
 * Tests for the Phase F4 trade selection grid in the Livewire booking form.
 *
 * Covers:
 * - selectTrade() sets selectedTradeId and clears service + schema state
 * - clearTrade() resets selectedTradeId
 * - getAvailableTradesProperty returns active trades with expected shape
 * - getServicesForSelectedTradeProperty filters services by trade
 * - render() exposes availableTrades, selectedTradeId, servicesForSelectedTrade
 */
class BookingTradeSelectionGridTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    private function client(): User
    {
        return User::factory()->create(['role' => User::ROLE_CLIENT, 'is_active' => true]);
    }

    private function makeTrade(string $slug, string $name, int $sort = 10, bool $active = true): Trade
    {
        return Trade::create([
            'slug' => $slug,
            'code' => strtoupper($slug),
            'name' => $name,
            'icon' => 'briefcase',
            'short_description' => "Description de {$name}",
            'is_active' => $active,
            'sort_order' => $sort,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // getAvailableTradesProperty
    // ─────────────────────────────────────────────────────────────

    public function test_available_trades_only_returns_active_trades(): void
    {
        $this->makeTrade('nettoyage', 'Nettoyage', sort: 1, active: true);
        $this->makeTrade('inactif', 'Inactif', sort: 2, active: false);

        $this->actingAs($this->client());

        $component = Livewire::test(PrendreRendezVous::class);

        $trades = $component->get('availableTrades');

        $names = array_column($trades, 'name');
        $this->assertContains('Nettoyage', $names);
        $this->assertNotContains('Inactif', $names);
    }

    public function test_available_trades_have_expected_keys(): void
    {
        $this->makeTrade('jardinage', 'Jardinage');

        $this->actingAs($this->client());

        $component = Livewire::test(PrendreRendezVous::class);
        $trades = $component->get('availableTrades');

        $this->assertNotEmpty($trades);
        $first = $trades[0];
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('icon', $first);
        $this->assertArrayHasKey('short_description', $first);
        $this->assertArrayHasKey('sort_order', $first);
    }

    public function test_available_trades_default_icon_is_briefcase_when_null(): void
    {
        Trade::create([
            'slug' => 'no-icon', 'code' => 'NOICON', 'name' => 'Sans icone',
            'icon' => null, 'is_active' => true, 'sort_order' => 1,
        ]);

        $this->actingAs($this->client());

        $component = Livewire::test(PrendreRendezVous::class);
        $trades = $component->get('availableTrades');

        $trade = collect($trades)->firstWhere('name', 'Sans icone');
        $this->assertSame('briefcase', $trade['icon']);
    }

    // ─────────────────────────────────────────────────────────────
    // selectTrade() action
    // ─────────────────────────────────────────────────────────────

    public function test_select_trade_sets_selected_trade_id(): void
    {
        $trade = $this->makeTrade('peinture', 'Peinture');

        $this->actingAs($this->client());

        Livewire::test(PrendreRendezVous::class)
            ->call('selectTrade', $trade->id)
            ->assertSet('selectedTradeId', $trade->id);
    }

    public function test_select_trade_clears_service_and_schema(): void
    {
        $context = $this->createCoverageContext();
        $trade = $this->makeTrade('babysit', 'Babysitting');
        $context['service']->update(['trade_id' => $trade->id]);

        $this->actingAs($this->client());

        $code = $context['service']->code ?: $context['service']->slug;

        // First select a service (which loads schema if any)
        $component = Livewire::test(PrendreRendezVous::class)
            ->set('postal_code_input', $context['postalCode']->code)
            ->set('selected_service_identifier', $code);

        // Now select a different trade — should clear service + schema
        $otherTrade = $this->makeTrade('jardinage', 'Jardinage');

        $component
            ->call('selectTrade', $otherTrade->id)
            ->assertSet('selected_service_identifier', null)
            ->assertSet('tradeFormSchema', null)
            ->assertSet('tradeFormAnswers', []);
    }

    public function test_select_trade_does_nothing_when_same_trade_already_selected(): void
    {
        $trade = $this->makeTrade('levage', 'Levage');

        $this->actingAs($this->client());

        // Set service identifier first
        Livewire::test(PrendreRendezVous::class)
            ->call('selectTrade', $trade->id)
            ->set('selected_service_identifier', 'some-service')
            ->call('selectTrade', $trade->id)
            // Same trade → should NOT clear the service
            ->assertSet('selected_service_identifier', 'some-service');
    }

    // ─────────────────────────────────────────────────────────────
    // clearTrade() action
    // ─────────────────────────────────────────────────────────────

    public function test_clear_trade_resets_selected_trade_and_service(): void
    {
        $trade = $this->makeTrade('toiture', 'Toiture');

        $this->actingAs($this->client());

        Livewire::test(PrendreRendezVous::class)
            ->call('selectTrade', $trade->id)
            ->assertSet('selectedTradeId', $trade->id)
            ->call('clearTrade')
            ->assertSet('selectedTradeId', null)
            ->assertSet('selected_service_identifier', null)
            ->assertSet('tradeFormSchema', null)
            ->assertSet('tradeFormAnswers', []);
    }

    // ─────────────────────────────────────────────────────────────
    // getServicesForSelectedTradeProperty
    // ─────────────────────────────────────────────────────────────

    public function test_services_for_selected_trade_filters_by_trade(): void
    {
        $context = $this->createCoverageContext();

        $cleaning = $this->makeTrade('nettoyage2', 'Nettoyage', sort: 1);
        $painting = $this->makeTrade('peinture2', 'Peinture', sort: 2);

        // Attach service to cleaning trade
        $context['service']->update(['trade_id' => $cleaning->id, 'name' => 'Nettoyage standard']);

        // Create a painting service (without zone rule — it won't appear in grouped by zone, but will appear without zone filter)
        ServiceCatalog::factory()->create([
            'trade_id' => $painting->id,
            'name' => 'Peinture intérieure',
            'code' => 'PAINT_INT2',
            'slug' => 'peinture-int-2',
            'is_active' => true,
        ]);

        $this->actingAs($this->client());

        // Select the cleaning trade
        $component = Livewire::test(PrendreRendezVous::class)
            ->call('selectTrade', $cleaning->id);

        $filtered = $component->get('servicesForSelectedTrade');

        // Only Nettoyage key should be present
        $this->assertArrayHasKey('Nettoyage', $filtered);
        $this->assertArrayNotHasKey('Peinture', $filtered);
    }

    public function test_services_for_selected_trade_returns_all_when_no_trade_selected(): void
    {
        $context = $this->createCoverageContext();

        $cleaning = $this->makeTrade('nettoyage3', 'Nettoyage 3', sort: 1);
        $context['service']->update(['trade_id' => $cleaning->id]);

        $this->actingAs($this->client());

        $component = Livewire::test(PrendreRendezVous::class);

        $all = $component->get('servicesForSelectedTrade');
        $grouped = $component->get('servicesGroupedByTrade');

        // When no trade selected, both should be the same
        $this->assertSame($grouped, $all);
    }

    // ─────────────────────────────────────────────────────────────
    // render() — view data
    // ─────────────────────────────────────────────────────────────

    public function test_render_passes_available_trades_to_view(): void
    {
        $this->makeTrade('rendu-trade', 'Rendu Trade');

        $this->actingAs($this->client());

        Livewire::test(PrendreRendezVous::class)
            ->assertViewHas('availableTrades')
            ->assertViewHas('selectedTradeId')
            ->assertViewHas('servicesForSelectedTrade');
    }
}
