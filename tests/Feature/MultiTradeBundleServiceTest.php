<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\MultiTradeBundle;
use App\Models\MultiTradeBundleItem;
use App\Models\Trade;
use App\Models\User;
use App\Services\Bundles\MultiTradeBundleService;
use Database\Factories\MultiTradeBundleFactory;
use Database\Factories\MultiTradeBundleItemFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MultiTradeBundleServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): MultiTradeBundleService
    {
        return $this->app->make(MultiTradeBundleService::class);
    }

    private function bundle(array $attrs = []): MultiTradeBundle
    {
        return MultiTradeBundleFactory::new()->create(array_merge([
            'total_quoted_cents' => 0,
            'total_final_cents' => 0,
        ], $attrs));
    }

    private function item(array $attrs = []): MultiTradeBundleItem
    {
        return MultiTradeBundleItemFactory::new()->create(array_merge([
            'quoted_price_cents' => 0,
        ], $attrs));
    }

    /** @return array{0: User, 1: Trade, 2: Trade} */
    private function context(): array
    {
        return [
            User::factory()->client()->create(),
            Trade::factory()->create(),
            Trade::factory()->create(),
        ];
    }

    public function test_create_draft_persists_bundle_with_items_and_total(): void
    {
        [$client, $tradeA, $tradeB] = $this->context();

        $bundle = $this->service()->createDraft(
            $client,
            'Rénovation salle de bain',
            [
                ['trade_id' => $tradeA->id, 'label' => 'Plomberie', 'estimated_price_cents' => 30000, 'duration_minutes' => 240],
                ['trade_id' => $tradeB->id, 'label' => 'Carrelage', 'estimated_price_cents' => 50000],
            ],
            'Rénovation complète',
            ['street' => 'Rue Neuve 1', 'city' => 'Bruxelles'],
        );

        $this->assertSame(MultiTradeBundle::STATUS_DRAFT, $bundle->status);
        $this->assertSame('Rénovation salle de bain', $bundle->name);
        $this->assertSame($client->id, $bundle->client_user_id);
        $this->assertStringStartsWith('bndl_', $bundle->code);
        $this->assertSame(80000, $bundle->total_estimated_cents);
        $this->assertCount(2, $bundle->items);
        $this->assertSame('Bruxelles', $bundle->address['city']);

        $first = $bundle->items->firstWhere('sequence_order', 0);
        $this->assertSame('Plomberie', $first->label);
        $this->assertSame(MultiTradeBundleItem::STATUS_DRAFT, $first->status);
    }

    public function test_create_draft_requires_at_least_two_items(): void
    {
        [$client, $tradeA] = $this->context();

        $this->expectException(ValidationException::class);

        $this->service()->createDraft($client, 'Mono', [
            ['trade_id' => $tradeA->id, 'label' => 'Seul'],
        ]);
    }

    public function test_create_draft_rejects_item_missing_trade_or_label(): void
    {
        [$client, $tradeA] = $this->context();

        $this->expectException(ValidationException::class);

        $this->service()->createDraft($client, 'Bad', [
            ['trade_id' => $tradeA->id, 'label' => 'Ok', 'estimated_price_cents' => 1000],
            ['trade_id' => $tradeA->id, 'label' => ''],
        ]);
    }

    public function test_start_quoting_transitions_draft_to_quoting(): void
    {
        $bundle = $this->bundle(['status' => MultiTradeBundle::STATUS_DRAFT]);

        $updated = $this->service()->startQuoting($bundle);

        $this->assertSame(MultiTradeBundle::STATUS_QUOTING, $updated->status);
    }

    public function test_start_quoting_rejects_non_draft_bundle(): void
    {
        $bundle = $this->bundle(['status' => MultiTradeBundle::STATUS_QUOTING]);

        $this->expectException(ValidationException::class);

        $this->service()->startQuoting($bundle);
    }

    public function test_quote_item_assigns_provider_and_recomputes_total(): void
    {
        $bundle = $this->bundle([
            'status' => MultiTradeBundle::STATUS_QUOTING,
            'bundle_discount_percent' => 0,
            'bundle_discount_cents' => 0,
        ]);
        $itemA = $this->item([
            'bundle_id' => $bundle->id,
            'status' => MultiTradeBundleItem::STATUS_DRAFT,
            'quoted_price_cents' => 0,
        ]);
        $this->item([
            'bundle_id' => $bundle->id,
            'status' => MultiTradeBundleItem::STATUS_QUOTED,
            'quoted_price_cents' => 20000,
        ]);
        $provider = User::factory()->employe()->create();

        $quoted = $this->service()->quoteItem($itemA, $provider, 30000);

        $this->assertSame(MultiTradeBundleItem::STATUS_QUOTED, $quoted->status);
        $this->assertSame($provider->id, $quoted->assigned_provider_user_id);
        $this->assertSame(30000, $quoted->quoted_price_cents);

        $bundle->refresh();
        $this->assertSame(50000, $bundle->total_quoted_cents);
        $this->assertSame(MultiTradeBundle::STATUS_QUOTED, $bundle->status);
        $this->assertSame(0, (int) $bundle->bundle_discount_cents);
    }

    public function test_quote_item_applies_percentage_discount(): void
    {
        $bundle = $this->bundle([
            'status' => MultiTradeBundle::STATUS_QUOTING,
            'bundle_discount_percent' => 10,
            'bundle_discount_cents' => 0,
        ]);
        $item = $this->item([
            'bundle_id' => $bundle->id,
            'status' => MultiTradeBundleItem::STATUS_DRAFT,
            'quoted_price_cents' => 0,
        ]);
        $provider = User::factory()->employe()->create();

        $this->service()->quoteItem($item, $provider, 100000);

        $bundle->refresh();
        $this->assertSame(100000, $bundle->total_quoted_cents);
        // 10% of 100000 = 10000
        $this->assertSame(10000, (int) $bundle->bundle_discount_cents);
    }

    public function test_quote_item_rejects_non_quotable_state(): void
    {
        $bundle = $this->bundle();
        $item = $this->item([
            'bundle_id' => $bundle->id,
            'status' => MultiTradeBundleItem::STATUS_ACCEPTED,
        ]);
        $provider = User::factory()->employe()->create();

        $this->expectException(ValidationException::class);

        $this->service()->quoteItem($item, $provider, 1000);
    }

    public function test_accept_creates_bookings_for_quoted_items(): void
    {
        $client = User::factory()->client()->create();
        $bundle = $this->bundle([
            'status' => MultiTradeBundle::STATUS_QUOTED,
            'client_user_id' => $client->id,
        ]);
        $provider = User::factory()->employe()->create();
        $item = $this->item([
            'bundle_id' => $bundle->id,
            'status' => MultiTradeBundleItem::STATUS_QUOTED,
            'assigned_provider_user_id' => $provider->id,
            'quoted_price_cents' => 45000,
            'duration_minutes' => 180,
        ]);

        $accepted = $this->service()->accept($bundle);

        $this->assertSame(MultiTradeBundle::STATUS_ACCEPTED, $accepted->status);
        $this->assertNotNull($accepted->accepted_at);

        $item->refresh();
        $this->assertSame(MultiTradeBundleItem::STATUS_ACCEPTED, $item->status);
        $this->assertNotNull($item->booking_id);

        $booking = Booking::find($item->booking_id);
        $this->assertNotNull($booking);
        $this->assertSame($client->id, $booking->client_id);
        $this->assertSame($provider->id, $booking->employe_id);
        $this->assertSame('450.00', (string) $booking->devis_estime);
        $this->assertSame('multi_trade_bundle', $booking->matching_snapshot['source']);
    }

    public function test_accept_rejects_when_items_not_all_quoted(): void
    {
        $bundle = $this->bundle(['status' => MultiTradeBundle::STATUS_QUOTING]);
        $this->item([
            'bundle_id' => $bundle->id,
            'status' => MultiTradeBundleItem::STATUS_DRAFT,
        ]);

        $this->expectException(ValidationException::class);

        $this->service()->accept($bundle);
    }

    public function test_cancel_marks_bundle_items_and_booking_cancelled(): void
    {
        $bundle = $this->bundle([
            'status' => MultiTradeBundle::STATUS_ACCEPTED,
            'metadata' => ['foo' => 'bar'],
        ]);
        $booking = Booking::factory()->create(['status' => 'en_attente']);
        $item = $this->item([
            'bundle_id' => $bundle->id,
            'status' => MultiTradeBundleItem::STATUS_ACCEPTED,
            'booking_id' => $booking->id,
        ]);

        $cancelled = $this->service()->cancel($bundle, 'client_changed_mind');

        $this->assertSame(MultiTradeBundle::STATUS_CANCELLED, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertSame('client_changed_mind', $cancelled->metadata['cancellation_reason']);
        $this->assertSame('bar', $cancelled->metadata['foo']);

        $item->refresh();
        $this->assertSame(MultiTradeBundleItem::STATUS_CANCELLED, $item->status);

        $booking->refresh();
        $this->assertSame('annule', $booking->status);
        $this->assertSame('bundle_cancelled', $booking->cancellation_reason);
    }
}
