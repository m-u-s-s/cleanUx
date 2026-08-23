<?php

namespace Tests\Feature;

use App\Livewire\Admin\Onboarding\AdminOnboardingProvidersList;
use App\Models\ProviderOnboardingDocument;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;
use ReflectionMethod;
use Tests\TestCase;

/** Coverage batch 6 — exercises the secondary / private count-recalculation helpers of AdminOnboardingProvidersList that the primary feature test does not reach: calculateCounts buckets, getCountsProperty, the "stable" and "signal/bucket" recalculation strategies, document readiness probing and the various refresh entry points. */
class AdminOnboardingProvidersListCoverageBatch6Test extends TestCase
{
    use RefreshDatabase;

    protected function createAdmin(): User
    {
        return User::factory()->admin()->create([
            'permissions' => ['manage-services', 'perform-critical-admin-actions'],
            'access_scope' => User::ACCESS_SCOPE_ALL,
            'is_active' => true,
        ]);
    }

    protected function makeProfile(array $attributes): ProviderProfile
    {
        $user = User::factory()->employe()->create();

        return ProviderProfile::factory()->create(array_merge([
            'user_id' => $user->id,
        ], $attributes));
    }

    protected function makeLivewireInstance(): AdminOnboardingProvidersList
    {
        $this->actingAs($this->createAdmin());

        return Livewire::test(AdminOnboardingProvidersList::class)->instance();
    }

