<?php

namespace Tests\Feature\OnboardingV2;

use App\Models\OnboardingProgress;
use App\Models\ProviderOnboardingDocument;
use App\Models\User;
use App\Services\Onboarding\ProviderOnboardingService;
use Database\Seeders\OnboardingJourneysSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * M19 — finishing the legacy provider onboarding (the real path) must reflect in the
 * OnboardingV2 journey, instead of leaving it permanently never-started.
 */
class OnboardingV2SyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_approval_marks_v2_journey_complete(): void
    {
        $this->seed(OnboardingJourneysSeeder::class);
        Storage::fake('private');

        $user = User::factory()->employe()->create(['stripe_connect_status' => 'active']);
        $admin = User::factory()->admin()->create();

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

        // Pre-condition: no v2 journey progress yet (the divergence the audit flagged).
        $this->assertSame(0, OnboardingProgress::where('user_id', $user->id)->count());

        $service->approveOnboarding($user->fresh(), $admin);

        $progress = OnboardingProgress::where('user_id', $user->id)->first();
        $this->assertNotNull($progress, 'a v2 journey must now exist for the onboarded provider');
        $this->assertSame(OnboardingProgress::STATUS_COMPLETED, $progress->status);
        $this->assertEqualsWithDelta(100, (float) $progress->percent_complete, 0.01);
    }
}
