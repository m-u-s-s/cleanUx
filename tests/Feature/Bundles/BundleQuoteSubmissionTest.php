<?php

namespace Tests\Feature\Bundles;

use App\Models\MultiTradeBundleItemQuote;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\Bundles\MultiTradeBundleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Bundle Marketplace P2 — soumission de devis par le prestataire. */
class BundleQuoteSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedProvider(): User
    {
        $user = User::factory()->employe()->create(['is_active' => true]);
        ProviderProfile::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        return $user->fresh();
    }

    private function pendingQuoteFor(User $provider, int $ttlHours = 48): MultiTradeBundleItemQuote
    {
        $trade = Trade::factory()->create();
        $client = User::factory()->client()->create();
        $bundle = app(MultiTradeBundleService::class)->createDraft(
            client: $client,
            name: 'Chantier',
            items: [
                ['trade_id' => $trade->id, 'label' => 'A', 'estimated_price_cents' => 1000],
                ['trade_id' => $trade->id, 'label' => 'B', 'estimated_price_cents' => 2000],
            ],
        );

        return MultiTradeBundleItemQuote::create([
            'bundle_item_id' => $bundle->items->first()->id,
            'provider_user_id' => $provider->id,
            'status' => MultiTradeBundleItemQuote::STATUS_PENDING,
            'valid_until' => now()->addHours($ttlHours),
        ]);
    }

    public function test_provider_submits_quote_sets_price_status_and_timestamp(): void
    {
        $provider = $this->verifiedProvider();
        $quote = $this->pendingQuoteFor($provider);

        $result = app(MultiTradeBundleService::class)->submitQuote($quote, $provider, 45000, 'Disponible dès lundi');

        $this->assertSame('submitted', $result->status);
        $this->assertSame(45000, $result->price_cents);
        $this->assertSame('Disponible dès lundi', $result->message);
        $this->assertNotNull($result->submitted_at);
    }

    public function test_provider_can_update_price_before_selection(): void
    {
        $provider = $this->verifiedProvider();
        $quote = $this->pendingQuoteFor($provider);

        app(MultiTradeBundleService::class)->submitQuote($quote, $provider, 45000);
        $updated = app(MultiTradeBundleService::class)->submitQuote($quote->fresh(), $provider, 42000);

        $this->assertSame(42000, $updated->price_cents);
        $this->assertSame('submitted', $updated->status);
    }

    public function test_cannot_submit_quote_belonging_to_another_provider(): void
    {
        $provider = $this->verifiedProvider();
        $intruder = $this->verifiedProvider();
        $quote = $this->pendingQuoteFor($provider);

        $this->expectException(ValidationException::class);
        app(MultiTradeBundleService::class)->submitQuote($quote, $intruder, 45000);
    }

    public function test_cannot_submit_expired_quote(): void
    {
        $provider = $this->verifiedProvider();
        $quote = $this->pendingQuoteFor($provider, ttlHours: -1); // déjà expiré

        $this->expectException(ValidationException::class);
        app(MultiTradeBundleService::class)->submitQuote($quote, $provider, 45000);
    }

    public function test_provider_withdraws_quote(): void
    {
        $provider = $this->verifiedProvider();
        $quote = $this->pendingQuoteFor($provider);
        app(MultiTradeBundleService::class)->submitQuote($quote, $provider, 45000);

        $result = app(MultiTradeBundleService::class)->withdrawQuote($quote->fresh(), $provider);

        $this->assertSame('withdrawn', $result->status);
    }
}
