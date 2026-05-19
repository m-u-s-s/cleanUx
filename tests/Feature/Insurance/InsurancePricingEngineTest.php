<?php

namespace Tests\Feature\Insurance;

use App\Models\Booking;
use App\Models\InsurancePlan;
use App\Models\User;
use App\Services\Insurance\InsurancePricingEngine;
use Database\Seeders\InsurancePlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InsurancePricingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InsurancePlansSeeder::class);
    }

    public function test_compute_premium_basic_formula(): void
    {
        $plan = InsurancePlan::query()->where('code', 'basic')->first();

        // Booking of 100 EUR (10000 cents):
        // premium = 200 + 1% * 10000 = 200 + 100 = 300
        $premium = app(InsurancePricingEngine::class)->computePremium($plan, 10000);

        $this->assertSame(300, $premium);
    }

    public function test_compute_premium_clamps_to_min(): void
    {
        $plan = InsurancePlan::query()->where('code', 'basic')->first();

        // Test with min_premium_cents > base+percent (bump max too so clamp doesn't override)
        $plan->forceFill(['min_premium_cents' => 5000, 'max_premium_cents' => 10000])->save();
        $plan->refresh();

        $premium = app(InsurancePricingEngine::class)->computePremium($plan, 1000);

        $this->assertSame(5000, $premium);
    }

    public function test_compute_premium_clamps_to_max(): void
    {
        $plan = InsurancePlan::query()->where('code', 'premium')->first();

        // Booking of 10000 EUR (1_000_000 cents):
        // premium = 1500 + 3.5% * 1000000 = 1500 + 35000 = 36500
        // max = 12000 → clamped
        $premium = app(InsurancePricingEngine::class)->computePremium($plan, 1_000_000);

        $this->assertSame(12000, $premium);
    }

    public function test_get_available_plans_for_booking_returns_all_active(): void
    {
        $user = User::factory()->client()->create();
        $booking = Booking::create([
            'client_id' => $user->id,
            'date' => now()->addDay(),
            'heure' => '10:00',
            'status' => 'en_attente',
            'devis_estime' => 200,
        ]);

        $plans = app(InsurancePricingEngine::class)->getAvailablePlansForBooking($booking->id);

        $this->assertCount(3, $plans);
        $codes = collect($plans)->pluck('plan.code')->all();
        $this->assertContains('basic', $codes);
        $this->assertContains('standard', $codes);
        $this->assertContains('premium', $codes);
    }

    public function test_get_available_plans_includes_premium_amount(): void
    {
        $user = User::factory()->client()->create();
        $booking = Booking::create([
            'client_id' => $user->id,
            'date' => now()->addDay(),
            'heure' => '10:00',
            'status' => 'en_attente',
            'devis_estime' => 100,
        ]);

        $plans = app(InsurancePricingEngine::class)->getAvailablePlansForBooking($booking->id);

        foreach ($plans as $item) {
            $this->assertIsInt($item['premium_cents']);
            $this->assertGreaterThan(0, $item['premium_cents']);
        }
    }

    public function test_inactive_plan_excluded(): void
    {
        $user = User::factory()->client()->create();
        $booking = Booking::create([
            'client_id' => $user->id,
            'date' => now()->addDay(),
            'heure' => '10:00',
            'status' => 'en_attente',
            'devis_estime' => 100,
        ]);

        InsurancePlan::query()->where('code', 'basic')->update(['is_active' => false]);

        $plans = app(InsurancePricingEngine::class)->getAvailablePlansForBooking($booking->id);

        $codes = collect($plans)->pluck('plan.code')->all();
        $this->assertNotContains('basic', $codes);
        $this->assertContains('standard', $codes);
    }

    public function test_trade_restricted_plan_excludes_unrelated_bookings(): void
    {
        $user = User::factory()->client()->create();
        $booking = Booking::create([
            'client_id' => $user->id,
            'date' => now()->addDay(),
            'heure' => '10:00',
            'status' => 'en_attente',
            'devis_estime' => 100,
        ]);

        InsurancePlan::query()->where('code', 'basic')->update([
            'trade_codes' => ['plomberie_pro_only'],
        ]);

        $plans = app(InsurancePricingEngine::class)->getAvailablePlansForBooking($booking->id);

        $codes = collect($plans)->pluck('plan.code')->all();
        $this->assertNotContains('basic', $codes);
    }
}
