<?php

namespace Tests\Feature\Kyc;

use App\Events\Kyc\KycCompleted;
use App\Events\Kyc\KycStarted;
use App\Models\KycVerification;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Kyc\KycProviderInterface;
use App\Services\Kyc\KycVerificationService;
use App\Services\Kyc\Providers\KycMockProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class KycLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(KycProviderInterface::class, KycMockProvider::class);
    }

    public function test_start_creates_verification_and_marks_in_review(): void
    {
        Event::fake([KycStarted::class]);

        $user = $this->makeProviderUser();

        $verification = app(KycVerificationService::class)->start($user, 'BE');

        $this->assertNotNull($verification->external_applicant_id);
        $this->assertStringStartsWith('mock_app_', $verification->external_applicant_id);
        $this->assertSame(KycVerification::STATUS_IN_REVIEW, $verification->status);
        $this->assertSame('mock', $verification->provider);
        $this->assertSame('BE', $verification->country_code);

        Event::assertDispatched(KycStarted::class);
    }

    public function test_sync_with_clear_result_auto_approves_provider_profile(): void
    {
        Event::fake([KycCompleted::class]);

        $user = $this->makeProviderUser();
        $verification = app(KycVerificationService::class)->start($user);

        app(KycVerificationService::class)->syncStatus($verification->fresh());

        $verification->refresh();
        $this->assertSame(KycVerification::STATUS_CLEAR, $verification->status);
        $this->assertSame(KycVerification::DECISION_APPROVED, $verification->decision);

        $profile = $user->fresh()->providerProfile;
        $this->assertSame('verified', $profile->verification_status);
        $this->assertNotNull($profile->kyc_completed_at);

        Event::assertDispatched(KycCompleted::class);
    }

    public function test_sync_rejected_user_marks_profile_rejected(): void
    {
        $user = User::factory()->create(['role' => 'employe', 'email' => 'fraud-rejected@example.com']);
        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'pending',
        ]);

        $verification = app(KycVerificationService::class)->start($user);
        app(KycVerificationService::class)->syncStatus($verification->fresh());

        $verification->refresh();
        $this->assertSame(KycVerification::STATUS_REJECTED, $verification->status);
        $this->assertSame(KycVerification::DECISION_REJECTED, $verification->decision);
        $this->assertNotNull($verification->rejection_reason);

        $profile = $user->fresh()->providerProfile;
        $this->assertSame('rejected', $profile->verification_status);
    }

    public function test_manual_review_email_keeps_profile_pending(): void
    {
        $user = User::factory()->create(['role' => 'employe', 'email' => 'manual-review@example.com']);
        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'pending',
        ]);

        $verification = app(KycVerificationService::class)->start($user);
        app(KycVerificationService::class)->syncStatus($verification->fresh());

        $verification->refresh();
        $this->assertSame(KycVerification::STATUS_CONSIDER, $verification->status);
        $this->assertSame(KycVerification::DECISION_MANUAL_REVIEW, $verification->decision);

        $profile = $user->fresh()->providerProfile;
        $this->assertSame('pending', $profile->verification_status);
    }

    public function test_admin_can_manually_approve(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['role' => 'employe', 'email' => 'manual-review@example.com']);
        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'pending',
        ]);

        $verification = app(KycVerificationService::class)->start($user);
        app(KycVerificationService::class)->syncStatus($verification->fresh());

        $verification->refresh();
        $this->assertSame(KycVerification::DECISION_MANUAL_REVIEW, $verification->decision);

        app(KycVerificationService::class)->approveManually(
            $verification,
            $admin,
            'Documents validés en visio',
        );

        $verification->refresh();
        $this->assertSame(KycVerification::DECISION_APPROVED, $verification->decision);
        $this->assertSame(KycVerification::STATUS_CLEAR, $verification->status);
        $this->assertSame((int) $admin->id, (int) $verification->reviewed_by_user_id);

        $this->assertSame('verified', $user->fresh()->providerProfile->verification_status);
    }

    public function test_admin_can_manually_reject(): void
    {
        $admin = User::factory()->admin()->create();
        $user = $this->makeProviderUser();

        $verification = app(KycVerificationService::class)->start($user);

        app(KycVerificationService::class)->rejectManually(
            $verification,
            $admin,
            'Documents non valides',
        );

        $verification->refresh();
        $this->assertSame(KycVerification::DECISION_REJECTED, $verification->decision);
        $this->assertSame('Documents non valides', $verification->rejection_reason);
        $this->assertSame('rejected', $user->fresh()->providerProfile->verification_status);
    }

    protected function makeProviderUser(): User
    {
        $user = User::factory()->create(['role' => 'employe', 'email' => 'good-' . uniqid() . '@example.com']);
        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'pending',
        ]);
        return $user->fresh();
    }
}
