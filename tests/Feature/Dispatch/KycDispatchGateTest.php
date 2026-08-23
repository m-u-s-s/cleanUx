<?php

namespace Tests\Feature\Dispatch;

use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Dispatch\MissionDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Décision produit 2026-06-11 : KYC = BLOCAGE STRICT. */
class KycDispatchGateTest extends TestCase
{
    use RefreshDatabase;

    private function provider(string $verificationStatus): User
    {
        $provider = User::factory()->employe()->create();
        ProviderProfile::factory()->create([
            'user_id' => $provider->id,
            'status' => 'active',
            'verification_status' => $verificationStatus,
        ]);

        return $provider->fresh('providerProfile');
    }

    public function test_create_offer_is_blocked_for_provider_without_cleared_kyc(): void
    {
        Notification::fake();
        $provider = $this->provider('pending');
        $mission = Mission::factory()->create();

        try {
            app(MissionDispatchService::class)->createOffer($mission, $provider);
            $this->fail('createOffer() must refuse a provider whose KYC is not cleared.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('KYC', $e->getMessage());
        }

        $this->assertSame(
            0,
            MissionAssignment::where('mission_id', $mission->id)->count(),
            'No assignment must be created for a non-KYC provider.'
        );
    }

    public function test_create_offer_succeeds_for_kyc_cleared_provider(): void
    {
        Notification::fake();
        $provider = $this->provider('verified');
        $mission = Mission::factory()->create();

        $assignment = app(MissionDispatchService::class)->createOffer($mission, $provider);

        $this->assertSame($provider->id, $assignment->user_id);
        $this->assertSame('assigned', $assignment->assignment_status);
    }

    public function test_accept_is_blocked_if_kyc_revoked_after_offer(): void
    {
        Notification::fake();
        $provider = $this->provider('verified');
        $mission = Mission::factory()->create();
        $assignment = app(MissionDispatchService::class)->createOffer($mission, $provider);

        // KYC révoqué entre l'offre et l'acceptation.
        $provider->providerProfile->update(['verification_status' => 'pending']);

        $this->expectException(\DomainException::class);
        app(MissionDispatchService::class)->accept($assignment->fresh());
    }
}
