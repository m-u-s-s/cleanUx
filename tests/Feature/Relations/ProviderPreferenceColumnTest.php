<?php

namespace Tests\Feature\Relations;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProviderPreferenceColumnTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_type_preference_is_persisted_and_defaults_to_any(): void
    {
        $this->assertTrue(Schema::hasColumn('bookings', 'provider_type_preference'));

        $booking = Booking::factory()->create(['provider_type_preference' => 'company']);
        $this->assertSame('company', $booking->fresh()->provider_type_preference);
        $this->assertTrue($booking->prefersProviderType('company'));

        $default = Booking::factory()->create();
        $this->assertSame('any', $default->fresh()->provider_type_preference);
    }
}
