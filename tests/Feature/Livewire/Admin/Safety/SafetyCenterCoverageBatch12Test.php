<?php

namespace Tests\Feature\Livewire\Admin\Safety;

use App\Livewire\Admin\Safety\SafetyCenter;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SafetyCenterCoverageBatch12Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $reporter;

    protected User $reported;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->reporter = User::factory()->client()->create([
            'name' => 'Reporter One',
            'email' => 'reporter@example.test',
        ]);
        $this->reported = User::factory()->employe()->create([
            'name' => 'Reported Two',
            'email' => 'reported@example.test',
        ]);
    }

    private function seedReport(array $overrides = []): UserReport
    {
        return UserReport::query()->create(array_merge([
            'code' => UserReport::generateCode(),
            'reporter_user_id' => $this->reporter->id,
            'reported_user_id' => $this->reported->id,
            'category' => 'harassment',
            'description' => 'A detailed enough description here.',
            'evidence' => [],
            'status' => UserReport::STATUS_PENDING,
        ], $overrides));
    }

    private function seedBlock(array $overrides = []): UserBlock
    {
        return UserBlock::query()->create(array_merge([
            'blocker_user_id' => $this->reporter->id,
            'blocked_user_id' => $this->reported->id,
            'reason' => 'spam',
        ], $overrides));
    }

    public function test_mount_renders_with_default_data(): void
    {
        $this->seedReport();
        $this->seedBlock();

        Livewire::actingAs($this->admin)
            ->test(SafetyCenter::class)
            ->assertOk()
            ->assertSet('tab', 'reports')
            ->assertViewHas('reports')
            ->assertViewHas('blocks')
            ->assertViewHas('stats')
            ->assertViewHas('categories')
            ->assertViewHas('selectedReport', null);
    }

    public function test_set_tab_changes_tab_and_resets_page(): void
    {
        Livewire::actingAs($this->admin)
            ->test(SafetyCenter::class)
            ->call('setTab', 'blocks')
            ->assertSet('tab', 'blocks');
    }

    public function test_open_and_close_report(): void
    {
        $report = $this->seedReport();

        $component = Livewire::actingAs($this->admin)
            ->test(SafetyCenter::class)
            ->call('openReport', $report->id)
            ->assertSet('selectedReportId', $report->id)
            ->assertSet('resolutionNotes', '')
            ->assertViewHas('selectedReport', fn ($r) => $r !== null && $r->id === $report->id);

        $component->call('closeReport')
            ->assertSet('selectedReportId', null)
            ->assertViewHas('selectedReport', null);
    }

    public function test_resolve_updates_report_and_closes(): void
    {
        $report = $this->seedReport();

        Livewire::actingAs($this->admin)
            ->test(SafetyCenter::class)
            ->set('selectedReportId', $report->id)
            ->set('resolutionNotes', 'Action taken after review.')
            ->call('resolve', UserReport::STATUS_RESOLVED_ACTION)
            ->assertSet('selectedReportId', null)
            ->assertDispatched('toast');

        $report->refresh();
        $this->assertSame(UserReport::STATUS_RESOLVED_ACTION, $report->status);
        $this->assertSame($this->admin->id, $report->reviewed_by_admin_id);
        $this->assertSame('Action taken after review.', $report->admin_notes);
        $this->assertNotNull($report->reviewed_at);
    }

    public function test_resolve_with_missing_report_is_noop(): void
    {
        Livewire::actingAs($this->admin)
            ->test(SafetyCenter::class)
            ->set('selectedReportId', 999999)
            ->call('resolve', UserReport::STATUS_DISMISSED)
            ->assertNotDispatched('toast');
    }

    public function test_resolve_with_invalid_resolution_dispatches_error(): void
    {
        $report = $this->seedReport();

        Livewire::actingAs($this->admin)
            ->test(SafetyCenter::class)
            ->set('selectedReportId', $report->id)
            ->call('resolve', 'totally_invalid_status')
            ->assertSet('selectedReportId', $report->id)
            ->assertDispatched('toast');

        $report->refresh();
        $this->assertSame(UserReport::STATUS_PENDING, $report->status);
    }

    public function test_status_and_category_filters(): void
    {
        $match = $this->seedReport([
            'status' => UserReport::STATUS_UNDER_REVIEW,
            'category' => 'fraud',
        ]);
        $miss = $this->seedReport([
            'status' => UserReport::STATUS_PENDING,
            'category' => 'spam',
        ]);

        Livewire::actingAs($this->admin)
            ->test(SafetyCenter::class)
            ->set('statusFilter', UserReport::STATUS_UNDER_REVIEW)
            ->set('categoryFilter', 'fraud')
            ->assertViewHas('reports', fn ($reports) => $reports->pluck('id')->contains($match->id)
                && ! $reports->pluck('id')->contains($miss->id));
    }

    public function test_search_matches_code_and_reporter_email(): void
    {
        $byCode = $this->seedReport(['code' => 'rpt_uniquesearchtoken01']);
        $byEmail = $this->seedReport();
        $miss = $this->seedReport([
            'code' => 'rpt_other000000000001',
            'reporter_user_id' => User::factory()->client()->create(['email' => 'nope@example.test'])->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(SafetyCenter::class)
            ->set('search', 'uniquesearchtoken01')
            ->assertViewHas('reports', fn ($reports) => $reports->pluck('id')->contains($byCode->id)
                && ! $reports->pluck('id')->contains($miss->id));

        Livewire::actingAs($this->admin)
            ->test(SafetyCenter::class)
            ->set('search', 'reporter@example.test')
            ->assertViewHas('reports', fn ($reports) => $reports->pluck('id')->contains($byEmail->id)
                && ! $reports->pluck('id')->contains($miss->id));
    }

    public function test_stats_reflect_counts(): void
    {
        $this->seedReport(['status' => UserReport::STATUS_PENDING]);
        $this->seedReport(['status' => UserReport::STATUS_UNDER_REVIEW, 'category' => 'fraud']);
        $this->seedReport(['status' => UserReport::STATUS_RESOLVED_ACTION, 'category' => 'spam']);
        $this->seedBlock();

        Livewire::actingAs($this->admin)
            ->test(SafetyCenter::class)
            ->assertViewHas('stats', function ($stats) {
                return $stats['pending'] === 1
                    && $stats['under_review'] === 1
                    && $stats['resolved_action'] === 1
                    && $stats['total_blocks'] === 1;
            });
    }
}
