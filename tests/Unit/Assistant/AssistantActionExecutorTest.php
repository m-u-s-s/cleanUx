<?php

namespace Tests\Unit\Assistant;

use App\Models\AvailabilitySlot;
use App\Models\Booking;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTier;
use App\Models\Mission;
use App\Models\ProviderPayout;
use App\Models\User;
use App\Services\Assistant\Actions\AssistantActionExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * @covers \App\Services\Assistant\Actions\AssistantActionExecutor
 */
class AssistantActionExecutorTest extends TestCase
{
    use RefreshDatabase;

    private AssistantActionExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->executor = new AssistantActionExecutor();
    }

    public function test_list_bookings_returns_no_bookings_message_when_empty(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->executor->execute('list_bookings', $user);

        $this->assertStringContainsString('aucune réservation', mb_strtolower($result));
    }

    public function test_list_bookings_returns_formatted_list(): void
    {
        $user = User::factory()->client()->create();

        Booking::factory()->create([
            'customer_user_id'  => $user->id,
            'booking_reference' => 'BK-001',
            'status'            => 'pending',
            'scheduled_date'    => '2026-06-01',
            'scheduled_time'    => '09:00:00',
        ]);

        $result = $this->executor->execute('list_bookings', $user);

        $this->assertStringContainsString('BK-001', $result);
        $this->assertStringContainsString('pending', $result);
    }

    public function test_booking_detail_returns_not_found_when_wrong_user(): void
    {
        $owner = User::factory()->client()->create();
        $other = User::factory()->client()->create();

        $booking = Booking::factory()->create(['customer_user_id' => $owner->id]);

        $result = $this->executor->execute('booking_detail', $other, ['booking_id' => $booking->id]);

        $this->assertStringContainsString('introuvable', mb_strtolower($result));
    }

    public function test_booking_detail_prompts_for_id_when_none_given(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->executor->execute('booking_detail', $user);

        $this->assertStringContainsString('préciser', mb_strtolower($result));
    }

    public function test_loyalty_balance_returns_no_account_message_when_none(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->executor->execute('loyalty_balance', $user);

        $this->assertStringContainsString('pas encore', mb_strtolower($result));
    }

    public function test_loyalty_balance_returns_tier_and_points(): void
    {
        $user = User::factory()->client()->create();
        $tier = LoyaltyTier::factory()->create(['name' => 'Gold', 'slug' => 'gold']);

        LoyaltyAccount::factory()->create([
            'user_id'          => $user->id,
            'current_tier_id'  => $tier->id,
            'redeemable_points' => 250,
            'period_points'    => 300,
            'lifetime_points'  => 1500,
        ]);

        $result = $this->executor->execute('loyalty_balance', $user);

        $this->assertStringContainsString('Gold', $result);
        $this->assertStringContainsString('250', $result);
    }

    public function test_earnings_summary_returns_formatted_amounts(): void
    {
        $user = User::factory()->employe()->create();

        ProviderPayout::factory()->create([
            'provider_user_id' => $user->id,
            'status'           => ProviderPayout::STATUS_PAID,
            'amount'           => 150.00,
            'period_end'       => now()->toDateString(),
        ]);

        $result = $this->executor->execute('earnings_summary', $user);

        $this->assertStringContainsString('150', $result);
        $this->assertStringContainsString('semaine', mb_strtolower($result));
    }

    public function test_availability_slots_returns_no_slots_message_when_empty(): void
    {
        $user = User::factory()->employe()->create();

        $result = $this->executor->execute('availability_slots', $user);

        $this->assertStringContainsString('aucun', mb_strtolower($result));
    }

    public function test_availability_slots_returns_formatted_days(): void
    {
        $user = User::factory()->employe()->create();

        AvailabilitySlot::create([
            'provider_user_id' => $user->id,
            'weekday'          => 1, // Monday
            'start_time'       => '08:00:00',
            'end_time'         => '16:00:00',
            'is_active'        => true,
        ]);

        $result = $this->executor->execute('availability_slots', $user);

        $this->assertStringContainsString('Lundi', $result);
        $this->assertStringContainsString('08:00', $result);
    }

    public function test_platform_kpis_returns_structured_block(): void
    {
        $user = User::factory()->admin()->create();

        $result = $this->executor->execute('platform_kpis', $user);

        $this->assertStringContainsString('KPIs', $result);
        $this->assertStringContainsString('aujourd', mb_strtolower($result));
    }

    public function test_unknown_action_returns_error_message(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->executor->execute('nonexistent_action', $user);

        $this->assertStringContainsString('Action inconnue', $result);
        $this->assertStringContainsString('nonexistent_action', $result);
    }
}
