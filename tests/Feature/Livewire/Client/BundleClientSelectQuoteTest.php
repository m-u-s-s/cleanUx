<?php

namespace Tests\Feature\Livewire\Client;

use App\Livewire\Client\MultiTradesBundleManager;
use App\Models\MultiTradeBundle;
use App\Models\MultiTradeBundleItem;
use App\Models\MultiTradeBundleItemQuote;
use App\Models\Trade;
use App\Models\User;
use App\Services\Bundles\MultiTradeBundleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Bundle Marketplace P3 — le client compare et choisit un devis depuis son espace "Chantiers groupés". */
class BundleClientSelectQuoteTest extends TestCase
{
    use RefreshDatabase;

    private function bundleFor(User $client): MultiTradeBundle
    {
        $trade = Trade::factory()->create();

        return app(MultiTradeBundleService::class)->createDraft(
            client: $client,
            name: 'Chantier',
            items: [
                ['trade_id' => $trade->id, 'label' => 'A', 'estimated_price_cents' => 1000],
                ['trade_id' => $trade->id, 'label' => 'B', 'estimated_price_cents' => 2000],
            ],
        );
    }

    private function submittedQuote(MultiTradeBundleItem $item, int $price = 45000): MultiTradeBundleItemQuote
    {
        return MultiTradeBundleItemQuote::create([
            'bundle_item_id' => $item->id,
            'provider_user_id' => User::factory()->employe()->create()->id,
            'status' => MultiTradeBundleItemQuote::STATUS_SUBMITTED,
            'price_cents' => $price,
            'valid_until' => now()->addHours(48),
            'submitted_at' => now(),
        ]);
    }

    public function test_client_selects_a_quote_for_their_bundle_item(): void
    {
        $client = User::factory()->client()->create();
        $bundle = $this->bundleFor($client);
        $item = $bundle->items->first();
        $quote = $this->submittedQuote($item, 45000);

        $this->actingAs($client);

        Livewire::test(MultiTradesBundleManager::class)
            ->call('selectQuote', $quote->id);

        $this->assertSame('selected', $quote->fresh()->status);
        $item->refresh();
        $this->assertSame('quoted', $item->status);
        $this->assertSame($quote->provider_user_id, $item->assigned_provider_user_id);
        $this->assertSame(45000, $item->quoted_price_cents);
    }

    public function test_client_cannot_select_quote_of_another_clients_bundle(): void
    {
        $owner = User::factory()->client()->create();
        $bundle = $this->bundleFor($owner);
        $quote = $this->submittedQuote($bundle->items->first());

        $intruder = User::factory()->client()->create();
        $this->actingAs($intruder);

        Livewire::test(MultiTradesBundleManager::class)
            ->call('selectQuote', $quote->id);

        // Le devis d'un autre client ne doit pas être modifiable.
        $this->assertSame('submitted', $quote->fresh()->status);
    }
}
