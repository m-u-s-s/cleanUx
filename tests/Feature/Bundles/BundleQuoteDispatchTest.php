<?php

namespace Tests\Feature\Bundles;

use App\Models\MultiTradeBundle;
use App\Models\MultiTradeBundleItemQuote;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use App\Notifications\Bundles\BundleQuoteRequestedNotification;
use App\Services\Bundles\MultiTradeBundleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Bundle Marketplace P1 — diffusion des demandes de devis.
 * Décisions produit 2026-06-11 : concurrence (plusieurs devis/item) + top-N (3-5)
 * sollicités + KYC strict. startQuoting() doit créer des quote-requests `pending`
 * pour les prestataires éligibles (bon métier + actif + KYC validé), plafonné au
 * top-N, valides 48h, et les notifier.
 */
class BundleQuoteDispatchTest extends TestCase
{
    use RefreshDatabase;

    private function provider(bool $kycCleared, ?Trade $trade, string $proficiency = 'expert'): User
    {
        $user = User::factory()->employe()->create(['is_active' => true]);
        ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'verification_status' => $kycCleared ? 'verified' : 'pending',
        ]);
        if ($trade) {
            $user->trades()->attach($trade->id, ['proficiency' => $proficiency, 'is_primary' => true]);
        }

        return $user->fresh();
    }

    private function draftBundle(User $client, Trade $trade): MultiTradeBundle
    {
        return app(MultiTradeBundleService::class)->createDraft(
            client: $client,
            name: 'Rénovation salle de bain',
            items: [
                ['trade_id' => $trade->id, 'label' => 'Plomberie', 'estimated_price_cents' => 50000],
                ['trade_id' => $trade->id, 'label' => 'Carrelage', 'estimated_price_cents' => 80000],
            ],
        );
    }

    public function test_start_quoting_solicits_only_eligible_kyc_providers_with_trade(): void
    {
        Notification::fake();
        config(['bundles.quote_request_top_n' => 5]);

        $trade = Trade::factory()->create();
        $client = User::factory()->client()->create();

        $eligible1 = $this->provider(kycCleared: true, trade: $trade);
        $eligible2 = $this->provider(kycCleared: true, trade: $trade);
        $notKyc = $this->provider(kycCleared: false, trade: $trade);   // exclu : KYC non validé
        $noTrade = $this->provider(kycCleared: true, trade: null);     // exclu : pas le métier

        $bundle = $this->draftBundle($client, $trade);

        app(MultiTradeBundleService::class)->startQuoting($bundle);

        // 2 items × 2 prestataires éligibles = 4 demandes de devis pending.
        $this->assertSame(4, MultiTradeBundleItemQuote::count());

        $solicited = MultiTradeBundleItemQuote::pluck('provider_user_id')->unique()->values()->all();
        sort($solicited);
        $expected = [$eligible1->id, $eligible2->id];
        sort($expected);
        $this->assertSame($expected, $solicited, 'Seuls les prestataires KYC-validés du bon métier sont sollicités.');

        $this->assertSame(0, MultiTradeBundleItemQuote::where('provider_user_id', $notKyc->id)->count());
        $this->assertSame(0, MultiTradeBundleItemQuote::where('provider_user_id', $noTrade->id)->count());

        $quote = MultiTradeBundleItemQuote::first();
        $this->assertSame('pending', $quote->status);
        $this->assertNotNull($quote->valid_until);
        $this->assertTrue($quote->valid_until->between(now()->addHours(47), now()->addHours(49)));

        $this->assertSame('quoting', $bundle->fresh()->status);
    }

    public function test_start_quoting_caps_solicitations_at_top_n(): void
    {
        Notification::fake();
        config(['bundles.quote_request_top_n' => 3]);

        $trade = Trade::factory()->create();
        $client = User::factory()->client()->create();

        foreach (range(1, 6) as $i) {
            $this->provider(kycCleared: true, trade: $trade);
        }

        $bundle = $this->draftBundle($client, $trade);

        app(MultiTradeBundleService::class)->startQuoting($bundle);

        // Plafonné à 3 prestataires par item × 2 items = 6 demandes.
        $this->assertSame(6, MultiTradeBundleItemQuote::count());
        foreach ($bundle->fresh()->items as $item) {
            $this->assertSame(3, $item->quotes()->count());
        }
    }

    public function test_start_quoting_notifies_solicited_providers(): void
    {
        Notification::fake();
        config(['bundles.quote_request_top_n' => 5]);

        $trade = Trade::factory()->create();
        $client = User::factory()->client()->create();
        $eligible = $this->provider(kycCleared: true, trade: $trade);

        $bundle = $this->draftBundle($client, $trade);
        app(MultiTradeBundleService::class)->startQuoting($bundle);

        Notification::assertSentTo($eligible, BundleQuoteRequestedNotification::class);
    }
}
