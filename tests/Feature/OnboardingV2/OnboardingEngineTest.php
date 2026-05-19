<?php

namespace Tests\Feature\OnboardingV2;

use App\Models\OnboardingJourney;
use App\Models\OnboardingProgress;
use App\Models\OnboardingStep;
use App\Models\OnboardingStepCompletion;
use App\Models\User;
use App\Services\OnboardingV2\OnboardingEngine;
use Database\Seeders\OnboardingJourneysSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OnboardingEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OnboardingJourneysSeeder::class);
        Config::set('onboarding_v2.enabled', true);
    }

    public function test_start_for_client_initialises_progress_with_pending_steps(): void
    {
        $user = User::factory()->client()->create();

        $progress = app(OnboardingEngine::class)->startFor($user);

        $this->assertInstanceOf(OnboardingProgress::class, $progress);
        $this->assertSame(OnboardingProgress::STATUS_IN_PROGRESS, $progress->status);
        $this->assertSame('client', $progress->journey->role);

        $completions = OnboardingStepCompletion::query()->where('progress_id', $progress->id)->get();
        $this->assertCount(3, $completions);
        foreach ($completions as $c) {
            $this->assertSame(OnboardingStepCompletion::STATUS_PENDING, $c->status);
        }

        $this->assertNotNull($progress->current_step_code);
    }

    public function test_start_for_is_idempotent(): void
    {
        $user = User::factory()->client()->create();
        $svc = app(OnboardingEngine::class);

        $a = $svc->startFor($user);
        $b = $svc->startFor($user);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, OnboardingProgress::count());
    }

    public function test_start_for_uses_provider_journey_for_provider_role(): void
    {
        $user = User::factory()->employe()->create();
        $progress = app(OnboardingEngine::class)->startFor($user);

        $this->assertSame('provider', $progress->journey->role);
        $this->assertSame('provider_default', $progress->journey->code);
    }

    public function test_get_current_step_returns_first_step_with_satisfied_dependencies(): void
    {
        $user = User::factory()->client()->create();
        $progress = app(OnboardingEngine::class)->startFor($user);

        $current = app(OnboardingEngine::class)->getCurrentStep($progress);

        $this->assertNotNull($current);
        $this->assertSame('profile', $current->code);
    }

    public function test_mark_complete_succeeds_when_validator_passes(): void
    {
        $user = User::factory()->client()->create([
            'name' => 'Alice',
            'email' => 'alice@test.com',
            'locale' => 'fr',
        ]);
        $progress = app(OnboardingEngine::class)->startFor($user);
        $profileStep = $progress->journey->steps->firstWhere('code', 'profile');

        $compl = app(OnboardingEngine::class)->markComplete($progress, $profileStep, []);

        $this->assertSame(OnboardingStepCompletion::STATUS_COMPLETED, $compl->status);
        $this->assertNotNull($compl->completed_at);

        $progress->refresh();
        $this->assertGreaterThan(0, (float) $progress->percent_complete);
        // After profile, next presented step is phone_verify (optional, position 2)
        $this->assertSame('phone_verify', $progress->current_step_code);
    }

    public function test_mark_complete_fails_validator_returns_validation_error(): void
    {
        $user = User::factory()->client()->create(['name' => 'A', 'email' => '', 'locale' => '']);
        $progress = app(OnboardingEngine::class)->startFor($user);
        $profileStep = $progress->journey->steps->firstWhere('code', 'profile');

        $this->expectException(ValidationException::class);
        app(OnboardingEngine::class)->markComplete($progress, $profileStep, []);
    }

    public function test_mark_skip_only_works_on_skippable_steps(): void
    {
        $user = User::factory()->client()->create(['name' => 'A', 'email' => 'a@x.com', 'locale' => 'fr']);
        $progress = app(OnboardingEngine::class)->startFor($user);
        $skippable = $progress->journey->steps->firstWhere('code', 'phone_verify');
        $nonSkippable = $progress->journey->steps->firstWhere('code', 'profile');

        $compl = app(OnboardingEngine::class)->markSkip($progress, $skippable, $user, 'pas envie');
        $this->assertSame(OnboardingStepCompletion::STATUS_SKIPPED, $compl->status);

        $this->expectException(ValidationException::class);
        app(OnboardingEngine::class)->markSkip($progress, $nonSkippable, $user, 'force');
    }

    public function test_journey_marked_completed_when_all_required_steps_done(): void
    {
        $user = User::factory()->client()->create(['name' => 'A', 'email' => 'a@x.com', 'locale' => 'fr']);
        $svc = app(OnboardingEngine::class);
        $progress = $svc->startFor($user);

        // Profile + TOS required (phone_verify optional)
        $profileStep = $progress->journey->steps->firstWhere('code', 'profile');
        $tosStep = $progress->journey->steps->firstWhere('code', 'tos');

        $svc->markComplete($progress, $profileStep, []);
        $svc->markComplete($progress, $tosStep, ['terms_accepted_version' => '2026-05-v1']);

        $progress->refresh();
        $this->assertSame(OnboardingProgress::STATUS_COMPLETED, $progress->status);
        $this->assertNotNull($progress->completed_at);
        $this->assertEqualsWithDelta(100.0, (float) $progress->percent_complete, 0.01);
    }

    public function test_step_with_unmet_dependency_is_not_current(): void
    {
        $user = User::factory()->employe()->create([
            'name' => 'A', 'email' => 'p@x.com', 'phone' => '+32412345678', 'locale' => 'fr',
        ]);
        $svc = app(OnboardingEngine::class);
        $progress = $svc->startFor($user);

        // KYC depends on TOS — should not be current until TOS done
        $kycStep = $progress->journey->steps->firstWhere('code', 'kyc');
        $current = $svc->getCurrentStep($progress);

        $this->assertNotSame($kycStep->code, $current->code);
        $this->assertSame('profile', $current->code);
    }

    public function test_failed_completion_increments_attempt_count(): void
    {
        $user = User::factory()->client()->create(['name' => '', 'email' => '', 'locale' => '']);
        $progress = app(OnboardingEngine::class)->startFor($user);
        $profileStep = $progress->journey->steps->firstWhere('code', 'profile');

        try { app(OnboardingEngine::class)->markComplete($progress, $profileStep, []); } catch (\Throwable $e) {}
        try { app(OnboardingEngine::class)->markComplete($progress, $profileStep, []); } catch (\Throwable $e) {}

        $compl = OnboardingStepCompletion::query()
            ->where('progress_id', $progress->id)
            ->where('step_id', $profileStep->id)
            ->first();
        $this->assertSame(2, (int) $compl->attempt_count);
        $this->assertSame(OnboardingStepCompletion::STATUS_FAILED, $compl->status);
    }
}
