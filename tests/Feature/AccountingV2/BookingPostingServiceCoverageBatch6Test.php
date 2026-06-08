<?php

namespace Tests\Feature\AccountingV2;

use App\Models\AccountingEntry;
use App\Models\Booking;
use App\Services\AccountingV2\Posting\BookingPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class BookingPostingServiceCoverageBatch6Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('accounting_v2.allowed_journals', ['VEN', 'ACH', 'BANK', 'OD', 'INV']);
        Config::set('accounting_v2.block_post_on_closed_period', true);
        Config::set('accounting_v2.chart_of_accounts', [
            '411000' => ['name' => 'Clients généraux', 'class' => 4],
            '512100' => ['name' => 'Banque Stripe', 'class' => 5],
            '701100' => ['name' => 'Ventes bookings', 'class' => 7],
            '4457' => ['name' => 'TVA collectée', 'class' => 4],
            '627' => ['name' => 'Frais Stripe', 'class' => 6],
            '658100' => ['name' => 'Remboursements', 'class' => 6],
        ]);
    }

    private function service(): BookingPostingService
    {
        return app(BookingPostingService::class);
    }

    public function test_post_booking_sale_with_vat_creates_three_balanced_lines(): void
    {
        $booking = Booking::factory()->create();

        $batchId = $this->service()->postBookingSale($booking, 12100, 21.0);

        $this->assertNotNull($batchId);
        $entries = AccountingEntry::query()->where('batch_id', $batchId)->get();
        $this->assertCount(3, $entries);

        // 12100 TTC at 21% => HT 10000, VAT 2100
        $client = $entries->firstWhere('account_code', '411000');
        $sales = $entries->firstWhere('account_code', '701100');
        $vat = $entries->firstWhere('account_code', '4457');

        $this->assertSame(12100, (int) $client->debit_cents);
        $this->assertSame(10000, (int) $sales->credit_cents);
        $this->assertSame(2100, (int) $vat->credit_cents);
        $this->assertSame((int) $entries->sum('debit_cents'), (int) $entries->sum('credit_cents'));
        $this->assertSame('client', $client->counterparty_type);
        $this->assertSame('VEN', $sales->journal_code);
    }

    public function test_post_booking_sale_without_vat_omits_vat_line(): void
    {
        $booking = Booking::factory()->create();

        $batchId = $this->service()->postBookingSale($booking, 5000, 0.0);

        $entries = AccountingEntry::query()->where('batch_id', $batchId)->get();
        $this->assertCount(2, $entries);
        $this->assertNull($entries->firstWhere('account_code', '4457'));
        $this->assertSame(5000, (int) $entries->firstWhere('account_code', '701100')->credit_cents);
    }

    public function test_post_booking_sale_clamps_negative_vat_rate_to_zero(): void
    {
        $booking = Booking::factory()->create();

        // Negative rate is clamped via max(0.0, $vatRate) => behaves like 0% VAT.
        $batchId = $this->service()->postBookingSale($booking, 8000, -10.0);

        $entries = AccountingEntry::query()->where('batch_id', $batchId)->get();
        $this->assertCount(2, $entries);
        $this->assertSame(8000, (int) $entries->firstWhere('account_code', '701100')->credit_cents);
    }

    public function test_post_booking_sale_returns_null_for_non_positive_amount(): void
    {
        $booking = Booking::factory()->create();

        $this->assertNull($this->service()->postBookingSale($booking, 0, 21.0));
        $this->assertNull($this->service()->postBookingSale($booking, -100, 21.0));
        $this->assertSame(0, AccountingEntry::query()->count());
    }

    public function test_post_booking_payment_with_stripe_fee_adds_fee_line(): void
    {
        $booking = Booking::factory()->create();

        $batchId = $this->service()->postBookingPayment($booking, 12100, 350);

        $entries = AccountingEntry::query()->where('batch_id', $batchId)->get();
        $this->assertCount(3, $entries);
        $this->assertSame(11750, (int) $entries->firstWhere('account_code', '512100')->debit_cents);
        $this->assertSame(12100, (int) $entries->firstWhere('account_code', '411000')->credit_cents);
        $this->assertSame(350, (int) $entries->firstWhere('account_code', '627')->debit_cents);
        $this->assertSame((int) $entries->sum('debit_cents'), (int) $entries->sum('credit_cents'));
        $this->assertSame('BANK', $entries->first()->journal_code);
    }

    public function test_post_booking_payment_without_fee_omits_fee_line(): void
    {
        $booking = Booking::factory()->create();

        $batchId = $this->service()->postBookingPayment($booking, 9000);

        $entries = AccountingEntry::query()->where('batch_id', $batchId)->get();
        $this->assertCount(2, $entries);
        $this->assertNull($entries->firstWhere('account_code', '627'));
        $this->assertSame(9000, (int) $entries->firstWhere('account_code', '512100')->debit_cents);
    }

    public function test_post_booking_payment_returns_null_for_non_positive_amount(): void
    {
        $booking = Booking::factory()->create();

        $this->assertNull($this->service()->postBookingPayment($booking, 0));
        $this->assertSame(0, AccountingEntry::query()->count());
    }

    public function test_post_refund_creates_balanced_lines(): void
    {
        $booking = Booking::factory()->create();

        $batchId = $this->service()->postRefund($booking, 4200);

        $entries = AccountingEntry::query()->where('batch_id', $batchId)->get();
        $this->assertCount(2, $entries);
        $this->assertSame(4200, (int) $entries->firstWhere('account_code', '658100')->debit_cents);
        $this->assertSame(4200, (int) $entries->firstWhere('account_code', '512100')->credit_cents);
        $this->assertSame('REFUND-BOOK-'.$booking->id, $entries->first()->reference);
    }

    public function test_post_refund_returns_null_for_non_positive_amount(): void
    {
        $booking = Booking::factory()->create();

        $this->assertNull($this->service()->postRefund($booking, 0));
        $this->assertSame(0, AccountingEntry::query()->count());
    }

    public function test_posting_is_idempotent_per_source(): void
    {
        $booking = Booking::factory()->create();

        $a = $this->service()->postBookingSale($booking, 6000, 0.0);
        $b = $this->service()->postBookingSale($booking, 6000, 0.0);

        $this->assertSame($a, $b);
        $this->assertSame(2, AccountingEntry::query()->where('source_type', 'Booking')->count());
    }
}
