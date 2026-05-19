<?php

namespace Tests\Feature;

use App\Livewire\Admin\GestionZones;
use App\Models\Trade;
use App\Models\TradeZoneSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\CreatesZoneAwareFixtures;
use Tests\TestCase;

class AdminTradeZoneSettingsTest extends TestCase
{
    use CreatesZoneAwareFixtures;
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::factory()->admin()->create([
            'permissions'  => ['manage-services', 'perform-critical-admin-actions'],
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active'    => true,
        ]);
    }

    protected function makeTrade(string $slug = 'peinture'): Trade
    {
        return Trade::create([
            'slug'       => $slug,
            'code'       => strtoupper($slug),
            'name'       => ucfirst($slug),
            'is_active'  => true,
            'sort_order' => 10,
        ]);
    }

    public function test_admin_can_see_trades_for_a_selected_zone(): void
    {
        $context = $this->createCoverageContext();
        $this->makeTrade('peinture');
        $this->makeTrade('jardinage');

        $this->actingAs($this->createAdmin());

        Livewire::test(GestionZones::class)
            ->call('selectZone', $context['zone']->id)
            ->assertSet('tradeSettings', function (array $settings) {
                return collect($settings)->pluck('trade_slug')->contains('peinture')
                    && collect($settings)->pluck('trade_slug')->contains('jardinage');
            });
    }

    public function test_admin_can_toggle_a_trade_in_a_zone(): void
    {
        $context = $this->createCoverageContext();
        $trade = $this->makeTrade('peinture');

        $this->actingAs($this->createAdmin());

        Livewire::test(GestionZones::class)
            ->call('selectZone', $context['zone']->id)
            ->call('toggleTradeActive', $trade->id);

        $this->assertDatabaseHas('trade_zone_settings', [
            'trade_id'        => $trade->id,
            'service_zone_id' => $context['zone']->id,
            'is_active'       => false,
        ]);
    }

    public function test_admin_can_save_a_price_multiplier_for_a_trade_in_a_zone(): void
    {
        $context = $this->createCoverageContext();
        $trade = $this->makeTrade('toiturerie');

        $this->actingAs($this->createAdmin());

        Livewire::test(GestionZones::class)
            ->call('selectZone', $context['zone']->id)
            ->set("tradeSettings.{$trade->id}.price_multiplier", '1.35')
            ->set("tradeSettings.{$trade->id}.is_active", true)
            ->set("tradeSettings.{$trade->id}.notes", 'Surcharge centre-ville')
            ->call('saveTradeSetting', $trade->id);

        $setting = TradeZoneSetting::where('trade_id', $trade->id)
            ->where('service_zone_id', $context['zone']->id)
            ->first();

        $this->assertNotNull($setting);
        $this->assertTrue($setting->is_active);
        $this->assertSame('1.35', (string) $setting->price_multiplier);
        $this->assertSame('Surcharge centre-ville', $setting->notes);
    }

    public function test_admin_can_save_all_trade_settings_in_one_call(): void
    {
        $context = $this->createCoverageContext();
        $tradeA = $this->makeTrade('peinture');
        $tradeB = $this->makeTrade('serrurerie');

        $this->actingAs($this->createAdmin());

        Livewire::test(GestionZones::class)
            ->call('selectZone', $context['zone']->id)
            ->set("tradeSettings.{$tradeA->id}.price_multiplier", '1.10')
            ->set("tradeSettings.{$tradeB->id}.is_active", false)
            ->call('saveAllTradeSettings');

        $this->assertDatabaseHas('trade_zone_settings', [
            'trade_id'         => $tradeA->id,
            'service_zone_id'  => $context['zone']->id,
            'price_multiplier' => 1.10,
        ]);
        $this->assertDatabaseHas('trade_zone_settings', [
            'trade_id'        => $tradeB->id,
            'service_zone_id' => $context['zone']->id,
            'is_active'       => false,
        ]);
    }

    public function test_multiplier_out_of_range_is_rejected(): void
    {
        $context = $this->createCoverageContext();
        $trade = $this->makeTrade('peinture');

        $this->actingAs($this->createAdmin());

        Livewire::test(GestionZones::class)
            ->call('selectZone', $context['zone']->id)
            ->set("tradeSettings.{$trade->id}.price_multiplier", '99')
            ->call('saveTradeSetting', $trade->id)
            ->assertHasErrors(["tradeSettings.$trade->id.price_multiplier"]);

        $this->assertDatabaseMissing('trade_zone_settings', [
            'trade_id'        => $trade->id,
            'service_zone_id' => $context['zone']->id,
        ]);
    }

    public function test_non_admin_cannot_save_trade_zone_setting(): void
    {
        $context = $this->createCoverageContext();
        $trade = $this->makeTrade('peinture');

        $client = User::factory()->create(['role' => 'client', 'is_active' => true]);
        $this->actingAs($client);

        try {
            Livewire::test(GestionZones::class)
                ->set('selectedZoneId', $context['zone']->id)
                ->call('toggleTradeActive', $trade->id);
        } catch (\Throwable $e) {
            // Gate::authorize peut soit lancer AuthorizationException soit produire
            // une réponse 403 selon le contexte Livewire — on accepte les deux.
            $this->assertTrue(
                $e instanceof \Illuminate\Auth\Access\AuthorizationException
                || str_contains($e->getMessage(), 'unauthorized')
                || str_contains($e->getMessage(), 'forbidden')
                || str_contains($e->getMessage(), 'This action is unauthorized'),
                'Une exception d\'autorisation devrait être levée pour un non-admin.'
            );
        }

        $this->assertDatabaseMissing('trade_zone_settings', [
            'trade_id'        => $trade->id,
            'service_zone_id' => $context['zone']->id,
        ]);
    }
}
