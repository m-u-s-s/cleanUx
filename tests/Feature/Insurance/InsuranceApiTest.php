<?php

namespace Tests\Feature\Insurance;

use App\Models\Booking;
use App\Models\BookingInsurance;
use App\Models\User;
use App\Services\Insurance\InsuranceProviderInterface;
use App\Services\Insurance\InsuranceService;
use App\Services\Insurance\Providers\InsuranceMockProvider;
use Database\Seeders\InsurancePlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InsuranceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(InsuranceProviderInterface::class, InsuranceMockProvider::class);
        $this->seed(InsurancePlansSeeder::class);
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

    public function test_plans_endpoint_lists_available_plans(): void
    {
        $user = User::factory()->client()->create();
        $booking = $this->makeBooking($user);

        Sanctum::actingAs($user);
        $response = $this->getJson("/api/client/bookings/{$booking->id}/insurance-plans");

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    public function test_purchase_endpoint_requires_auth(): void
    {
        $this->postJson('/api/client/bookings/1/insurance', [
            'plan_code' => 'basic',
        ])->assertStatus(401);
    }

    public function test_purchase_endpoint_creates_insurance(): void
    {
        $user = User::factory()->client()->create();
        $booking = $this->makeBooking($user);

        Sanctum::actingAs($user);
        $response = $this->postJson("/api/client/bookings/{$booking->id}/insurance", [
            'plan_code' => 'standard',
        ]);

        $response->assertStatus(201);
        $response->assertJson(['ok' => true]);
        $this->assertSame(1, BookingInsurance::count());
    }

    public function test_purchase_endpoint_validates_plan_code(): void
    {
        $user = User::factory()->client()->create();
        $booking = $this->makeBooking($user);

        Sanctum::actingAs($user);
        $this->postJson("/api/client/bookings/{$booking->id}/insurance", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['plan_code']);
    }

    public function test_index_endpoint_returns_only_user_insurances(): void
    {
        $alice = User::factory()->client()->create();
        $bob = User::factory()->client()->create();

        $aliceBooking = $this->makeBooking($alice);
        $bobBooking = $this->makeBooking($bob);

        app(InsuranceService::class)->purchase($aliceBooking->id, 'basic', $alice);
        app(InsuranceService::class)->purchase($bobBooking->id, 'basic', $bob);

        Sanctum::actingAs($alice);
        $response = $this->getJson('/api/client/insurances');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_cancel_endpoint_rejects_other_user(): void
    {
        $alice = User::factory()->client()->create();
        $bob = User::factory()->client()->create();
        $booking = $this->makeBooking($alice);
        $insurance = app(InsuranceService::class)->purchase($booking->id, 'basic', $alice);

        Sanctum::actingAs($bob);
        $this->postJson("/api/client/insurances/{$insurance->id}/cancel")
            ->assertStatus(403);
    }

    public function test_cancel_endpoint_marks_cancelled(): void
    {
        $user = User::factory()->client()->create();
        $booking = $this->makeBooking($user);
        $insurance = app(InsuranceService::class)->purchase($booking->id, 'basic', $user);

        Sanctum::actingAs($user);
        $response = $this->postJson("/api/client/insurances/{$insurance->id}/cancel");

        $response->assertOk();
        $insurance->refresh();
        $this->assertSame(BookingInsurance::STATUS_CANCELLED, $insurance->status);
    }

    public function test_file_claim_endpoint_creates_claim(): void
    {
        $user = User::factory()->client()->create();
        $booking = $this->makeBooking($user);
        $insurance = app(InsuranceService::class)->purchase($booking->id, 'standard', $user);

        Sanctum::actingAs($user);
        $response = $this->postJson("/api/client/insurances/{$insurance->id}/claims", [
            'incident_type' => 'damage',
            'description' => 'Mur abîmé pendant la prestation, peinture rayée.',
            'incident_date' => now()->subDay()->format('Y-m-d'),
            'amount_claimed_cents' => 40000,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['ok', 'claim' => ['id', 'status']]);
    }

    public function test_file_claim_endpoint_validates_fields(): void
    {
        $user = User::factory()->client()->create();
        $booking = $this->makeBooking($user);
        $insurance = app(InsuranceService::class)->purchase($booking->id, 'standard', $user);

        Sanctum::actingAs($user);
        $this->postJson("/api/client/insurances/{$insurance->id}/claims", [
            'incident_type' => 'invalid',
            'description' => 'too short',
            'incident_date' => '2099-01-01',
            'amount_claimed_cents' => 0,
        ])->assertStatus(422);
    }
}
