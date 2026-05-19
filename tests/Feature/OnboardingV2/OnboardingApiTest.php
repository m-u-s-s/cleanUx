<?php

namespace Tests\Feature\OnboardingV2;

use App\Models\OnboardingProgress;
use App\Models\User;
use App\Services\OnboardingV2\OnboardingEngine;
use Database\Seeders\OnboardingJourneysSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OnboardingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(OnboardingJourneysSeeder::class);
    }

    public function test_me_endpoint_requires_auth(): void
    {
        $this->getJson('/api/v2/onboarding/me')->assertStatus(401);
    }

    public function test_me_endpoint_auto_starts_journey(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v2/onboarding/me');

        $response->assertOk();
        $response->assertJsonStructure(['progress', 'journey', 'current_step', 'steps']);
        $this->assertSame('client', $response->json('journey.role'));
        $this->assertSame(1, OnboardingProgress::count());
    }

    public function test_complete_step_endpoint_advances_journey(): void
    {
        $user = User::factory()->client()->create([
            'name' => 'Alice', 'email' => 'alice@x.com', 'locale' => 'fr',
        ]);
        Sanctum::actingAs($user);

        $progress = app(OnboardingEngine::class)->startFor($user);
        $profileStep = $progress->journey->steps->firstWhere('code', 'profile');

        $response = $this->postJson("/api/v2/onboarding/steps/{$profileStep->id}/complete", [
            'payload' => [],
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        // After profile, next is phone_verify (optional position 2)
        $this->assertSame('phone_verify', $response->json('progress.current_step_code'));
    }

    public function test_complete_step_returns_validation_errors_when_validator_fails(): void
    {
        $user = User::factory()->client()->create(['name' => '', 'email' => '', 'locale' => '']);
        Sanctum::actingAs($user);

        $progress = app(OnboardingEngine::class)->startFor($user);
        $profileStep = $progress->journey->steps->firstWhere('code', 'profile');

        $this->postJson("/api/v2/onboarding/steps/{$profileStep->id}/complete", ['payload' => []])
            ->assertStatus(422);
    }

    public function test_skip_step_endpoint_works_for_skippable_steps(): void
    {
        $user = User::factory()->client()->create(['name' => 'A', 'email' => 'a@x.com', 'locale' => 'fr']);
        Sanctum::actingAs($user);

        $progress = app(OnboardingEngine::class)->startFor($user);
        $skippable = $progress->journey->steps->firstWhere('code', 'phone_verify');

        $response = $this->postJson("/api/v2/onboarding/steps/{$skippable->id}/skip", [
            'reason' => 'no phone',
        ]);

        $response->assertOk();
        $this->assertSame('skipped', $response->json('completion.status'));
    }

    public function test_skip_step_rejects_non_skippable_steps(): void
    {
        $user = User::factory()->client()->create();
        Sanctum::actingAs($user);

        $progress = app(OnboardingEngine::class)->startFor($user);
        $required = $progress->journey->steps->firstWhere('code', 'profile');

        $this->postJson("/api/v2/onboarding/steps/{$required->id}/skip", [])->assertStatus(422);
    }

    public function test_admin_index_returns_all_progress(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();
        app(OnboardingEngine::class)->startFor($client);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/onboarding-v2/progress');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }
}
