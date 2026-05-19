<?php

namespace Tests\Feature\Risk;

use App\Models\RiskEvaluation;
use App\Models\RiskHold;
use App\Models\User;
use App\Services\Risk\RiskContext;
use App\Services\Risk\RiskScoringEngine;
use Database\Seeders\RiskRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RiskApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RiskRulesSeeder::class);
    }

    protected function createHold(User $user): RiskHold
    {
        $context = new RiskContext(
            contextType: RiskEvaluation::CONTEXT_PAYMENT_ATTEMPT,
            user: $user,
            extra: ['decline_count_last_24h' => 5],
        );
        app(RiskScoringEngine::class)->evaluate($context);
        return RiskHold::first();
    }

    public function test_evaluations_endpoint_requires_auth(): void
    {
        $this->getJson('/api/admin/risk/evaluations')->assertStatus(401);
    }

    public function test_evaluations_endpoint_returns_list_for_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();

        $context = new RiskContext(
            contextType: RiskEvaluation::CONTEXT_PAYMENT_ATTEMPT,
            user: $client,
            extra: ['decline_count_last_24h' => 5],
        );
        app(RiskScoringEngine::class)->evaluate($context);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/risk/evaluations');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_holds_endpoint_returns_only_active_by_default(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();
        $this->createHold($client);

        Sanctum::actingAs($admin);
        $response = $this->getJson('/api/admin/risk/holds');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('active', $data[0]['status']);
    }

    public function test_review_hold_approved(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();
        $hold = $this->createHold($client);

        Sanctum::actingAs($admin);
        $response = $this->postJson("/api/admin/risk/holds/{$hold->id}/review", [
            'decision' => 'approved',
            'notes' => 'Manually verified',
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'hold' => ['status' => 'reviewed_approved'],
        ]);

        $hold->refresh();
        $this->assertSame($admin->id, $hold->reviewed_by_user_id);
    }

    public function test_review_hold_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();
        $hold = $this->createHold($client);

        Sanctum::actingAs($admin);
        $response = $this->postJson("/api/admin/risk/holds/{$hold->id}/review", [
            'decision' => 'rejected',
        ]);

        $response->assertOk();
        $hold->refresh();
        $this->assertSame(RiskHold::STATUS_REVIEWED_REJECTED, $hold->status);
    }

    public function test_review_hold_validates_decision_enum(): void
    {
        $admin = User::factory()->admin()->create();
        $client = User::factory()->client()->create();
        $hold = $this->createHold($client);

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/risk/holds/{$hold->id}/review", [
            'decision' => 'maybe',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['decision']);
    }
}
