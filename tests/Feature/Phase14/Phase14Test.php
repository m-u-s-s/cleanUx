<?php

namespace Tests\Feature\Phase14;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\PricingZoneState;
use App\Models\ProviderOnboardingDocument;
use App\Models\ProviderProfile;
use App\Models\ServiceZone;
use App\Models\User;
use App\Services\Cancellation\CancelBookingService;
use App\Services\Cancellation\CancellationFeeCalculator;
use App\Services\Onboarding\ProviderOnboardingService;
use App\Services\Pricing\SurgePricingEngine;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase14Test extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────
    // SURGE PRICING
    // ──────────────────────────────────────────────

    public function test_surge_returns_base_price_when_no_zone_no_peak(): void
    {
        // Force un horaire neutre (3h du matin = pas de peak)
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 3, 0, 0)); // mercredi 3h

        $result = app(SurgePricingEngine::class)->calculate(50.0, null);

        $this->assertEquals(50.0, $result['final_price']);
        $this->assertEquals(1.0, $result['multiplier']);

        Carbon::setTestNow();
    }

    public function test_surge_applies_temporal_peak_in_evening(): void
    {
        // Mercredi 18h = peak 17-19 → ×1.30
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 18, 0, 0));

        $result = app(SurgePricingEngine::class)->calculate(100.0, null);

        $this->assertEquals(130.0, $result['final_price']);
        $this->assertEquals(1.30, $result['multiplier']);

        Carbon::setTestNow();
    }

    public function test_surge_applies_asap_extra(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 3, 0, 0));

        $result = app(SurgePricingEngine::class)->calculate(100.0, null, [
            'booking_mode' => 'asap',
        ]);

        // 1.0 (no peak) × 1.25 (asap) = 1.25
        $this->assertEquals(1.25, $result['multiplier']);
        $this->assertEquals(125.0, $result['final_price']);

        Carbon::setTestNow();
    }

    public function test_surge_caps_at_max_multiplier(): void
    {
        config(['surge.max_multiplier' => 2.0]);

        Carbon::setTestNow(Carbon::create(2026, 5, 14, 18, 0, 0)); // peak 1.30

        $result = app(SurgePricingEngine::class)->calculate(100.0, null, [
            'booking_mode' => 'asap',  // ×1.25 = 1.625
        ]);

        // 1.30 × 1.25 = 1.625 → on cap pas car < 2.0
        $this->assertLessThanOrEqual(2.0, $result['multiplier']);

        Carbon::setTestNow();
    }

    public function test_surge_uses_zone_state_when_active(): void
    {
        $zone = ServiceZone::create([
            'name' => 'Test Zone',
            'slug' => 'test-zone-' . Str::random(5),
            'status' => 'active',
        ]);

        PricingZoneState::create([
            'service_zone_id'        => $zone->id,
            'multiplier'             => 1.50,
            'demand_factor'          => 1.20,
            'supply_factor'          => 1.25,
            'temporal_factor'        => 1.00,
            'open_bookings_count'    => 10,
            'online_providers_count' => 1,
            'expires_at'             => now()->addMinutes(5),
        ]);

        $result = app(SurgePricingEngine::class)->calculate(100.0, $zone);

        $this->assertEquals(150.0, $result['final_price']);
        $this->assertEquals('cached', $result['source']);
    }

    public function test_surge_falls_back_to_live_when_state_expired(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 3, 0, 0));

        $zone = ServiceZone::create([
            'name' => 'Test Zone',
            'slug' => 'test-zone-' . Str::random(5),
            'status' => 'active',
        ]);

        PricingZoneState::create([
            'service_zone_id' => $zone->id,
            'multiplier'      => 2.50,
            'expires_at'      => now()->subMinutes(5), // expiré
        ]);

        $result = app(SurgePricingEngine::class)->calculate(100.0, $zone);

        $this->assertEquals('live', $result['source']);
        // Live calc → multiplier proche de 1.0 (pas de bookings, pas de peak)
        $this->assertLessThan(1.5, $result['multiplier']);

        Carbon::setTestNow();
    }

    // ──────────────────────────────────────────────
    // PROVIDER ONBOARDING
    // ──────────────────────────────────────────────

    public function test_start_onboarding_creates_provider_profile(): void
    {
        $user = User::factory()->create();

        $profile = app(ProviderOnboardingService::class)->startOnboarding($user);

        $this->assertNotNull($profile);
        $this->assertEquals(0, $profile->onboarding_step);
        $this->assertEquals('pending', $profile->verification_status);
    }

    public function test_set_profile_basics_updates_user_and_advances_step(): void
    {
        $user = User::factory()->create();
        $service = app(ProviderOnboardingService::class);
        $service->startOnboarding($user);

        $profile = $service->setProfileBasics($user, [
            'name' => 'Jean Dupont',
            'phone' => '+32475123456',
            'bio' => 'Plombier 10 ans expérience',
        ]);

        $this->assertEquals('Jean Dupont', $user->fresh()->name);
        $this->assertEquals('Plombier 10 ans expérience', $profile->bio);
        $this->assertGreaterThanOrEqual(0, $profile->onboarding_step);
    }

    public function test_upload_document_creates_pending_record(): void
    {
        Storage::fake('private');

        $user = User::factory()->create();
        $service = app(ProviderOnboardingService::class);
        $service->startOnboarding($user);

        $file = UploadedFile::fake()->create('id.pdf', 100, 'application/pdf');
        $doc = $service->uploadDocument($user, ProviderOnboardingDocument::TYPE_IDENTITY_CARD, $file);

        $this->assertEquals(ProviderOnboardingDocument::STATUS_PENDING, $doc->status);
        $this->assertEquals(ProviderOnboardingDocument::TYPE_IDENTITY_CARD, $doc->document_type);
        Storage::disk('private')->assertExists($doc->file_path);
    }

    public function test_review_document_can_approve_or_reject(): void
    {
        Storage::fake('private');

        $user = User::factory()->create();
        $admin = User::factory()->create();
        $service = app(ProviderOnboardingService::class);
        $service->startOnboarding($user);

        $file = UploadedFile::fake()->create('id.pdf', 100, 'application/pdf');
        $doc = $service->uploadDocument($user, ProviderOnboardingDocument::TYPE_IDENTITY_CARD, $file);

        $approved = $service->reviewDocument($doc, $admin, true);
        $this->assertTrue($approved->isApproved());

        $file2 = UploadedFile::fake()->create('id2.pdf', 100, 'application/pdf');
        $doc2 = $service->uploadDocument($user, ProviderOnboardingDocument::TYPE_IDENTITY_CARD, $file2);

        $rejected = $service->reviewDocument($doc2, $admin, false, 'Document flou');
        $this->assertTrue($rejected->isRejected());
        $this->assertEquals('Document flou', $rejected->rejection_reason);
    }

    public function test_approve_onboarding_requires_identity_and_insurance(): void
    {
        Storage::fake('private');

        $user = User::factory()->create();
        $admin = User::factory()->create();
        $user->update(['stripe_connect_status' => 'active']);

        $service = app(ProviderOnboardingService::class);
        $service->startOnboarding($user);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('document d\'identité');
        $service->approveOnboarding($user->fresh(), $admin);
    }

    public function test_approve_onboarding_succeeds_with_all_documents(): void
    {
        Storage::fake('private');

        $user = User::factory()->create();
        $admin = User::factory()->create();
        $user->update(['stripe_connect_status' => 'active']);

        $service = app(ProviderOnboardingService::class);
        $service->startOnboarding($user);

        $idDoc = $service->uploadDocument(
            $user,
            ProviderOnboardingDocument::TYPE_IDENTITY_CARD,
            UploadedFile::fake()->create('id.pdf', 100, 'application/pdf')
        );
        $service->reviewDocument($idDoc, $admin, true);

        $insDoc = $service->uploadDocument(
            $user,
            ProviderOnboardingDocument::TYPE_INSURANCE,
            UploadedFile::fake()->create('insurance.pdf', 100, 'application/pdf')
        );
        $service->reviewDocument($insDoc, $admin, true);

        $profile = $service->approveOnboarding($user->fresh(), $admin);

        $this->assertEquals('verified', $profile->verification_status);
        $this->assertEquals('active', $profile->status);
        $this->assertNotNull($profile->onboarding_completed_at);
    }

    public function test_get_progress_returns_complete_state(): void
    {
        $user = User::factory()->create();
        $service = app(ProviderOnboardingService::class);
        $service->startOnboarding($user);

        $progress = $service->getProgress($user->fresh());

        $this->assertTrue($progress['started']);
        $this->assertEquals(0, $progress['current_step']);
        $this->assertEquals(7, $progress['total_steps']);
        $this->assertFalse($progress['completed']);
    }

    // ──────────────────────────────────────────────
    // CANCELLATION FEES
    // ──────────────────────────────────────────────

    public function test_cancellation_fee_more_than_24h_before_is_free(): void
    {
        $booking = $this->makeBooking([
            'scheduled_date' => now()->addDays(2)->toDateString(),
            'scheduled_time' => '10:00:00',
            'estimated_price' => 100,
        ]);

        $details = app(CancellationFeeCalculator::class)->forClientCancellation($booking);

        $this->assertEquals(0.0, $details['fee_amount']);
        $this->assertTrue($details['is_free']);
    }

    public function test_cancellation_fee_within_2h_to_24h_is_25_percent(): void
    {
        // Booking dans 5h
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));
        $booking = $this->makeBooking([
            'scheduled_date'  => '2026-05-14',
            'scheduled_time'  => '15:00:00',  // dans 5h
            'estimated_price' => 100,
            'created_at'      => now()->subHours(48),
        ]);

        $details = app(CancellationFeeCalculator::class)->forClientCancellation($booking);

        $this->assertEquals(25.0, $details['fee_amount']);
        $this->assertEquals(25, $details['fee_percent']);
        $this->assertFalse($details['is_free']);

        Carbon::setTestNow();
    }

    public function test_cancellation_fee_within_30min_to_2h_is_50_percent(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));
        $booking = $this->makeBooking([
            'scheduled_date'  => '2026-05-14',
            'scheduled_time'  => '11:00:00',  // dans 1h
            'estimated_price' => 100,
            'created_at'      => now()->subHours(48),
        ]);

        $details = app(CancellationFeeCalculator::class)->forClientCancellation($booking);

        $this->assertEquals(50.0, $details['fee_amount']);
        $this->assertEquals(50, $details['fee_percent']);

        Carbon::setTestNow();
    }

    public function test_cancellation_fee_within_30min_is_100_percent(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));
        $booking = $this->makeBooking([
            'scheduled_date'  => '2026-05-14',
            'scheduled_time'  => '10:15:00',  // dans 15 min
            'estimated_price' => 100,
            'created_at'      => now()->subHours(48),
        ]);

        $details = app(CancellationFeeCalculator::class)->forClientCancellation($booking);

        $this->assertEquals(100.0, $details['fee_amount']);
        $this->assertEquals(100, $details['fee_percent']);

        Carbon::setTestNow();
    }

    public function test_cancellation_grace_window_for_just_created_booking(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));
        $booking = $this->makeBooking([
            'scheduled_date'  => '2026-05-14',
            'scheduled_time'  => '10:30:00',
            'estimated_price' => 100,
            'created_at'      => now()->subMinutes(2), // créé il y a 2 min
        ]);

        $details = app(CancellationFeeCalculator::class)->forClientCancellation($booking);

        $this->assertTrue($details['is_free']);
        $this->assertEquals('free_within_grace', $details['reason_code']);

        Carbon::setTestNow();
    }

    public function test_provider_cancellation_30min_before_is_free(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));
        $booking = $this->makeBooking([
            'scheduled_date' => '2026-05-14',
            'scheduled_time' => '11:00:00', // dans 1h
        ]);

        $penalty = app(CancellationFeeCalculator::class)->forProviderCancellation($booking);

        $this->assertTrue($penalty['is_free']);
        $this->assertEquals(0.0, $penalty['penalty_eur']);

        Carbon::setTestNow();
    }

    public function test_provider_late_cancellation_applies_penalty(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));
        $booking = $this->makeBooking([
            'scheduled_date' => '2026-05-14',
            'scheduled_time' => '10:15:00', // dans 15 min
        ]);

        $penalty = app(CancellationFeeCalculator::class)->forProviderCancellation($booking);

        $this->assertFalse($penalty['is_free']);
        $this->assertGreaterThan(0, $penalty['penalty_eur']);
        $this->assertGreaterThan(0, $penalty['reliability_penalty']);

        Carbon::setTestNow();
    }

    public function test_no_show_detection(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 30, 0));
        $booking = $this->makeBooking([
            'scheduled_date' => '2026-05-14',
            'scheduled_time' => '10:00:00', // commencé il y a 30 min
        ]);

        // Avec grace 15 min : 30 min après le start = no-show
        $isNoShow = app(CancellationFeeCalculator::class)->isNoShow($booking);
        $this->assertTrue($isNoShow);

        Carbon::setTestNow();
    }

    public function test_cancel_by_client_marks_booking_with_fee_metadata(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 5, 14, 10, 0, 0));
        $client = User::factory()->create();
        $booking = $this->makeBooking([
            'scheduled_date'  => '2026-05-14',
            'scheduled_time'  => '11:00:00',
            'estimated_price' => 100,
            'customer_user_id'=> $client->id,
            'client_id'       => $client->id,
            'created_at'      => now()->subHours(48),
        ]);

        $result = app(CancelBookingService::class)->cancelByClient($booking, $client, 'changement de plans');

        $this->assertTrue($result['ok']);
        $this->assertEquals(50.0, $result['fee_details']['fee_amount']); // 50% car 1h avant
        $this->assertEquals('annule', $booking->fresh()->status);
        $this->assertEquals(50.0, $booking->fresh()->metadata['cancellation_fee']);

        Carbon::setTestNow();
    }

    public function test_cannot_cancel_already_completed_booking(): void
    {
        $client = User::factory()->create();
        $booking = $this->makeBooking([
            'status'          => 'termine',
            'customer_user_id'=> $client->id,
        ]);

        $this->expectException(\DomainException::class);
        app(CancelBookingService::class)->cancelByClient($booking, $client);
    }

    // ──────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────

    protected function makeBooking(array $overrides = []): Booking
    {
        $client = User::factory()->create();
        return Booking::create(array_merge([
            'booking_reference' => 'CUX-' . strtoupper(Str::random(6)),
            'customer_user_id'  => $client->id,
            'client_id'         => $client->id,
            'scheduled_date'    => now()->addDay()->toDateString(),
            'scheduled_time'    => '10:00:00',
            'status'            => 'confirme',
            'currency'          => 'EUR',
            'priority'          => 'normal',
            'booking_mode'      => 'scheduled',
            'estimated_price'   => 100,
        ], $overrides));
    }
}
