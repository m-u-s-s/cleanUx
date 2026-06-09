<?php

namespace Tests\Feature\Promotion;

use App\Models\Booking;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\User;
use App\Services\Promotion\BookingPromoCodeApplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class BookingPromoCodeApplierCoverageBatch14Test extends TestCase
{
    use RefreshDatabase;

    private function makeBooking(User $client, array $overrides = []): Booking
    {
        return Booking::create(array_merge([
            'client_id' => $client->id,
            'date' => now()->addDay(),
            'heure' => '10:00',
            'status' => 'en_attente',
            'devis_estime' => 100,
        ], $overrides));
    }

    public function test_blank_code_returns_null_without_redemption(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client);

        $applier = app(BookingPromoCodeApplier::class);

        $this->assertNull($applier->applyToBooking($booking, $client, '   '));
        $this->assertNull($applier->applyToBooking($booking, $client, null));
        $this->assertSame(0, PromoCodeRedemption::query()->count());
    }

    public function test_zero_amount_booking_returns_null(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client, ['devis_estime' => 0, 'estimated_price' => 0]);

        PromoCode::create([
            'code' => 'BATCH14ZERO',
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 10,
            'status' => PromoCode::STATUS_ACTIVE,
        ]);

        $applier = app(BookingPromoCodeApplier::class);

        $this->assertNull($applier->applyToBooking($booking, $client, 'BATCH14ZERO'));
        $this->assertSame(0, PromoCodeRedemption::query()->count());
    }

    public function test_invalid_code_throws_validation_exception(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client);

        $applier = app(BookingPromoCodeApplier::class);

        $this->expectException(ValidationException::class);
        $applier->applyToBooking($booking, $client, 'DOES-NOT-EXIST');
    }

    public function test_valid_percent_code_applies_and_writes_back_to_booking(): void
    {
        $client = User::factory()->client()->create();
        $booking = $this->makeBooking($client, [
            'devis_estime' => 200,
            'estimated_price' => 200,
            'zone_snapshot' => ['country_id' => 1],
            'pricing_snapshot' => ['currency' => 'EUR'],
        ]);

        PromoCode::create([
            'code' => 'BATCH14PCT',
            'discount_type' => PromoCode::TYPE_PERCENT,
            'discount_value' => 25,
            'status' => PromoCode::STATUS_ACTIVE,
            'first_booking_only' => true,
        ]);

        $applier = app(BookingPromoCodeApplier::class);
        $redemption = $applier->applyToBooking($booking, $client, 'BATCH14PCT');

        $this->assertInstanceOf(PromoCodeRedemption::class, $redemption);
        $this->assertEqualsWithDelta(50.0, (float) $redemption->discount_amount, 0.01);
        $this->assertEqualsWithDelta(150.0, (float) $redemption->booking_amount_after, 0.01);

        $booking->refresh();
        $this->assertEqualsWithDelta(150.0, (float) $booking->devis_estime, 0.01);
        $this->assertEqualsWithDelta(150.0, (float) $booking->estimated_price, 0.01);

        $applied = data_get($booking->pricing_snapshot, 'promo_code_applied');
        $this->assertNotNull($applied);
        $this->assertSame($redemption->id, $applied['redemption_id']);
        $this->assertSame('BATCH14PCT', $applied['code']);
        $this->assertEqualsWithDelta(50.0, (float) $applied['discount_amount'], 0.01);
        $this->assertEqualsWithDelta(200.0, (float) $applied['amount_before'], 0.01);
        $this->assertEqualsWithDelta(150.0, (float) $applied['amount_after'], 0.01);
    }

    public function test_first_booking_only_rejected_when_prior_booking_exists(): void
    {
        $client = User::factory()->client()->create();

        // A pre-existing booking makes the new one NOT the first.
        $this->makeBooking($client, ['devis_estime' => 50]);
        $booking = $this->makeBooking($client, ['devis_estime' => 120]);

        PromoCode::create([
            'code' => 'BATCH14FIRST',
            'discount_type' => PromoCode::TYPE_FIXED,
            'discount_value' => 10,
            'status' => PromoCode::STATUS_ACTIVE,
            'first_booking_only' => true,
        ]);

        $applier = app(BookingPromoCodeApplier::class);

        $this->expectException(ValidationException::class);
        $applier->applyToBooking($booking, $client, 'BATCH14FIRST');
    }
}
