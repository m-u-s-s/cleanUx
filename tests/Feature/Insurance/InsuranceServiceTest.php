<?php

namespace Tests\Feature\Insurance;

use App\Models\Booking;
use App\Models\BookingInsurance;
use App\Models\InsuranceClaim;
use App\Models\InsurancePlan;
use App\Models\User;
use App\Services\Insurance\InsuranceProviderInterface;
use App\Services\Insurance\InsuranceService;
use App\Services\Insurance\InsuranceWebhookUpdate;
use App\Services\Insurance\Providers\InsuranceMockProvider;
use Database\Seeders\InsurancePlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InsuranceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(InsuranceProviderInterface::class, InsuranceMockProvider::class);
        $this->seed(InsurancePlansSeeder::class);
        Config::set('insurance.enabled', true);
    }

    protected function makeBooking(User $user): Booking
    {
        return Booking::create([
            'client_id' => $user->id,
            'date' => now()->addDay(),
            'heure' => '10:00',
            'status' => 'en_attente',
            'devis_estime' => 200,
        ]);
    }

    public function test_purchase_creates_active_insurance_with_mock_provider(): void
    {
        $user = User::factory()->client()->create();
        $booking = $this->makeBooking($user);

        $insurance = app(InsuranceService::class)->purchase($booking->id, 'basic', $user);

        $this->assertInstanceOf(BookingInsurance::class, $insurance);
        $this->assertSame(BookingInsurance::STATUS_ACTIVE, $insurance->status);
        $this->assertStringStartsWith('mock_pol_', $insurance->external_id);
        $this->assertNotNull($insurance->policy_number);
        $this->assertGreaterThan(0, $insurance->premium_cents);
    }

    public function test_purchase_is_idempotent_with_same_booking_and_plan(): void
    {
        $user = User::factory()->client()->create();
        $booking = $this->makeBooking($user);

        $svc = app(InsuranceService::class);
        $a = $svc->purchase($booking->id, 'basic', $user);
        $b = $svc->purchase($booking->id, 'basic', $user);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, BookingInsurance::count());
    }

    public function test_purchase_rejects_unknown_plan(): void
    {
        $user = User::factory()->client()->create();
        $booking = $this->makeBooking($user);

        $this->expectException(ValidationException::class);

        app(InsuranceService::class)->purchase($booking->id, 'nonexistent', $user);
    }

    public function test_purchase_handles_provider_failure(): void
    {
        $user = User::factory()->client()->create();
        $booking = $this->makeBooking($user);

        InsurancePlan::query()->create([
            'code' => 'fail_plan',
            'name' => 'Fail',
            'coverage_amount_cents' => 100000,
            'premium_base_cents' => 100,
            'premium_percent' => 0,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $insurance = app(InsuranceService::class)->purchase($booking->id, 'fail_plan', $user);

        $this->assertSame(BookingInsurance::STATUS_CANCELLED, $insurance->status);
        $this->assertNotNull($insurance->cancelled_at);
    }

    public function test_cancel_marks_status_cancelled(): void
    {
        $user = User::factory()->client()->create();
        $booking = $this->makeBooking($user);
        $insurance = app(InsuranceService::class)->purchase($booking->id, 'basic', $user);

        $cancelled = app(InsuranceService::class)->cancel($insurance);

        $this->assertSame(BookingInsurance::STATUS_CANCELLED, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_file_claim_creates_claim_with_under_review_status(): void
    {
        $user = User::factory()->client()->create();
        $booking = $this->makeBooking($user);
        $insurance = app(InsuranceService::class)->purchase($booking->id, 'standard', $user);

        // Premium on standard for 200 EUR booking ≈ 900 cents; max claim = 50× = 45000
        $claim = app(InsuranceService::class)->fileClaim(
            insurance: $insurance,
            claimant: $user,
            incidentType: InsuranceClaim::INCIDENT_DAMAGE,
            description: 'Mur abîmé pendant la prestation, peinture rayée.',
            incidentDate: now()->subDay(),
            amountClaimedCents: 40000,
        );

        $this->assertInstanceOf(InsuranceClaim::class, $claim);
        $this->assertSame(InsuranceClaim::STATUS_UNDER_REVIEW, $claim->status);
        $this->assertStringStartsWith('mock_clm_', $claim->external_claim_id);

        $insurance->refresh();
        $this->assertSame(BookingInsurance::STATUS_CLAIMED, $insurance->status);
    }

    public function test_file_claim_rejects_amount_above_coverage(): void
    {
        $user = User::factory()->client()->create();
        $booking = $this->makeBooking($user);
        $insurance = app(InsuranceService::class)->purchase($booking->id, 'basic', $user);

        $this->expectException(ValidationException::class);

        app(InsuranceService::class)->fileClaim(
            insurance: $insurance,
            claimant: $user,
            incidentType: 'damage',
            description: 'Dommage colossal',
            incidentDate: now()->subDay(),
            amountClaimedCents: 99999999,
        );
    }

    public function test_file_claim_rejects_incident_outside_window(): void
    {
        Config::set('insurance.claims.filing_window_days', 30);
        $user = User::factory()->client()->create();
        $booking = $this->makeBooking($user);
        $insurance = app(InsuranceService::class)->purchase($booking->id, 'standard', $user);

        $this->expectException(ValidationException::class);

        app(InsuranceService::class)->fileClaim(
            insurance: $insurance,
            claimant: $user,
            incidentType: 'damage',
            description: 'Vieux incident.',
            incidentDate: now()->subDays(60),
            amountClaimedCents: 10000,
        );
    }

    public function test_file_claim_fraud_simulation_yields_rejected(): void
    {
        $user = User::factory()->client()->create();
        $booking = $this->makeBooking($user);
        $insurance = app(InsuranceService::class)->purchase($booking->id, 'standard', $user);

        $claim = app(InsuranceService::class)->fileClaim(
            insurance: $insurance,
            claimant: $user,
            incidentType: 'fraud_simulation',
            description: 'Test fraud auto reject',
            incidentDate: now()->subDay(),
            amountClaimedCents: 10000,
        );

        $this->assertSame(InsuranceClaim::STATUS_REJECTED, $claim->status);
    }

    public function test_apply_webhook_update_claim_paid(): void
    {
        $user = User::factory()->client()->create();
        $booking = $this->makeBooking($user);
        $insurance = app(InsuranceService::class)->purchase($booking->id, 'standard', $user);

        $claim = app(InsuranceService::class)->fileClaim(
            insurance: $insurance,
            claimant: $user,
            incidentType: 'damage',
            description: 'Test claim description for webhook update flow.',
            incidentDate: now()->subDay(),
            amountClaimedCents: 40000,
        );

        $update = new InsuranceWebhookUpdate(
            target: InsuranceWebhookUpdate::TARGET_CLAIM,
            externalId: $claim->external_claim_id,
            newStatus: 'paid',
            amountSettledCents: 35000,
            reason: 'Settled',
        );

        $updated = app(InsuranceService::class)->applyWebhookUpdate($update);

        $this->assertInstanceOf(InsuranceClaim::class, $updated);
        $this->assertSame(InsuranceClaim::STATUS_PAID, $updated->status);
        $this->assertSame(35000, (int) $updated->amount_settled_cents);
        $this->assertNotNull($updated->paid_at);
    }

    public function test_apply_webhook_update_policy_cancelled(): void
    {
        $user = User::factory()->client()->create();
        $booking = $this->makeBooking($user);
        $insurance = app(InsuranceService::class)->purchase($booking->id, 'basic', $user);

        $update = new InsuranceWebhookUpdate(
            target: InsuranceWebhookUpdate::TARGET_POLICY,
            externalId: $insurance->external_id,
            newStatus: 'cancelled',
        );

        $updated = app(InsuranceService::class)->applyWebhookUpdate($update);

        $this->assertInstanceOf(BookingInsurance::class, $updated);
        $this->assertSame(BookingInsurance::STATUS_CANCELLED, $updated->status);
    }
}
