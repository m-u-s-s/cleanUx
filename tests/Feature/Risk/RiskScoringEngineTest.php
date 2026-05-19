<?php

namespace Tests\Feature\Risk;

use App\Models\RiskEvaluation;
use App\Models\RiskHold;
use App\Models\User;
use App\Services\Risk\RiskContext;
use App\Services\Risk\RiskScoringEngine;
use Database\Seeders\RiskRulesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class RiskScoringEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RiskRulesSeeder::class);
        Config::set('risk.enabled', true);
        Config::set('risk.thresholds.review', 50);
        Config::set('risk.thresholds.block', 100);
        Config::set('risk.hold_duration_minutes', 60);
    }

    public function test_evaluate_with_no_hits_returns_allow(): void
    {
        $user = User::factory()->client()->create(['created_at' => now()->subDays(30)]);

        $context = new RiskContext(
            contextType: RiskEvaluation::CONTEXT_LOGIN,
            user: $user,
        );

        $eval = app(RiskScoringEngine::class)->evaluate($context);

        $this->assertSame(RiskEvaluation::DECISION_ALLOW, $eval->decision);
        $this->assertSame(0, (int) $eval->score);
        $this->assertSame(0, RiskHold::count());
    }

    public function test_evaluate_with_payment_decline_burst_triggers_review_or_block(): void
    {
        $user = User::factory()->client()->create(['created_at' => now()->subDays(30)]);

        $context = new RiskContext(
            contextType: RiskEvaluation::CONTEXT_PAYMENT_ATTEMPT,
            user: $user,
            extra: ['decline_count_last_24h' => 5],
        );

        $eval = app(RiskScoringEngine::class)->evaluate($context);

        $this->assertNotSame(RiskEvaluation::DECISION_ALLOW, $eval->decision);
        $this->assertGreaterThan(0, $eval->score);
        $this->assertSame(1, RiskHold::count());

        $hold = RiskHold::first();
        $this->assertSame(RiskHold::STATUS_ACTIVE, $hold->status);
        $this->assertSame($user->id, $hold->user_id);
        $this->assertTrue($hold->expires_at->isFuture());
    }

    public function test_evaluate_with_very_new_account_adds_age_score(): void
    {
        $user = User::factory()->client()->create(['created_at' => now()->subHour()]);

        $context = new RiskContext(
            contextType: RiskEvaluation::CONTEXT_BOOKING_CREATE,
            user: $user,
        );

        $eval = app(RiskScoringEngine::class)->evaluate($context);

        $this->assertGreaterThan(0, $eval->score);
        $codes = collect($eval->triggered_rules)->pluck('code')->all();
        $this->assertContains('account.very_new', $codes);
    }

    public function test_evaluate_bypass_role_admin_always_allows(): void
    {
        $admin = User::factory()->admin()->create(['created_at' => now()->subHour()]);

        $context = new RiskContext(
            contextType: RiskEvaluation::CONTEXT_PAYMENT_ATTEMPT,
            user: $admin,
            extra: ['decline_count_last_24h' => 100],  // would normally trigger
        );

        $eval = app(RiskScoringEngine::class)->evaluate($context);

        $this->assertSame(RiskEvaluation::DECISION_ALLOW, $eval->decision);
        $this->assertSame(0, $eval->score);
    }

    public function test_evaluate_is_idempotent_with_same_key(): void
    {
        $user = User::factory()->client()->create();
        $context = new RiskContext(
            contextType: RiskEvaluation::CONTEXT_LOGIN,
            user: $user,
        );

        $a = app(RiskScoringEngine::class)->evaluate($context, idempotencyKey: 'k-001');
        $b = app(RiskScoringEngine::class)->evaluate($context, idempotencyKey: 'k-001');

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, RiskEvaluation::count());
    }

    public function test_evaluate_skipped_when_engine_disabled(): void
    {
        Config::set('risk.enabled', false);

        $user = User::factory()->client()->create(['created_at' => now()->subHour()]);
        $context = new RiskContext(
            contextType: RiskEvaluation::CONTEXT_PAYMENT_ATTEMPT,
            user: $user,
            extra: ['decline_count_last_24h' => 100],
        );

        $eval = app(RiskScoringEngine::class)->evaluate($context);

        $this->assertSame(RiskEvaluation::DECISION_ALLOW, $eval->decision);
        $this->assertSame(0, RiskHold::count());
    }

    public function test_review_hold_approved_changes_status_and_records_reviewer(): void
    {
        $user = User::factory()->client()->create();
        $admin = User::factory()->admin()->create();

        $context = new RiskContext(
            contextType: RiskEvaluation::CONTEXT_PAYMENT_ATTEMPT,
            user: $user,
            extra: ['decline_count_last_24h' => 5],
        );
        $eval = app(RiskScoringEngine::class)->evaluate($context);
        $hold = RiskHold::first();
        $this->assertSame(RiskHold::STATUS_ACTIVE, $hold->status);

        $reviewed = app(RiskScoringEngine::class)->reviewHold($hold, $admin, 'approved', 'False positive.');

        $this->assertSame(RiskHold::STATUS_REVIEWED_APPROVED, $reviewed->status);
        $this->assertSame($admin->id, $reviewed->reviewed_by_user_id);
        $this->assertNotNull($reviewed->reviewed_at);
        $this->assertSame('False positive.', $reviewed->review_notes);
    }

    public function test_review_hold_rejected_blocks_user(): void
    {
        $user = User::factory()->client()->create();
        $admin = User::factory()->admin()->create();

        $context = new RiskContext(
            contextType: RiskEvaluation::CONTEXT_PAYMENT_ATTEMPT,
            user: $user,
            extra: ['decline_count_last_24h' => 5],
        );
        app(RiskScoringEngine::class)->evaluate($context);
        $hold = RiskHold::first();

        $reviewed = app(RiskScoringEngine::class)->reviewHold($hold, $admin, 'rejected');

        $this->assertSame(RiskHold::STATUS_REVIEWED_REJECTED, $reviewed->status);
    }

    public function test_cleanup_expired_holds_marks_them_expired(): void
    {
        $user = User::factory()->client()->create();

        RiskHold::create([
            'user_id' => $user->id,
            'status' => RiskHold::STATUS_ACTIVE,
            'reason' => 'test',
            'expires_at' => now()->subMinute(),
        ]);

        $count = app(RiskScoringEngine::class)->cleanupExpiredHolds();

        $this->assertSame(1, $count);
        $this->assertSame(RiskHold::STATUS_EXPIRED, RiskHold::first()->status);
    }
}
