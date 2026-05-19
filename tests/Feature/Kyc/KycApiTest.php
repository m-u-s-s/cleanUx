<?php

namespace Tests\Feature\Kyc;

use App\Models\KycVerification;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Kyc\KycProviderInterface;
use App\Services\Kyc\Providers\KycMockProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KycApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(KycProviderInterface::class, KycMockProvider::class);
    }

    public function test_start_endpoint_creates_verification(): void
    {
        $user = User::factory()->create(['role' => 'employe', 'email' => 'good@example.com']);
        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'pending',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/provider/kyc/start', [
            'country_code' => 'BE',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['verification_id', 'provider', 'status', 'decision']);
        $this->assertSame('mock', $response->json('provider'));
    }

    public function test_status_endpoint_returns_latest_verification(): void
    {
        $user = User::factory()->create(['role' => 'employe']);
        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'pending',
        ]);

        KycVerification::create([
            'user_id' => $user->id,
            'provider' => 'mock',
            'status' => KycVerification::STATUS_IN_REVIEW,
            'decision' => KycVerification::DECISION_PENDING,
            'started_at' => now(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/provider/kyc/status');
        $response->assertOk();
        $response->assertJson([
            'has_verification' => true,
            'provider' => 'mock',
            'status' => 'in_review',
        ]);
    }

    public function test_status_returns_false_when_no_verification(): void
    {
        $user = User::factory()->create(['role' => 'employe']);
        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'pending',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/provider/kyc/status');
        $response->assertOk();
        $response->assertJson(['has_verification' => false]);
    }

    public function test_sync_forbidden_for_other_user_verification(): void
    {
        $userA = User::factory()->create(['role' => 'employe']);
        $userB = User::factory()->create(['role' => 'employe']);
        ProviderProfile::create([
            'user_id' => $userB->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'pending',
        ]);

        $verification = KycVerification::create([
            'user_id' => $userB->id,
            'provider' => 'mock',
            'status' => KycVerification::STATUS_IN_REVIEW,
            'decision' => KycVerification::DECISION_PENDING,
            'external_applicant_id' => 'mock_app_aaa',
            'started_at' => now(),
        ]);

        Sanctum::actingAs($userA);

        $this->postJson('/api/provider/kyc/verifications/' . $verification->id . '/sync')
            ->assertStatus(403);
    }
}
