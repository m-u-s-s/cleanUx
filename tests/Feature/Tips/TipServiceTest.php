<?php

namespace Tests\Feature\Tips;

use App\Models\Booking;
use App\Models\BookingTip;
use App\Models\User;
use App\Services\Tips\TipService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TipServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function createCompletedBooking(User $client, User $provider): Booking
    {
        return Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $provider->id,
            'status' => 'termine',
            'devis_estime' => 100.00,
        ]);
    }

    public function test_create_tip_succeeds_for_completed_booking(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = $this->createCompletedBooking($client, $provider);

        $tip = app(TipService::class)->create($client, $booking, 1500, '15%', 15);

        $this->assertInstanceOf(BookingTip::class, $tip);
        $this->assertSame(BookingTip::STATUS_PENDING, $tip->status);
        $this->assertSame(1500, $tip->amount_cents);
        $this->assertSame(15, (int) $tip->preset_percent);
        $this->assertSame(15, (int) $tip->client_bonus_points);
    }

    public function test_create_tip_rejects_other_users_booking(): void
    {
        $client = User::factory()->client()->create();
        $otherClient = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = $this->createCompletedBooking($otherClient, $provider);

        $this->expectException(ValidationException::class);
        app(TipService::class)->create($client, $booking, 1500);
    }

    public function test_create_tip_rejects_uncompleted_booking(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $provider->id,
            'status' => 'en_cours',
        ]);

        $this->expectException(ValidationException::class);
        app(TipService::class)->create($client, $booking, 1500);
    }

    public function test_create_tip_rejects_out_of_range_amount(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = $this->createCompletedBooking($client, $provider);

        $this->expectException(ValidationException::class);
        app(TipService::class)->create($client, $booking, 50);   // < 100 min
    }

    public function test_create_tip_idempotent_returns_existing(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = $this->createCompletedBooking($client, $provider);
        $service = app(TipService::class);

        $tip1 = $service->create($client, $booking, 1500);
        $tip2 = $service->create($client, $booking, 2000);   // 2nd request

        $this->assertSame($tip1->id, $tip2->id);   // Même tip retourné
        $this->assertSame(1500, $tip2->amount_cents);   // Montant initial préservé
    }

    public function test_confirm_charge_transitions_to_charged(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = $this->createCompletedBooking($client, $provider);
        $service = app(TipService::class);

        $tip = $service->create($client, $booking, 1500);
        $confirmed = $service->confirmCharge($tip, 'pi_test_12345');

        $this->assertSame(BookingTip::STATUS_CHARGED, $confirmed->status);
        $this->assertSame('pi_test_12345', $confirmed->stripe_payment_intent_id);
        $this->assertNotNull($confirmed->charged_at);
    }

    public function test_pay_out_requires_charged_state(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = $this->createCompletedBooking($client, $provider);
        $service = app(TipService::class);

        $tip = $service->create($client, $booking, 1500);

        $this->expectException(ValidationException::class);
        $service->markPaidOut($tip, 'tr_test_999');   // Pas encore charged
    }

    public function test_pay_out_flow_complete(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = $this->createCompletedBooking($client, $provider);
        $service = app(TipService::class);

        $tip = $service->create($client, $booking, 2000);
        $service->confirmCharge($tip, 'pi_xyz');
        $paid = $service->markPaidOut($tip->fresh(), 'tr_xyz');

        $this->assertSame(BookingTip::STATUS_PAID_OUT, $paid->status);
        $this->assertSame('tr_xyz', $paid->stripe_transfer_id);
        $this->assertNotNull($paid->paid_out_at);
    }

    public function test_cancel_only_works_on_pending(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = $this->createCompletedBooking($client, $provider);
        $service = app(TipService::class);

        $tip = $service->create($client, $booking, 1500);
        $cancelled = $service->cancel($tip);
        $this->assertSame(BookingTip::STATUS_CANCELLED, $cancelled->status);

        // Une fois cancelled, on peut recréer
        $tip2 = $service->create($client, $booking, 2500);
        $this->assertSame(BookingTip::STATUS_PENDING, $tip2->status);
        $this->assertSame(2500, $tip2->amount_cents);
    }

    public function test_suggestions_compute_correct_amounts(): void
    {
        $client = User::factory()->client()->create();
        $provider = User::factory()->employe()->create();
        $booking = Booking::factory()->create([
            'client_id' => $client->id,
            'employe_id' => $provider->id,
            'status' => 'termine',
            'devis_estime' => 80.00,
        ]);

        $suggestions = app(TipService::class)->suggestionsForBooking($booking);

        $this->assertCount(3, $suggestions);
        $this->assertSame(800, $suggestions[0]['amount_cents']);   // 10% of 80€
        $this->assertSame(1200, $suggestions[1]['amount_cents']);  // 15%
        $this->assertSame(1600, $suggestions[2]['amount_cents']);  // 20%
    }
}
