<?php

namespace Tests\Feature\Promotion;

use App\Events\Promotion\PromoCodeRedeemed;
use App\Models\Booking;
use App\Models\PromoCampaign;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\User;
use App\Services\Promotion\PromoCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class PromoCodeApplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_creates_redemption_and_increments_total_uses(): void
    {
        Event::fake([PromoCodeRedeemed::class]);

        $user = User::factory()->client()->create();
        $booking = Booking::create([
            'client_id' => $user->id,
            'date' => now()->addDay(),
            'heure' => '10:00',
            'status' => 'en_attente',
            'devis_estime' => 100,
        ]);

        $promo = PromoCode::create([
            'code' => 'APPLY10',
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 10,
            'status' => PromoCode::STATUS_ACTIVE,
            'total_uses' => 0,
        ]);

        $service = app(PromoCodeService::class);
        $redemption = $service->apply($promo, $user, $booking, 100);

        $this->assertInstanceOf(PromoCodeRedemption::class, $redemption);
        $this->assertSame(PromoCodeRedemption::STATUS_APPLIED, $redemption->status);
        $this->assertEqualsWithDelta(10.0, (float) $redemption->discount_amount, 0.01);

        $this->assertSame(1, $promo->fresh()->total_uses);

        Event::assertDispatched(PromoCodeRedeemed::class);
    }

    public function test_apply_updates_campaign_totals(): void
    {
        $user = User::factory()->client()->create();
        $booking = Booking::create([
            'client_id' => $user->id,
            'date' => now()->addDay(),
            'heure' => '10:00',
            'status' => 'en_attente',
            'devis_estime' => 200,
        ]);

        $campaign = PromoCampaign::create([
            'name' => 'Test Campaign',
            'slug' => 'test',
            'status' => PromoCampaign::STATUS_ACTIVE,
            'total_discounted' => 0,
            'total_redemptions' => 0,
        ]);

        $promo = PromoCode::create([
            'promo_campaign_id' => $campaign->id,
            'code' => 'CAMPAIGN20',
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 20,
            'status' => PromoCode::STATUS_ACTIVE,
        ]);

        app(PromoCodeService::class)->apply($promo, $user, $booking, 200);

        $campaign->refresh();
        $this->assertSame(1, (int) $campaign->total_redemptions);
        $this->assertEqualsWithDelta(40.0, (float) $campaign->total_discounted, 0.01);
    }

    public function test_revert_decrements_usage_counters(): void
    {
        $user = User::factory()->client()->create();
        $booking = Booking::create([
            'client_id' => $user->id,
            'date' => now()->addDay(),
            'heure' => '10:00',
            'status' => 'en_attente',
            'devis_estime' => 100,
        ]);

        $promo = PromoCode::create([
            'code' => 'REVERT',
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 10,
            'status' => PromoCode::STATUS_ACTIVE,
        ]);

        $service = app(PromoCodeService::class);
        $redemption = $service->apply($promo, $user, $booking, 100);

        $this->assertSame(1, $promo->fresh()->total_uses);

        $service->revert($redemption, 'test_revert');

        $this->assertSame(0, $promo->fresh()->total_uses);
        $this->assertSame(PromoCodeRedemption::STATUS_REVERTED, $redemption->fresh()->status);
    }
}
