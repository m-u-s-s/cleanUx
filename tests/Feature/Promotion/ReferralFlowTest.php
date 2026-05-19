<?php

namespace Tests\Feature\Promotion;

use App\Events\Promotion\ReferralQualified;
use App\Events\Promotion\ReferralRegistered;
use App\Events\Promotion\ReferralRewardGranted;
use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\User;
use App\Services\Promotion\ReferralService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ReferralFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_ensure_referral_code_generates_unique_code(): void
    {
        $a = User::factory()->client()->create(['name' => 'Alice Martin']);
        $b = User::factory()->client()->create(['name' => 'Alice Martin']);

        $service = app(ReferralService::class);
        $codeA = $service->ensureReferralCode($a);
        $codeB = $service->ensureReferralCode($b);

        $this->assertNotEmpty($codeA);
        $this->assertNotEmpty($codeB);
        $this->assertNotSame($codeA, $codeB);

        // idempotent
        $this->assertSame($codeA, $service->ensureReferralCode($a->fresh()));
    }

    public function test_register_referral_creates_pending_referral(): void
    {
        Event::fake([ReferralRegistered::class]);

        $referrer = User::factory()->client()->create();
        $referee = User::factory()->client()->create();

        $service = app(ReferralService::class);
        $code = $service->ensureReferralCode($referrer);

        $referral = $service->registerReferral($code, $referee);

        $this->assertNotNull($referral);
        $this->assertSame(Referral::STATUS_SIGNED_UP, $referral->status);
        $this->assertSame($referrer->id, $referral->referrer_user_id);
        $this->assertSame($referee->id, $referral->referee_user_id);

        Event::assertDispatched(ReferralRegistered::class);
    }

    public function test_self_referral_is_rejected(): void
    {
        $user = User::factory()->client()->create();
        $service = app(ReferralService::class);
        $code = $service->ensureReferralCode($user);

        $this->assertNull($service->registerReferral($code, $user));
    }

    public function test_qualifying_booking_grants_rewards_to_both(): void
    {
        Event::fake([ReferralQualified::class, ReferralRewardGranted::class]);

        $referrer = User::factory()->client()->create();
        $referee = User::factory()->client()->create();

        $service = app(ReferralService::class);
        $code = $service->ensureReferralCode($referrer);
        $referral = $service->registerReferral($code, $referee);

        $booking = Booking::create([
            'client_id' => $referee->id,
            'date' => now()->subDay(),
            'heure' => '10:00',
            'status' => 'termine',
            'devis_estime' => 100,
        ]);

        $service->markQualifiedByBooking($booking->fresh());

        $referral->refresh();
        $this->assertSame(Referral::STATUS_REWARDED, $referral->status);
        $this->assertNotNull($referral->qualified_at);
        $this->assertNotNull($referral->rewarded_at);

        $this->assertDatabaseHas('referral_rewards', [
            'referral_id' => $referral->id,
            'role' => ReferralReward::ROLE_REFERRER,
            'status' => ReferralReward::STATUS_GRANTED,
        ]);
        $this->assertDatabaseHas('referral_rewards', [
            'referral_id' => $referral->id,
            'role' => ReferralReward::ROLE_REFEREE,
            'status' => ReferralReward::STATUS_GRANTED,
        ]);

        // Promo codes nominatifs créés pour chaque bénéficiaire
        $this->assertSame(2, PromoCode::query()
            ->where('source', PromoCode::SOURCE_REFERRAL)
            ->count());

        Event::assertDispatched(ReferralQualified::class);
        Event::assertDispatched(ReferralRewardGranted::class, 2);
    }

    public function test_non_completed_booking_does_not_qualify(): void
    {
        $referrer = User::factory()->client()->create();
        $referee = User::factory()->client()->create();

        $service = app(ReferralService::class);
        $code = $service->ensureReferralCode($referrer);
        $service->registerReferral($code, $referee);

        $booking = Booking::create([
            'client_id' => $referee->id,
            'date' => now()->subDay(),
            'heure' => '10:00',
            'status' => 'confirme',
            'devis_estime' => 100,
        ]);

        $result = $service->markQualifiedByBooking($booking->fresh());
        $this->assertNull($result);

        $this->assertSame(0, ReferralReward::query()->count());
    }

    public function test_stats_returns_aggregated_counts(): void
    {
        $referrer = User::factory()->client()->create();
        $service = app(ReferralService::class);
        $code = $service->ensureReferralCode($referrer);

        // 3 filleuls : 2 inscrits, 1 qualifié+rewarded
        for ($i = 0; $i < 3; $i++) {
            $referee = User::factory()->client()->create();
            $service->registerReferral($code, $referee);
        }

        // qualifier le 3ème
        $lastReferral = Referral::query()->latest()->first();
        $booking = Booking::create([
            'client_id' => $lastReferral->referee_user_id,
            'date' => now()->subDay(),
            'heure' => '10:00',
            'status' => 'termine',
            'devis_estime' => 100,
        ]);
        $service->markQualifiedByBooking($booking->fresh());

        $stats = $service->statsForUser($referrer->fresh());

        $this->assertSame(3, $stats['total_invited']);
        $this->assertSame(3, $stats['total_signed_up']);
        $this->assertSame(1, $stats['total_qualified']);
        $this->assertSame(1, $stats['total_rewarded']);
        $this->assertGreaterThan(0, $stats['total_earned']);
    }
}
