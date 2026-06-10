<?php

namespace Tests\Feature\Bundles;

use App\Models\MultiTradeBundle;
use App\Models\MultiTradeBundleItem;
use App\Models\MultiTradeBundleItemQuote;
use App\Models\Trade;
use App\Models\User;
use App\Services\Bundles\MultiTradeBundleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Bundle Marketplace P3 — le client compare les devis reçus et choisit.
 * selectQuote() retient un devis soumis, rejette les concurrents, fige le
 * prestataire/prix sur l'item, et applique la remise groupage quand tout est quoté.
 */
class BundleQuoteSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function draftBundle(int $items = 2): MultiTradeBundle
    {
        $trade = Trade::factory()->create();
        $client = User::factory()->client()->create();
        $payload = [];
        foreach (range(1, $items) as $i) {
            $payload[] = ['trade_id' => $trade->id, 'label' => "Item $i", 'estimated_price_cents' => 1000 * $i];
        }

        return app(MultiTradeBundleService::class)->createDraft($client, 'Chantier', $payload);
    }

    private function submittedQuote(MultiTradeBundleItem $item, int $priceCents): MultiTradeBundleItemQuote
    {
        $provider = User::factory()->employe()->create();

        return MultiTradeBundleItemQuote::create([
            'bundle_item_id' => $item->id,
            'provider_user_id' => $provider->id,
            'status' => MultiTradeBundleItemQuote::STATUS_SUBMITTED,
            'price_cents' => $priceCents,
            'valid_until' => now()->addHours(48),
            'submitted_at' => now(),
        ]);
    }

    public function test_select_quote_marks_selected_rejects_others_and_quotes_item(): void
    {
        $bundle = $this->draftBundle();
        $item = $bundle->items->first();
        $chosen = $this->submittedQuote($item, 45000);
        $loser = $this->submittedQuote($item, 60000);

        app(MultiTradeBundleService::class)->selectQuote($chosen);

        $this->assertSame('selected', $chosen->fresh()->status);
        $this->assertSame('rejected', $loser->fresh()->status);

        $item->refresh();
        $this->assertSame('quoted', $item->status);
        $this->assertSame($chosen->provider_user_id, $item->assigned_provider_user_id);
        $this->assertSame(45000, $item->quoted_price_cents);
    }

    public function test_cannot_select_a_non_submitted_quote(): void
    {
        $bundle = $this->draftBundle();
        $item = $bundle->items->first();
        $pending = MultiTradeBundleItemQuote::create([
            'bundle_item_id' => $item->id,
            'provider_user_id' => User::factory()->employe()->create()->id,
            'status' => MultiTradeBundleItemQuote::STATUS_PENDING,
            'valid_until' => now()->addHours(48),
        ]);

        $this->expectException(ValidationException::class);
        app(MultiTradeBundleService::class)->selectQuote($pending);
    }

    public function test_group_discount_applied_once_all_items_quoted(): void
    {
        config(['bundles.group_discount_percent' => 10]);
        $bundle = $this->draftBundle(items: 2);

        $items = $bundle->items;
        $q1 = $this->submittedQuote($items[0], 100000);
        $q2 = $this->submittedQuote($items[1], 100000);

        // Après le 1er choix : pas encore tout quoté → pas de remise.
        app(MultiTradeBundleService::class)->selectQuote($q1);
        $this->assertSame(0.0, (float) $bundle->fresh()->bundle_discount_percent);

        // Après le 2e choix : tout est quoté → remise groupage 10%.
        app(MultiTradeBundleService::class)->selectQuote($q2);
        $bundle->refresh();
        $this->assertSame(10.0, (float) $bundle->bundle_discount_percent);
        $this->assertSame(200000, (int) $bundle->total_quoted_cents);
        $this->assertSame(20000, (int) $bundle->bundle_discount_cents);
    }
}
