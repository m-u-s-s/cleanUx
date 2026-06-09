<?php

namespace Tests\Feature\Livewire\Admin\Risk;

use App\Livewire\Admin\Risk\RiskCenter;
use App\Models\RiskEvaluation;
use App\Models\RiskHold;
use App\Models\User;
use Database\Factories\RiskEvaluationFactory;
use Database\Factories\RiskHoldFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RiskCenterCoverageBatch17Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    public function test_renders_pending_tab_with_kpis_and_active_holds(): void
    {
        RiskEvaluationFactory::new()->blocked()->create(['evaluated_at' => now()->subHours(2)]);
        $eval = RiskEvaluationFactory::new()->review()->create(['evaluated_at' => now()->subHours(3)]);
        RiskHoldFactory::new()->create([
            'status' => RiskHold::STATUS_ACTIVE,
            'risk_evaluation_id' => $eval->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(RiskCenter::class)
            ->assertOk()
            ->assertSet('tab', 'pending')
            ->assertViewHas('kpis')
            ->assertViewHas('items');
    }

    public function test_renders_history_tab_with_filters(): void
    {
        $user = User::factory()->client()->create(['name' => 'Bruno Target', 'email' => 'bruno@example.test']);
        RiskEvaluationFactory::new()->blocked()->create([
            'user_id' => $user->id,
            'context' => RiskEvaluation::CONTEXT_PAYMENT_ATTEMPT,
        ]);
        RiskEvaluationFactory::new()->create([
            'context' => RiskEvaluation::CONTEXT_LOGIN,
        ]);

        Livewire::actingAs($this->admin)
            ->test(RiskCenter::class)
            ->set('tab', 'history')
            ->set('filterDecision', RiskEvaluation::DECISION_BLOCK)
            ->set('filterContext', RiskEvaluation::CONTEXT_PAYMENT_ATTEMPT)
            ->set('search', 'bruno')
            ->assertOk()
            ->assertSet('filterDecision', RiskEvaluation::DECISION_BLOCK)
            ->assertSet('search', 'bruno');
    }

    public function test_approve_releases_hold_and_dispatches_toast(): void
    {
        $eval = RiskEvaluationFactory::new()->blocked()->create();
        $hold = RiskHoldFactory::new()->create([
            'status' => RiskHold::STATUS_ACTIVE,
            'risk_evaluation_id' => $eval->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(RiskCenter::class)
            ->call('approve', $hold->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('risk_holds', [
            'id' => $hold->id,
            'status' => RiskHold::STATUS_REVIEWED_APPROVED,
            'reviewed_by_user_id' => $this->admin->id,
        ]);
    }

    public function test_reject_blocks_hold_and_dispatches_toast(): void
    {
        $eval = RiskEvaluationFactory::new()->blocked()->create();
        $hold = RiskHoldFactory::new()->create([
            'status' => RiskHold::STATUS_ACTIVE,
            'risk_evaluation_id' => $eval->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(RiskCenter::class)
            ->call('reject', $hold->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('risk_holds', [
            'id' => $hold->id,
            'status' => RiskHold::STATUS_REVIEWED_REJECTED,
            'reviewed_by_user_id' => $this->admin->id,
        ]);
    }

    public function test_approve_missing_hold_throws_not_found(): void
    {
        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($this->admin)
            ->test(RiskCenter::class)
            ->call('approve', 999999);
    }
}
