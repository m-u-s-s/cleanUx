<?php

namespace Tests\Feature\Promotion;

use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\User;
use App\Services\Promotion\PromoCodeService;
use App\Services\Promotion\PromoCodeValidationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoCodeValidationTest extends TestCase
{
    use RefreshDatabase;

    protected PromoCodeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PromoCodeService::class);
    }

    public function test_invalid_code_returns_not_found(): void
    {
        $user = User::factory()->client()->create();

        $result = $this->service->validate('NOPE', new PromoCodeValidationContext(
            user: $user,
            bookingAmount: 100,
        ));

        $this->assertFalse($result->valid);
        $this->assertSame(PromoCodeService::ERROR_NOT_FOUND, $result->errorCode);
    }

    public function test_active_percent_code_returns_correct_discount(): void
    {
        $user = User::factory()->client()->create();

        PromoCode::create([
            'code' => 'SUMMER10',
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 10,
            'status' => PromoCode::STATUS_ACTIVE,
            'max_uses_per_user' => 1,
        ]);

        $result = $this->service->validate('summer10', new PromoCodeValidationContext(
            user: $user,
            bookingAmount: 200,
        ));

        $this->assertTrue($result->valid);
        $this->assertEqualsWithDelta(20.0, $result->discountAmount, 0.01);
        $this->assertEqualsWithDelta(180.0, $result->finalAmount, 0.01);
    }

    public function test_fixed_amount_code_caps_at_booking_amount(): void
    {
        $user = User::factory()->client()->create();

        PromoCode::create([
            'code' => 'FIX50',
            'discount_type' => PromoCode::TYPE_FIXED,
            'discount_value' => 50,
            'status' => PromoCode::STATUS_ACTIVE,
        ]);

        $result = $this->service->validate('FIX50', new PromoCodeValidationContext(
            user: $user,
            bookingAmount: 30,
        ));

        $this->assertTrue($result->valid);
        $this->assertEqualsWithDelta(30.0, $result->discountAmount, 0.01);
        $this->assertEqualsWithDelta(0.0, $result->finalAmount, 0.01);
    }

    public function test_max_discount_amount_caps_percent_discount(): void
    {
        $user = User::factory()->client()->create();

        PromoCode::create([
            'code' => 'BIG20',
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 20,
            'max_discount_amount' => 15,
            'status' => PromoCode::STATUS_ACTIVE,
        ]);

        $result = $this->service->validate('BIG20', new PromoCodeValidationContext(
            user: $user,
            bookingAmount: 200,
        ));

        $this->assertTrue($result->valid);
        $this->assertEqualsWithDelta(15.0, $result->discountAmount, 0.01);
    }

    public function test_expired_code_is_rejected(): void
    {
        $user = User::factory()->client()->create();

        PromoCode::create([
            'code' => 'OLD',
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 10,
            'status' => PromoCode::STATUS_ACTIVE,
            'valid_until' => now()->subDay(),
        ]);

        $result = $this->service->validate('OLD', new PromoCodeValidationContext(
            user: $user,
            bookingAmount: 100,
        ));

        $this->assertFalse($result->valid);
        $this->assertSame(PromoCodeService::ERROR_OUTSIDE_WINDOW, $result->errorCode);
    }

    public function test_min_booking_amount_enforced(): void
    {
        $user = User::factory()->client()->create();

        PromoCode::create([
            'code' => 'MIN100',
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 10,
            'status' => PromoCode::STATUS_ACTIVE,
            'min_booking_amount' => 100,
        ]);

        $result = $this->service->validate('MIN100', new PromoCodeValidationContext(
            user: $user,
            bookingAmount: 50,
        ));

        $this->assertFalse($result->valid);
        $this->assertSame(PromoCodeService::ERROR_MIN_AMOUNT, $result->errorCode);
    }

    public function test_per_user_usage_limit_enforced(): void
    {
        $user = User::factory()->client()->create();

        $promo = PromoCode::create([
            'code' => 'ONCE',
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 10,
            'status' => PromoCode::STATUS_ACTIVE,
            'max_uses_per_user' => 1,
        ]);

        PromoCodeRedemption::create([
            'promo_code_id' => $promo->id,
            'user_id' => $user->id,
            'status' => PromoCodeRedemption::STATUS_APPLIED,
            'discount_amount' => 10,
            'redeemed_at' => now(),
        ]);

        $result = $this->service->validate('ONCE', new PromoCodeValidationContext(
            user: $user,
            bookingAmount: 100,
        ));

        $this->assertFalse($result->valid);
        $this->assertSame(PromoCodeService::ERROR_USER_LIMIT, $result->errorCode);
    }

    public function test_global_usage_limit_enforced(): void
    {
        $user = User::factory()->client()->create();

        PromoCode::create([
            'code' => 'TEN',
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 10,
            'status' => PromoCode::STATUS_ACTIVE,
            'max_total_uses' => 5,
            'total_uses' => 5,
        ]);

        $result = $this->service->validate('TEN', new PromoCodeValidationContext(
            user: $user,
            bookingAmount: 100,
        ));

        $this->assertFalse($result->valid);
        $this->assertSame(PromoCodeService::ERROR_GLOBAL_LIMIT, $result->errorCode);
    }

    public function test_first_booking_only_blocks_returning_customer(): void
    {
        $user = User::factory()->client()->create();

        PromoCode::create([
            'code' => 'NEWBIE',
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 50,
            'status' => PromoCode::STATUS_ACTIVE,
            'first_booking_only' => true,
        ]);

        $result = $this->service->validate('NEWBIE', new PromoCodeValidationContext(
            user: $user,
            bookingAmount: 100,
            isFirstBooking: false,
        ));

        $this->assertFalse($result->valid);
        $this->assertSame(PromoCodeService::ERROR_FIRST_BOOKING_ONLY, $result->errorCode);
    }

    public function test_first_booking_only_allows_new_customer(): void
    {
        $user = User::factory()->client()->create();

        PromoCode::create([
            'code' => 'NEWBIE',
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 50,
            'status' => PromoCode::STATUS_ACTIVE,
            'first_booking_only' => true,
        ]);

        $result = $this->service->validate('NEWBIE', new PromoCodeValidationContext(
            user: $user,
            bookingAmount: 100,
            isFirstBooking: true,
        ));

        $this->assertTrue($result->valid);
    }

    public function test_paused_code_is_rejected(): void
    {
        $user = User::factory()->client()->create();

        PromoCode::create([
            'code' => 'PAUSED',
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 10,
            'status' => PromoCode::STATUS_PAUSED,
        ]);

        $result = $this->service->validate('PAUSED', new PromoCodeValidationContext(
            user: $user,
            bookingAmount: 100,
        ));

        $this->assertFalse($result->valid);
        $this->assertSame(PromoCodeService::ERROR_NOT_ACTIVE, $result->errorCode);
    }
}
