<?php

namespace Tests\Feature\Loyalty;

use App\Events\Loyalty\LoyaltyPointsAwarded;
use App\Events\Loyalty\LoyaltyTierUpgraded;
use App\Models\Booking;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTier;
use App\Models\LoyaltyTransaction;
use App\Models\User;
use App\Services\Loyalty\LoyaltyService;
use Database\Seeders\LoyaltyTierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class LoyaltyServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LoyaltyTierSeeder::class);
    }

    public function test_account_for_creates_with_bronze_tier(): void
    {
        $user = User::factory()->client()->create();

        $account = app(LoyaltyService::class)->accountFor($user);

        $this->assertInstanceOf(LoyaltyAccount::class, $account);
        $this->assertSame('bronze', $account->currentTier->slug);
        $this->assertSame(0, (int) $account->lifetime_points);
    }

    public function test_account_for_is_idempotent(): void
    {
        $user = User::factory()->client()->create();

        $service = app(LoyaltyService::class);
        $a = $service->accountFor($user);
        $b = $service->accountFor($user->fresh());

        $this->assertSame($a->id, $b->id);
    }

    public function test_award_increments_lifetime_and_period_points(): void
    {
        Event::fake([LoyaltyPointsAwarded::class]);

        $user = User::factory()->client()->create();

        $tx = app(LoyaltyService::class)->award(
            $user,
            LoyaltyTransaction::TYPE_EARN_SIGNUP,
            100,
            null,
            'signup:test_1',
        );

        $this->assertNotNull($tx);
        $this->assertSame(100, $tx->points);
        $this->assertSame('credit', $tx->direction);

        $account = LoyaltyAccount::query()->where('user_id', $user->id)->first();
        $this->assertSame(100, (int) $account->lifetime_points);
        $this->assertSame(100, (int) $account->period_points);

        Event::assertDispatched(LoyaltyPointsAwarded::class);
    }

    public function test_award_idempotency_blocks_duplicate(): void
    {
        $user = User::factory()->client()->create();

        $a = app(LoyaltyService::class)->award($user, LoyaltyTransaction::TYPE_EARN_SIGNUP, 100, null, 'dup_key');
        $b = app(LoyaltyService::class)->award($user, LoyaltyTransaction::TYPE_EARN_SIGNUP, 100, null, 'dup_key');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, LoyaltyTransaction::count());

        $account = LoyaltyAccount::where('user_id', $user->id)->first();
        $this->assertSame(100, (int) $account->lifetime_points);
    }

    public function test_award_triggers_tier_upgrade(): void
    {
        Event::fake([LoyaltyTierUpgraded::class]);

        $user = User::factory()->client()->create();
        $service = app(LoyaltyService::class);

        // Bronze → Silver à 1000 points
        $service->award($user, LoyaltyTransaction::TYPE_EARN_PROMO, 1100, null, 'promo_test');

        $account = LoyaltyAccount::where('user_id', $user->id)->first();
        $this->assertSame('silver', $account->currentTier->slug);

        Event::assertDispatched(LoyaltyTierUpgraded::class);
    }

    public function test_award_with_tier_multiplier_applies(): void
    {
        $user = User::factory()->client()->create();
        $service = app(LoyaltyService::class);

        // Pousser au tier Silver (x1.2)
        $service->award($user, LoyaltyTransaction::TYPE_EARN_PROMO, 1100, null, 'pre1');

        $account = LoyaltyAccount::where('user_id', $user->id)->first();
        $this->assertSame('silver', $account->currentTier->slug);

        // 100 base points en silver x1.2 = 120
        $tx = $service->award($user, LoyaltyTransaction::TYPE_EARN_BOOKING, 100, null, 'after_tier');
        $this->assertSame(120, $tx->points);
    }

    public function test_admin_adjust_credits_or_debits(): void
    {
        $user = User::factory()->client()->create();
        $admin = User::factory()->admin()->create();

        // Crédit
        $tx = app(LoyaltyService::class)->adminAdjust($user, 500, $admin, 'Compensation goodwill');
        $this->assertSame('credit', $tx->direction);
        $this->assertSame(500, (int) LoyaltyAccount::where('user_id', $user->id)->first()->lifetime_points);

        // Débit
        $tx2 = app(LoyaltyService::class)->adminAdjust($user, -200, $admin, 'Fraude détectée');
        $this->assertSame('debit', $tx2->direction);
        $this->assertSame(300, (int) LoyaltyAccount::where('user_id', $user->id)->first()->lifetime_points);
    }

    public function test_award_booking_points_calculates_from_amount(): void
    {
        $user = User::factory()->client()->create();

        $booking = Booking::create([
            'client_id' => $user->id,
            'date' => now()->subDay(),
            'heure' => '10:00',
            'status' => 'termine',
            'devis_estime' => 50,
            'booking_reference' => 'CUX-TEST-001',
        ]);

        $tx = app(LoyaltyService::class)->awardBookingPoints($user, $booking);

        // 50€ * 10 pts/€ = 500 pts en bronze (x1.0)
        $this->assertNotNull($tx);
        $this->assertSame(500, $tx->points);
    }
}
