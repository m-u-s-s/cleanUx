<?php

namespace Tests\Feature\Quality;

use App\Livewire\Admin\Quality\QualityCenter;
use App\Models\MissionQualityInspection;
use App\Models\QualityChecklist;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class QualityCenterCoverageBatch12Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->adminComplet()->create();
    }

    private function checklist(string $phase = 'post'): QualityChecklist
    {
        return QualityChecklist::create([
            'code' => 'CL-'.Str::random(8),
            'name' => 'Checklist '.$phase,
            'phase' => $phase,
            'is_active' => true,
            'version' => 1,
        ]);
    }

    private function inspection(array $attrs = []): MissionQualityInspection
    {
        return MissionQualityInspection::create(array_merge([
            'mission_id' => 1,
            'checklist_id' => $this->checklist()->id,
            'phase' => 'post',
            'status' => MissionQualityInspection::STATUS_SUBMITTED,
            'score_max' => 100,
            'idempotency_key' => 'k-'.Str::random(12),
            'metadata' => [],
        ], $attrs));
    }

    public function test_pending_tab_renders_with_kpis(): void
    {
        $this->inspection([
            'status' => MissionQualityInspection::STATUS_SUBMITTED,
            'phase' => 'post',
            'submitted_at' => now(),
            'score_calculated' => 80,
            'score_max' => 100,
        ]);

        Livewire::actingAs($this->admin())
            ->test(QualityCenter::class)
            ->assertOk()
            ->assertSet('tab', 'pending');
    }

    public function test_filter_phase_on_pending_tab(): void
    {
        $this->inspection([
            'status' => MissionQualityInspection::STATUS_SUBMITTED,
            'phase' => 'pre',
            'submitted_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(QualityCenter::class)
            ->set('filterPhase', 'pre')
            ->assertOk()
            ->set('filterPhase', 'during')
            ->assertOk();
    }

    public function test_disputes_tab_renders(): void
    {
        $this->inspection([
            'status' => MissionQualityInspection::STATUS_DISPUTED,
            'disputed_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(QualityCenter::class)
            ->set('tab', 'disputes')
            ->assertOk();
    }

    public function test_history_tab_renders(): void
    {
        $this->inspection([
            'status' => MissionQualityInspection::STATUS_VALIDATED_ADMIN,
            'validated_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(QualityCenter::class)
            ->set('tab', 'history')
            ->assertOk();
    }

    public function test_checklists_tab_renders(): void
    {
        $this->checklist('during');

        Livewire::actingAs($this->admin())
            ->test(QualityCenter::class)
            ->set('tab', 'checklists')
            ->assertOk();
    }

    public function test_validate_action_validates_submitted_inspection(): void
    {
        $insp = $this->inspection([
            'status' => MissionQualityInspection::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(QualityCenter::class)
            ->call('validate_', $insp->id)
            ->assertDispatched('toast');

        $this->assertSame(
            MissionQualityInspection::STATUS_VALIDATED_ADMIN,
            $insp->fresh()->status,
        );
    }

    public function test_validate_action_error_path_on_invalid_status(): void
    {
        $insp = $this->inspection([
            'status' => MissionQualityInspection::STATUS_REJECTED,
        ]);

        Livewire::actingAs($this->admin())
            ->test(QualityCenter::class)
            ->call('validate_', $insp->id)
            ->assertDispatched('toast');

        $this->assertSame(
            MissionQualityInspection::STATUS_REJECTED,
            $insp->fresh()->status,
        );
    }

    public function test_reject_action_rejects_inspection(): void
    {
        $insp = $this->inspection([
            'status' => MissionQualityInspection::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        Livewire::actingAs($this->admin())
            ->test(QualityCenter::class)
            ->call('reject', $insp->id)
            ->assertDispatched('toast');

        $this->assertSame(
            MissionQualityInspection::STATUS_REJECTED,
            $insp->fresh()->status,
        );
    }
}
