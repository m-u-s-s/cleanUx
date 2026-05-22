<?php

namespace Tests\Feature\Client;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class MissionLiveTrackingV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_renders_v2_mount_when_feature_active(): void
    {
        $user = User::factory()->create(['role' => 'client', 'is_active' => true]);
        Feature::for($user)->activate('client-mobile-v2');

        $booking = Booking::factory()->create(['client_id' => $user->id]);
        $mission = Mission::factory()->create([
            'rendez_vous_id' => $booking->id,
            'status' => 'en_route',
        ]);

        $response = $this->actingAs($user)->get(route('client.missions.tracking', $mission->id));

        $response->assertOk();
        $response->assertSee('id="mission-live-island"', false);
    }

    public function test_renders_legacy_when_feature_inactive(): void
    {
        $user = User::factory()->create(['role' => 'client', 'is_active' => true]);

        $booking = Booking::factory()->create(['client_id' => $user->id]);
        $mission = Mission::factory()->create([
            'rendez_vous_id' => $booking->id,
            'status' => 'en_route',
        ]);

        $response = $this->actingAs($user)->get(route('client.missions.tracking', $mission->id));
        $response->assertOk();
        $response->assertDontSee('id="mission-live-island"', false);
    }
}
