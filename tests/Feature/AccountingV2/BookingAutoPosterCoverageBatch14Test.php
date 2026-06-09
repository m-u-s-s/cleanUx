<?php

namespace Tests\Feature\AccountingV2;

use App\Models\AccountingEntry;
use App\Models\Booking;
use App\Support\Accounting\BookingAutoPoster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class BookingAutoPosterCoverageBatch14Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('accounting_v2.auto_post_enabled', true);
    }

    private function buildBooking(int $id, array $attributes): Booking
    {
        $booking = new Booking;
        $booking->id = $id;
        foreach ($attributes as $key => $value) {
            $booking->{$key} = $value;
        }

        return $booking;
    }

    public function test_disabled_flag_short_circuits_all_postings(): void
    {
        Config::set('accounting_v2.auto_post_enabled', false);
        $booking = $this->buildBooking(1, ['total_amount_cents' => 12100, 'vat_rate' => 21.0]);

        BookingAutoPoster::postSale($booking);
        BookingAutoPoster::postPayment($booking, 300);
        BookingAutoPoster::postRefund($booking, 5000);

        $this->assertSame(0, AccountingEntry::query()->count());
    }

    public function test_post_sale_with_cents_column_and_vat_rate_creates_balanced_entries(): void
    {
        $booking = $this->buildBooking(11, ['total_amount_cents' => 12100, 'vat_rate' => 21.0]);

        BookingAutoPoster::postSale($booking);

        $entries = AccountingEntry::query()->where('source_id', 11)->where('source_type', 'Booking')->get();
        $this->assertCount(3, $entries);
        $this->assertSame(12100, (int) $entries->sum('debit_cents'));
        $this->assertSame(12100, (int) $entries->sum('credit_cents'));
    }

    public function test_post_sale_uses_float_price_and_country_code_when_no_cents(): void
    {
        $booking = $this->buildBooking(12, ['total_amount' => 120.00, 'country_code' => 'FR']);

        BookingAutoPoster::postSale($booking);

        $entries = AccountingEntry::query()->where('source_id', 12)->where('source_type', 'Booking')->get();
        // 120.00 EUR @20% FR -> HT 10000, VAT 2000, TTC 12000
        $this->assertSame(12000, (int) $entries->sum('debit_cents'));
        $this->assertSame(12000, (int) $entries->sum('credit_cents'));
    }

    public function test_post_sale_resolves_vat_from_metadata_country(): void
    {
        $booking = $this->buildBooking(13, [
            'payment_amount_cents' => 5000,
            'metadata' => ['country_code' => 'DE'],
        ]);

        BookingAutoPoster::postSale($booking);

        $this->assertSame(
            5000,
            (int) AccountingEntry::query()->where('source_id', 13)->where('source_type', 'Booking')->sum('debit_cents')
        );
    }

    public function test_post_sale_falls_back_to_default_country_vat(): void
    {
        $booking = $this->buildBooking(14, ['estimated_price' => 100.00]);

        BookingAutoPoster::postSale($booking);

        // Default BE @21% -> HT round(10000/1.21)=8264, VAT 1736, TTC 10000
        $this->assertSame(
            10000,
            (int) AccountingEntry::query()->where('source_id', 14)->where('source_type', 'Booking')->sum('credit_cents')
        );
    }

    public function test_post_sale_with_zero_amount_creates_nothing(): void
    {
        $booking = $this->buildBooking(15, ['total_amount_cents' => 0]);

        BookingAutoPoster::postSale($booking);

        $this->assertSame(0, AccountingEntry::query()->count());
    }

    public function test_post_payment_with_stripe_fee_creates_three_lines(): void
    {
        $booking = $this->buildBooking(21, ['amount_cents' => 10000]);

        BookingAutoPoster::postPayment($booking, 290);

        $entries = AccountingEntry::query()->where('source_id', 21)->where('source_type', 'Booking.payment')->get();
        $this->assertCount(3, $entries);
        $this->assertSame(10000, (int) $entries->sum('debit_cents'));
        $this->assertSame(10000, (int) $entries->sum('credit_cents'));
    }

    public function test_post_payment_with_zero_amount_creates_nothing(): void
    {
        $booking = $this->buildBooking(22, ['total_amount' => 0]);

        BookingAutoPoster::postPayment($booking);

        $this->assertSame(0, AccountingEntry::query()->count());
    }

    public function test_post_refund_creates_balanced_entries(): void
    {
        $booking = $this->buildBooking(31, []);

        BookingAutoPoster::postRefund($booking, 4500);

        $entries = AccountingEntry::query()->where('source_id', 31)->where('source_type', 'Booking.refund')->get();
        $this->assertCount(2, $entries);
        $this->assertSame(4500, (int) $entries->sum('debit_cents'));
        $this->assertSame(4500, (int) $entries->sum('credit_cents'));
    }

    public function test_post_refund_with_non_positive_amount_creates_nothing(): void
    {
        $booking = $this->buildBooking(32, []);

        BookingAutoPoster::postRefund($booking, 0);

        $this->assertSame(0, AccountingEntry::query()->count());
    }
}
