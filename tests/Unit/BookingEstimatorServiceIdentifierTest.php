<?php

namespace Tests\Unit;

use App\Services\Booking\BookingEstimatorService;
use Tests\TestCase;

class BookingEstimatorServiceIdentifierTest extends TestCase
{
    public function test_service_identifier_context_is_used_when_catalog_is_missing(): void
    {
        $service = new BookingEstimatorService();

        $duration = $service->estimateDuration(null, [
            'service_identifier' => 'nettoyage_profond',
            'surface' => 'moins_50',
        ]);

        $price = $service->estimatePrice(null, null, null, [
            'service_identifier' => 'nettoyage_profond',
            'surface' => 'moins_50',
        ]);

        $this->assertSame(180, $duration);
        $this->assertSame(129.0, $price);
    }
}
