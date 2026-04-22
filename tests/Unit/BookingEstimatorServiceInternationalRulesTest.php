<?php

namespace Tests\Unit;

use App\Services\Booking\BookingEstimatorService;
use Tests\TestCase;

class BookingEstimatorServiceInternationalRulesTest extends TestCase
{
    public function test_it_applies_country_price_multiplier_when_estimating(): void
    {
        $service = app(BookingEstimatorService::class);

        $price = $service->estimatePrice(null, null, null, [
            'service_identifier' => 'nettoyage_standard',
            'surface' => 'moins_50',
            'country_price_multiplier' => 1.20,
        ]);

        $this->assertSame(94.8, $price);
    }
}
