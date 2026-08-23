<?php

namespace Tests\Feature\Bundles;

use App\Models\MultiTradeBundle;
use App\Models\MultiTradeBundleItem;
use App\Models\MultiTradeBundleItemQuote;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\Bundles\MultiTradeBundleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bundle Marketplace P4 — expiration des demandes dépassées + ré-escalade pour compléter les items sous-quotés. */
class BundleQuoteExpiryTest extends TestCase
{
    use RefreshDatabase;

    private function eligibleProvider(Trade $trade): User
    {
        $user = User::factory()->employe()->create(['is_active' => true]);
        ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);
        $user->trades()->attach($trade->id, ['proficiency' => 'expert', 'is_primary' => true]);

        return $user->fresh();
    }

    private function quotingBundle(Trade $trade): MultiTradeBundle
    {
        $client = User::factory()->client()->create();
        $bundle = app(MultiTradeBundleService::class)->createDraft($client, 'Chantier', [
            ['trade_id' => $trade->id, 'label' => 'A', 'estimated_price_cents' => 1000],
            ['trade_id' => $trade->id, 'label' => 'B', 'estimated_price_cents' => 2000],
        ]);
        $bundle->update(['status' => MultiTradeBundle::STATUS_QUOTING]);

        return $bundle->fresh('items');
    }

    private function quote(MultiTradeBundleItem $item, User $provider, string $status, $validUntil): MultiTradeBundleItemQuote
    {
        return MultiTradeBundleItemQuote::create([
            'bundle_item_id' => $item->id,
            'provider_user_id' => $provider->id,
            'status' => $status,
            'price_cents' => $status === 'submitted' ? 5000 : null,
            'valid_until' => $validUntil,
            'submitted_at' => $status === 'submitted' ? now() : null,
        ]);
    }

    public function test_expire_stale_quotes_marks_past_due_open_quotes_only(): void
    {
        $trade = Trade::factory()->create();
        $bundle = $this->quotingBundle($trade);
        $item = $bundle->items->first();

        $stalePending = $this->quote($item, $this->eligibleProvider($trade), 'pending', now()->subHour());
        $staleSubmitted = $this->quote($item, $this->eligibleProvider($trade), 'submitted', now()->subHour());
        $fresh = $this->quote($item, $this->eligibleProvider($trade), 'pending', now()->addHours(10));
        $selected = $this->quote($bundle->items->last(), $this->eligibleProvider($trade), 'selected', now()->subDay());

        $expired = app(MultiTradeBundleService::class)->expireStaleQuotes();

        $this->assertSame(2, $expired);
        $this->assertSame('expired', $stalePending->fresh()->status);
        $this->assertSame('expired', $staleSubmitted->fresh()->status);
        $this->assertSame('pending', $fresh->fresh()->status);
        $this->assertSame('selected', $selected->fresh()->status);
    }

    public function test_scan_command_expires_stale_and_reescalates(): void
    {
        config(['bundles.quote_request_top_n' => 3]);

        $trade = Trade::factory()->create();
        collect(range(1, 4))->each(fn () => $this->eligibleProvider($trade));

        $bundle = $this->quotingBundle($trade);
        $stale = $this->quote($bundle->items->first(), $this->eligibleProvider($trade), 'pending', now()->subHour());

        $this->artisan('bundles:scan-quote-requests')->assertExitCode(0);

        $this->assertSame('expired', $stale->fresh()->status);
        // Après expiration + ré-escalade, des prestataires frais ont été (re)sollicités.
        $this->assertGreaterThan(0, MultiTradeBundleItemQuote::where('status', 'pending')->count());
    }

    public function test_escalate_tops_up_under_quoted_items_in_quoting_bundles(): void
    {
        config(['bundles.quote_request_top_n' => 3]);

        $trade = Trade::factory()->create();
        // 5 prestataires éligibles disponibles pour être (re)sollicités.
        $providers = collect(range(1, 5))->map(fn () => $this->eligibleProvider($trade));

        $bundle = $this->quotingBundle($trade);
        $itemA = $bundle->items->first();

        // itemA a déjà 1 devis actif → il en manque 2 pour atteindre le top-N (3).
        $this->quote($itemA, $providers->first(), 'pending', now()->addHours(10));

        app(MultiTradeBundleService::class)->escalateQuotingBundles();

        // Chaque item non sélectionné doit atteindre le top-N de devis actifs.
        foreach ($bundle->fresh()->items as $item) {
            $this->assertSame(3, $item->quotes()->active()->count(), "Item {$item->label} doit avoir 3 devis actifs.");
        }
    }
}