    protected function invoke(object $object, string $method, array $args = []): mixed
    {
        $ref = new ReflectionMethod($object, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($object, $args);
    }

    public function test_calculate_counts_classifies_every_bucket(): void
    {
        // rejected
        $this->makeProfile(['status' => 'rejected', 'verification_status' => 'rejected', 'onboarding_step' => 1]);
        // verified via verification_status
        $this->makeProfile(['status' => 'pending', 'verification_status' => 'verified', 'onboarding_step' => 6]);
        // verified via status === active
        $this->makeProfile(['status' => 'active', 'verification_status' => 'pending', 'onboarding_step' => 1, 'onboarding_completed_at' => null]);
        // pending (verification pending, step 0 so neither ready nor in_progress)
        $this->makeProfile(['status' => 'suspended', 'verification_status' => 'pending', 'onboarding_step' => 0]);
        // ready (not verified, step >= 5)
        $this->makeProfile(['status' => 'suspended', 'verification_status' => 'unverified', 'onboarding_step' => 5]);
        // in_progress (step between 1 and 4)
        $this->makeProfile(['status' => 'suspended', 'verification_status' => 'unverified', 'onboarding_step' => 3]);

        $counts = $this->invoke($this->makeLivewireInstance(), 'calculateCounts');

        $this->assertSame(1, $counts['rejected']);
        $this->assertSame(2, $counts['verified']);
        $this->assertSame(1, $counts['pending']);
        $this->assertSame(1, $counts['ready']);
        $this->assertSame(1, $counts['in_progress']);
        $this->assertSame(6, $counts['total']);
    }

    public function test_get_counts_property_computes_three_buckets(): void
    {
        // verified
        $this->makeProfile(['status' => 'pending', 'verification_status' => 'verified', 'onboarding_step' => 6, 'onboarding_completed_at' => null]);
        // ready: not verified, no completed_at, step >= 5
        $this->makeProfile(['status' => 'pending', 'verification_status' => 'pending', 'onboarding_step' => 5, 'onboarding_completed_at' => null]);
        // in_progress: not verified, no completed_at, 0 <= step < 5
        $this->makeProfile(['status' => 'pending', 'verification_status' => 'pending', 'onboarding_step' => 2, 'onboarding_completed_at' => null]);

        $counts = $this->makeLivewireInstance()->getCountsProperty();

        $this->assertSame(1, $counts['verified']);
        $this->assertSame(1, $counts['ready']);
        $this->assertSame(1, $counts['in_progress']);
    }

    public function test_recalculate_stable_and_normalize_status(): void
    {
        $verified = $this->makeProfile(['status' => 'pending', 'verification_status' => 'verified', 'onboarding_step' => 6]);
        $ready = $this->makeProfile(['status' => 'pending', 'verification_status' => 'ready', 'onboarding_step' => 5]);
        $inProgress = $this->makeProfile(['status' => 'pending', 'verification_status' => 'in_progress', 'onboarding_step' => 2]);

        $component = $this->makeLivewireInstance();

        $this->assertSame('verified', $this->invoke($component, 'normalizeProviderOnboardingStatusStable', [$verified]));
        $this->assertSame('ready', $this->invoke($component, 'normalizeProviderOnboardingStatusStable', [$ready]));
        $this->assertSame('in_progress', $this->invoke($component, 'normalizeProviderOnboardingStatusStable', [$inProgress]));

        $stable = $this->invoke($component, 'recalculateProviderOnboardingCountsStable');
        $this->assertSame(1, $stable['verified']);
        $this->assertSame(1, $stable['ready']);
        $this->assertSame(1, $stable['in_progress']);
    }

    public function test_normalize_status_defaults_to_in_progress_when_unknown(): void
    {
        $profile = $this->makeProfile(['status' => 'whatever', 'verification_status' => 'mystery', 'onboarding_step' => 1]);

        $this->assertSame(
            'in_progress',
            $this->invoke($this->makeLivewireInstance(), 'normalizeProviderOnboardingStatusStable', [$profile])
        );
    }

    public function test_provider_onboarding_bucket_variants(): void
    {
        $verified = $this->makeProfile(['status' => 'pending', 'verification_status' => 'verified']);
        $readyByStatus = $this->makeProfile(['status' => 'pending', 'verification_status' => 'ready_for_review']);
        $inProgress = $this->makeProfile(['status' => 'pending', 'verification_status' => 'in_progress']);

        // ready by documents: status not matched but every document approved
        $readyByDocs = $this->makeProfile(['status' => 'pending', 'verification_status' => 'unverified']);
        ProviderOnboardingDocument::factory()->approved()->create(['user_id' => $readyByDocs->user_id]);

        $component = $this->makeLivewireInstance();

        $this->assertSame('verified', $this->invoke($component, 'providerOnboardingBucket', [$verified]));
        $this->assertSame('ready', $this->invoke($component, 'providerOnboardingBucket', [$readyByStatus]));
        $this->assertSame('ready', $this->invoke($component, 'providerOnboardingBucket', [$readyByDocs]));
        $this->assertSame('in_progress', $this->invoke($component, 'providerOnboardingBucket', [$inProgress]));
    }

    public function test_provider_has_onboarding_signal_branches(): void
    {
        $byStatus = $this->makeProfile(['status' => 'pending', 'verification_status' => 'in_progress']);

        $byDocs = $this->makeProfile(['status' => 'pending', 'verification_status' => 'unverified']);
        ProviderOnboardingDocument::factory()->create(['user_id' => $byDocs->user_id]);

        $noSignal = $this->makeProfile(['status' => 'pending', 'verification_status' => 'unverified']);

        $component = $this->makeLivewireInstance();

        $this->assertTrue($this->invoke($component, 'providerHasOnboardingSignal', [$byStatus]));
        $this->assertTrue($this->invoke($component, 'providerHasOnboardingSignal', [$byDocs]));
        $this->assertFalse($this->invoke($component, 'providerHasOnboardingSignal', [$noSignal]));
    }

    public function test_provider_documents_are_ready_and_documents_collection(): void
    {
        $component = $this->makeLivewireInstance();

        // empty -> false
        $empty = $this->makeProfile(['status' => 'pending', 'verification_status' => 'unverified']);
        $this->assertFalse($this->invoke($component, 'providerDocumentsAreReady', [$empty]));

        // all approved -> true
        $allOk = $this->makeProfile(['status' => 'pending', 'verification_status' => 'unverified']);
        ProviderOnboardingDocument::factory()->approved()->create(['user_id' => $allOk->user_id]);
        $this->assertTrue($this->invoke($component, 'providerDocumentsAreReady', [$allOk]));

        // one pending -> false
        $blocked = $this->makeProfile(['status' => 'pending', 'verification_status' => 'unverified']);
        ProviderOnboardingDocument::factory()->approved()->create(['user_id' => $blocked->user_id]);
        ProviderOnboardingDocument::factory()->create([
            'user_id' => $blocked->user_id,
            'status' => ProviderOnboardingDocument::STATUS_PENDING,
        ]);
        $this->assertFalse($this->invoke($component, 'providerDocumentsAreReady', [$blocked]));

        $docs = $this->invoke($component, 'providerOnboardingDocuments', [$allOk]);
        $this->assertInstanceOf(Collection::class, $docs);
        $this->assertCount(1, $docs);
    }

    public function test_provider_onboarding_counts_aggregates_signal_profiles(): void
    {
        $this->makeProfile(['status' => 'pending', 'verification_status' => 'verified']);
        $this->makeProfile(['status' => 'pending', 'verification_status' => 'ready_for_review']);
        $this->makeProfile(['status' => 'pending', 'verification_status' => 'in_progress']);
        // no-signal profile must be filtered out
        $this->makeProfile(['status' => 'pending', 'verification_status' => 'unverified']);

        $counts = $this->invoke($this->makeLivewireInstance(), 'providerOnboardingCounts');

        $this->assertSame(1, $counts['verified']);
        $this->assertSame(1, $counts['ready']);
        $this->assertSame(1, $counts['in_progress']);
    }

    public function test_refresh_entry_points_repopulate_counts(): void
    {
        $this->makeProfile(['status' => 'suspended', 'verification_status' => 'unverified', 'onboarding_step' => 3]);

        $component = $this->makeLivewireInstance();

        // Each of these protected/private entry points should run without error
        // and leave the counts array populated.
        $this->invoke($component, 'refreshOnboardingCounts');
        $this->assertIsArray($component->counts);

        $this->invoke($component, 'loadCounts');
        $this->assertArrayHasKey('in_progress', $component->counts);

        $this->invoke($component, 'refreshProviderOnboardingCounts');
        $this->assertArrayHasKey('verified', $component->counts);
    }
}
