<?php

namespace Tests\Feature\AccountingV2;

use App\Models\AccountingEntry;
use App\Models\Booking;
use App\Services\AccountingV2\Posting\BookingPostingService;
use App\Support\Accounting\BookingAutoPoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Audit MEDIUM — auto-posting comptable, MODÈLE AGENT (décision produit 2026-06-11) : seule la commission est un produit ; la part prestataire est une dette (467) jusqu'au payout ; TVA sur la seule commission. */
class MarketplaceSettlementPostingTest extends TestCase
{
    use RefreshDatabase;

    private function entries(Booking $booking)
    {
        return AccountingEntry::query()
            ->where('source_type', 'Booking.settlement')
            ->where('source_id', $booking->id)
            ->get();
    }

    public function test_agent_settlement_is_balanced_with_provider_liability_and_commission_revenue(): void
    {
        config([
            'accounting_v2.marketplace.commission_revenue_account' => '706',
            'accounting_v2.marketplace.provider_payable_account' => '467',
            'accounting_v2.marketplace.commission_vat_rate' => 21.0,
        ]);

        $booking = Booking::factory()->create([
            'payment_amount_cents' => 10000,  // TTC payé par le client
            'platform_fee_cents' => 2000,     // commission TTC (20%)
        ]);

        app(BookingPostingService::class)->postMarketplaceSettlement($booking);

        $entries = $this->entries($booking);
        $this->assertTrue($entries->isNotEmpty());

        // Partie double : débits == crédits == TTC.
        $this->assertSame((int) $entries->sum('debit_cents'), (int) $entries->sum('credit_cents'));
        $this->assertSame(10000, (int) $entries->sum('debit_cents'));

        // Dette prestataire (467) = part prestataire = TTC - commission.
        $this->assertSame(8000, (int) $entries->firstWhere('account_code', '467')->credit_cents);

        // Produit commission HT = round(2000 / 1.21) = 1653 ; TVA collectée = 347.
        $this->assertSame(1653, (int) $entries->firstWhere('account_code', '706')->credit_cents);
        $this->assertSame(347, (int) $entries->firstWhere('account_code', '4457')->credit_cents);
    }

    public function test_settlement_is_idempotent(): void
    {
        $booking = Booking::factory()->create(['payment_amount_cents' => 10000, 'platform_fee_cents' => 2000]);

        app(BookingPostingService::class)->postMarketplaceSettlement($booking);
        app(BookingPostingService::class)->postMarketplaceSettlement($booking);

        $batches = $this->entries($booking)->pluck('batch_id')->unique();
        $this->assertCount(1, $batches);
    }

    public function test_auto_poster_routes_to_agent_settlement_when_revenue_model_is_agent(): void
    {
        config([
            'accounting_v2.auto_post_enabled' => true,
            'accounting_v2.marketplace.revenue_model' => 'agent',
        ]);

        $booking = Booking::factory()->create([
            'status' => 'completed',
            'payment_amount_cents' => 10000,
            'platform_fee_cents' => 2000,
            'devis_estime' => 100,
        ]);

        BookingAutoPoster::postSale($booking);

        // Écriture de règlement agent présente, et PAS d'écriture de vente principale.
        $this->assertTrue($this->entries($booking)->isNotEmpty());
        $this->assertSame(0, AccountingEntry::where('source_type', 'Booking')->where('source_id', $booking->id)->count());
    }
}
