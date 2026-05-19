<?php

namespace Tests\Feature\Loyalty;

use App\Models\Booking;
use App\Models\LoyaltyTransaction;
use App\Models\User;
use Database\Seeders\LoyaltyTierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoyaltyHooksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LoyaltyTierSeeder::class);
    }

    public function test_completing_a_booking_awards_loyalty_points(): void
    {
        $user = User::factory()->client()->create();

        $booking = Booking::create([
            'client_id' => $user->id,
            'date' => now()->subDay(),
            'heure' => '10:00',
            'status' => 'confirme',  // Pas encore complete
            'devis_estime' => 80,
            'booking_reference' => 'CUX-HOOK-001',
        ]);

        // Pas de points encore
        $this->assertSame(0, LoyaltyTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', LoyaltyTransaction::TYPE_EARN_BOOKING)
            ->count());

        // Transition to completed → BookingObserver awards points
        $booking->update(['status' => 'termine']);

        $earned = LoyaltyTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', LoyaltyTransaction::TYPE_EARN_BOOKING)
            ->first();

        $this->assertNotNull($earned);
        $this->assertSame(800, $earned->points); // 80€ × 10
    }

    public function test_booking_completion_is_idempotent_on_repeated_saves(): void
    {
        $user = User::factory()->client()->create();

        $booking = Booking::create([
            'client_id' => $user->id,
            'date' => now()->subDay(),
            'heure' => '10:00',
            'status' => 'confirme',
            'devis_estime' => 50,
            'booking_reference' => 'CUX-IDEM-001',
        ]);

        $booking->update(['status' => 'termine']);

        // Re-trigger via des updates sans re-toucher le status
        $booking->update(['heure' => '11:00']);
        $booking->update(['heure' => '12:00']);

        $count = LoyaltyTransaction::query()
            ->where('user_id', $user->id)
            ->where('type', LoyaltyTransaction::TYPE_EARN_BOOKING)
            ->count();

        $this->assertSame(1, $count, 'Only one award even after repeated updates');
    }
}
