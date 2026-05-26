<?php

namespace Tests\Unit\Services;

use App\Models\Booking;
use App\Services\Payments\CommissionService;
use Tests\TestCase;

class CommissionServiceTest extends TestCase
{
    private CommissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CommissionService();
    }

    public function test_calculates_15_percent_default_commission(): void
    {
        $booking = new Booking();
        $booking->devis_estime = 100.00;

        $result = $this->service->calculateForBooking($booking);

        $this->assertSame(10000, $result['total_cents']);
        $this->assertSame(1500, $result['platform_fee_cents']);
        $this->assertSame(8500, $result['provider_payout_cents']);
        $this->assertSame(0.15, $result['commission_rate']);
        $this->assertSame('eur', $result['currency']);
    }

    public function test_enforces_minimum_200_cents_fee_on_small_booking(): void
    {
        $booking = new Booking();
        $booking->devis_estime = 5.00;

        $result = $this->service->calculateForBooking($booking);

        $this->assertSame(500, $result['total_cents']);
        $this->assertSame(200, $result['platform_fee_cents']);
        $this->assertSame(300, $result['provider_payout_cents']);
    }

    public function test_fee_is_capped_at_booking_total(): void
    {
        $booking = new Booking();
        $booking->devis_estime = 1.00;

        $result = $this->service->calculateForBooking($booking);

        $this->assertSame(100, $result['total_cents']);
        $this->assertSame(100, $result['platform_fee_cents']);
        $this->assertSame(0, $result['provider_payout_cents']);
    }

    public function test_zero_amount_booking_returns_zero_fee(): void
    {
        $booking = new Booking();
        $booking->devis_estime = 0.00;

        $result = $this->service->calculateForBooking($booking);

        $this->assertSame(0, $result['total_cents']);
        $this->assertSame(0, $result['platform_fee_cents']);
        $this->assertSame(0, $result['provider_payout_cents']);
    }
}
