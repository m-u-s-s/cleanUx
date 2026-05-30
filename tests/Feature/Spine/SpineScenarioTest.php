<?php

namespace Tests\Feature\Spine;

use App\Models\Booking;
use App\Models\Mission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Spine\SpineScenario;
use Tests\TestCase;

class SpineScenarioTest extends TestCase
{
    use RefreshDatabase;

    public function test_builds_a_connect_ready_provider_and_linked_booking_mission(): void
    {
        $s = SpineScenario::make()->withDevis(100.00)->build();

        $this->assertTrue(
            $s->provider->canReceiveStripeConnectPayments(),
            'scenario provider must be Stripe-Connect ready'
        );
        $this->assertNotNull($s->provider->stripe_connect_account_id);
        $this->assertNotNull($s->client->stripe_id);

        $this->assertInstanceOf(Booking::class, $s->booking);
        $this->assertSame($s->client->id, $s->booking->client_id);
        $this->assertSame($s->provider->id, $s->booking->employe_id);

        $this->assertInstanceOf(Mission::class, $s->mission);
        $this->assertSame($s->booking->id, $s->mission->booking->id);
        $this->assertSame($s->provider->id, $s->mission->lead_provider_user_id);
    }
}
