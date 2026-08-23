<?php

namespace Tests\Feature\Livewire\Provider;

use App\Livewire\Provider\BundleQuoteRequests;
use App\Models\MultiTradeBundleItemQuote;
use App\Models\ProviderProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\Bundles\MultiTradeBundleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Bundle Marketplace P2 — page prestataire des demandes de devis (chantiers groupés). */
class BundleQuoteRequestsTest extends TestCase
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

    private function pendingQuoteFor(User $provider): MultiTradeBundleItemQuote
    {
        $trade = Trade::factory()->create();
        $client = User::factory()->client()->create();
        $bundle = app(MultiTradeBundleService::class)->createDraft(
            client: $client,
            name: 'Chantier',
            items: [
                ['trade_id' => $trade->id, 'label' => 'Plomberie', 'estimated_price_cents' => 1000],
                ['trade_id' => $trade->id, 'label' => 'Carrelage', 'estimated_price_cents' => 2000],
            ],
        );

        return MultiTradeBundleItemQuote::create([
            'bundle_item_id' => $bundle->items->first()->id,
            'provider_user_id' => $provider->id,
            'status' => MultiTradeBundleItemQuote::STATUS_PENDING,
            'valid_until' => now()->addHours(48),
        ]);
    }

    public function test_lists_only_own_active_quote_requests(): void
    {
        $provider = $this->verifiedProvider();
        $mine = $this->pendingQuoteFor($provider);
        $foreign = $this->pendingQuoteFor($this->verifiedProvider());

        $this->actingAs($provider);

        Livewire::test(BundleQuoteRequests::class)
            ->assertOk()
            ->assertViewHas('requests', function ($requests) use ($mine, $foreign) {
                $ids = collect($requests->items())->pluck('id')->all();

                return in_array($mine->id, $ids, true) && ! in_array($foreign->id, $ids, true);
            });
    }

    public function test_submit_records_price_and_marks_submitted(): void
    {
        $provider = $this->verifiedProvider();
        $quote = $this->pendingQuoteFor($provider);
        $this->actingAs($provider);

        Livewire::test(BundleQuoteRequests::class)
            ->call('select', $quote->id)
            ->set('price', '450')
            ->set('message', 'Disponible lundi')
            ->call('submit');

        $fresh = $quote->fresh();
        $this->assertSame('submitted', $fresh->status);
        $this->assertSame(45000, $fresh->price_cents);
        $this->assertSame('Disponible lundi', $fresh->message);
    }

    public function test_cannot_submit_for_a_foreign_quote(): void
    {
        $provider = $this->verifiedProvider();
        $foreign = $this->pendingQuoteFor($this->verifiedProvider());
        $this->actingAs($provider);

        Livewire::test(BundleQuoteRequests::class)
            ->call('select', $foreign->id)
            ->assertSet('selectedId', null);
    }
}
