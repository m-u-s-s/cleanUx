<?php

namespace Tests\Feature\Missions;

use App\Models\Mission;
use App\Models\MissionChecklist;
use App\Models\MissionIncident;
use App\Models\MissionMedia;
use App\Models\MissionQualityReview;
use App\Models\MissionReport;
use App\Models\User;
use App\Services\Missions\MissionQualityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MissionQualityServiceCoverageTest extends TestCase
{
    use RefreshDatabase;

    private function service(): MissionQualityService
    {
        return app(MissionQualityService::class);
    }

    // ──────────────────────────────────────────────
    // reportIncident
    // ──────────────────────────────────────────────

    public function test_report_incident_creates_incident_refreshes_quality_and_logs_event(): void
    {
        $employee = User::factory()->employe()->create();
        $mission = Mission::factory()->completed()->create();

        $incident = $this->service()->reportIncident($mission, $employee, [
            'incident_type' => 'equipment',
            'severity' => 'high',
            'title' => 'Broken vacuum',
            'description' => 'The vacuum stopped working',
            'meta' => ['room' => 'kitchen'],
        ]);

        $this->assertSame('open', $incident->status);
        $this->assertSame('high', $incident->severity);
        $this->assertSame('equipment', $incident->incident_type);
        $this->assertSame($employee->id, $incident->reported_by_user_id);
        $this->assertTrue($incident->client_visible);
        $this->assertSame(['room' => 'kitchen'], $incident->meta);

        // Quality was recomputed (open high incident penalty = 18).
        $fresh = $mission->fresh();
        $this->assertNotNull($fresh->quality_score);
        $this->assertSame(18, $fresh->quality_summary['incident_penalty']);

        // A report was generated and an event logged.
        $this->assertDatabaseHas('mission_reports', ['mission_id' => $mission->id]);
        $this->assertDatabaseHas('mission_events', [
            'mission_id' => $mission->id,
            'event_type' => 'incident_reported',
        ]);
    }

    public function test_report_incident_applies_defaults_when_optional_data_missing(): void
    {
        $employee = User::factory()->employe()->create();
        $mission = Mission::factory()->completed()->create();

        $incident = $this->service()->reportIncident($mission, $employee, [
            'title' => 'Minimal incident',
        ]);

        $this->assertSame('general', $incident->incident_type);
        $this->assertSame('medium', $incident->severity);
        $this->assertNull($incident->description);
        $this->assertTrue($incident->client_visible);
    }

    // ──────────────────────────────────────────────
    // submitClientFinalValidation
    // ──────────────────────────────────────────────

    public function test_submit_client_final_validation_satisfied_path(): void
    {
        $client = User::factory()->client()->create();
        $mission = Mission::factory()->completed()->create();

        $review = $this->service()->submitClientFinalValidation($mission, $client, true, 'Great job');

        $this->assertSame('satisfied', $review->final_status);
        $this->assertSame(90, $review->score);
        $this->assertSame('client', $review->reviewer_role);
        $this->assertSame('Great job', $review->comment);

        $fresh = $mission->fresh();
        $this->assertSame('satisfied', $fresh->client_final_status);
        $this->assertNotNull($fresh->client_final_validated_at);

        // No incident / client action created on the satisfied path.
        $this->assertDatabaseMissing('mission_incidents', ['mission_id' => $mission->id]);
        $this->assertDatabaseMissing('mission_client_actions', ['mission_id' => $mission->id]);

        $this->assertDatabaseHas('mission_events', [
            'mission_id' => $mission->id,
            'event_type' => 'client_final_validation',
        ]);
    }

    public function test_submit_client_final_validation_problem_path_creates_action_and_incident(): void
    {
        $client = User::factory()->client()->create();
        $mission = Mission::factory()->completed()->create();

        $review = $this->service()->submitClientFinalValidation($mission, $client, false, 'Floor still dirty');

        $this->assertSame('problem_reported', $review->final_status);
        $this->assertSame(45, $review->score);
        $this->assertSame(50, $review->cleanliness_score);

        $this->assertSame('problem_reported', $mission->fresh()->client_final_status);

        $this->assertDatabaseHas('mission_client_actions', [
            'mission_id' => $mission->id,
            'client_user_id' => $client->id,
            'action_type' => 'issue_reported',
            'status' => 'submitted',
        ]);

        $this->assertDatabaseHas('mission_incidents', [
            'mission_id' => $mission->id,
            'incident_type' => 'client_feedback',
            'severity' => 'medium',
            'status' => 'open',
        ]);
    }

    // ──────────────────────────────────────────────
    // refreshMissionQuality
    // ──────────────────────────────────────────────

    public function test_refresh_mission_quality_computes_deterministic_score_and_summary(): void
    {
        $now = now();
        $mission = Mission::factory()->create([
            'planned_start_at' => $now,
            'actual_start_at' => $now, // delay 0 → punctuality 20
        ]);

        MissionChecklist::factory()->create([
            'mission_id' => $mission->id,
            'completion_rate' => 80, // *0.5 = 40
        ]);

        MissionIncident::query()->create([
            'mission_id' => $mission->id,
            'reported_by_user_id' => null,
            'incident_type' => 'general',
            'severity' => 'critical', // penalty 25
            'status' => 'open',
            'title' => 'Critical',
            'client_visible' => true,
            'reported_at' => $now,
        ]);

        MissionQualityReview::query()->create([
            'mission_id' => $mission->id,
            'reviewer_user_id' => null,
            'reviewer_role' => 'client',
            'final_status' => 'satisfied', // clientScore 30
            'score' => 90,
            'reviewed_at' => $now,
        ]);

        $result = $this->service()->refreshMissionQuality($mission->fresh());

        // 40 + 30 + 20 - 25 = 65 → "good"
        $this->assertSame(65, $result->quality_score);
        $this->assertSame('good', $result->quality_status);
        $this->assertSame(80, $result->quality_summary['checklist_rate']);
        $this->assertSame(25, $result->quality_summary['incident_penalty']);
        $this->assertSame(20, $result->quality_summary['punctuality_score']);
        $this->assertSame('satisfied', $result->quality_summary['client_status']);
    }

    public function test_refresh_mission_quality_critical_status_when_no_data(): void
    {
        // No checklist, no reviews, no incidents, no actual_start_at.
        // checklist 0 + clientScore default 20 + punctuality 12 - 0 = 32 → "critical"
        $mission = Mission::factory()->create([
            'planned_start_at' => null,
            'actual_start_at' => null,
        ]);

        $result = $this->service()->refreshMissionQuality($mission);

        $this->assertSame(32, $result->quality_score);
        $this->assertSame('critical', $result->quality_status);
        $this->assertNull($result->quality_summary['client_status']);
        $this->assertSame(12, $result->quality_summary['punctuality_score']);
    }

    public function test_refresh_mission_quality_problem_review_lowers_client_score(): void
    {
        $mission = Mission::factory()->create([
            'planned_start_at' => null,
            'actual_start_at' => null,
        ]);

        MissionQualityReview::query()->create([
            'mission_id' => $mission->id,
            'reviewer_user_id' => null,
            'reviewer_role' => 'client',
            'final_status' => 'problem_reported', // clientScore 10
            'score' => 45,
            'reviewed_at' => now(),
        ]);

        // 0 + 10 + 12 - 0 = 22 → "critical"
        $result = $this->service()->refreshMissionQuality($mission);

        $this->assertSame(22, $result->quality_score);
        $this->assertSame('problem_reported', $result->quality_summary['client_status']);
    }

    // ──────────────────────────────────────────────
    // generateOrRefreshReport + buildSummary
    // ──────────────────────────────────────────────

    public function test_generate_report_counts_photos_and_summary_with_incident(): void
    {
        $employee = User::factory()->employe()->create();
        $mission = Mission::factory()->completed()->create();

        MissionChecklist::factory()->create([
            'mission_id' => $mission->id,
            'completion_rate' => 70,
        ]);

        MissionMedia::factory()->count(2)->create([
            'mission_id' => $mission->id,
            'media_type' => 'before_photo',
        ]);
        MissionMedia::factory()->create([
            'mission_id' => $mission->id,
            'media_type' => 'after_photo',
        ]);

        MissionIncident::query()->create([
            'mission_id' => $mission->id,
            'reported_by_user_id' => $employee->id,
            'incident_type' => 'general',
            'severity' => 'medium',
            'status' => 'open',
            'title' => 'Issue',
            'client_visible' => true,
            'reported_at' => now(),
        ]);

        $report = $this->service()->generateOrRefreshReport($mission->fresh(), $employee);

        $this->assertSame('generated', $report->status);
        $this->assertSame(2, $report->before_photos_count);
        $this->assertSame(1, $report->after_photos_count);
        $this->assertSame(1, $report->incident_count);
        $this->assertSame(70, $report->checklist_completion_rate);
        $this->assertSame($employee->id, $report->generated_by_user_id);
        $this->assertStringContainsString('1 incident(s)', $report->summary);
        $this->assertStringContainsString('satisfaisante', $report->summary);
        $this->assertSame($mission->id, $report->report_payload['mission_id']);
    }

    public function test_generate_report_summary_without_incident_and_with_problem_review(): void
    {
        $mission = Mission::factory()->completed()->create();

        MissionQualityReview::query()->create([
            'mission_id' => $mission->id,
            'reviewer_user_id' => null,
            'reviewer_role' => 'client',
            'final_status' => 'problem_reported',
            'score' => 45,
            'reviewed_at' => now(),
        ]);

        $report = $this->service()->generateOrRefreshReport($mission->fresh());

        $this->assertSame(0, $report->incident_count);
        $this->assertSame('problem_reported', $report->client_validation);
        $this->assertStringContainsString('sans incident', $report->summary);
        $this->assertStringContainsString('avec réserve client', $report->summary);
        $this->assertNull($report->generated_by_user_id);
    }

    public function test_generate_report_is_idempotent_and_keeps_report_number(): void
    {
        $mission = Mission::factory()->completed()->create();

        $first = $this->service()->generateOrRefreshReport($mission->fresh());
        $number = $first->report_number;
        $this->assertNotEmpty($number);

        $second = $this->service()->generateOrRefreshReport($mission->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame($number, $second->report_number);
        $this->assertSame(1, MissionReport::query()->where('mission_id', $mission->id)->count());
    }
}
