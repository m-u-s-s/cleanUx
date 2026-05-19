<?php

namespace Tests\Feature\Disputes;

use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\User;
use App\Services\Disputes\DisputeService;
use App\Services\Disputes\DisputeSlaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisputeSlaTest extends TestCase
{
    use RefreshDatabase;

    public function test_urgent_priority_yields_shorter_sla(): void
    {
        $sla = app(DisputeSlaService::class);

        $urgentHours = $sla->hoursFor(ComplaintCase::PRIORITY_URGENT, ComplaintCase::SEVERITY_MEDIUM);
        $lowHours = $sla->hoursFor(ComplaintCase::PRIORITY_LOW, ComplaintCase::SEVERITY_MEDIUM);

        $this->assertLessThan($lowHours, $urgentHours);
    }

    public function test_critical_severity_halves_sla(): void
    {
        $sla = app(DisputeSlaService::class);

        $medium = $sla->hoursFor(ComplaintCase::PRIORITY_NORMAL, ComplaintCase::SEVERITY_MEDIUM);
        $critical = $sla->hoursFor(ComplaintCase::PRIORITY_NORMAL, ComplaintCase::SEVERITY_CRITICAL);

        $this->assertLessThanOrEqual($medium / 2 + 1, $critical);
    }

    public function test_find_overdue_for_escalation_picks_correct_cases(): void
    {
        $client = User::factory()->client()->create();
        $service = app(DisputeService::class);

        $case = $service->open($client, [
            'subject' => 'Test SLA',
            'description' => 'Description suffisante',
            'category' => 'quality',
            'priority' => 'urgent',
        ]);

        // Simuler le passage du temps : due_at dans le passé
        $case->update(['due_at' => now()->subHours(2)]);

        $overdue = app(DisputeSlaService::class)->findOverdueForEscalation();

        $this->assertTrue($overdue->contains('id', $case->id));
    }

    public function test_resolved_dispute_is_not_considered_overdue(): void
    {
        $client = User::factory()->client()->create();
        $service = app(DisputeService::class);

        $case = $service->open($client, [
            'subject' => 'Test',
            'description' => 'Description suffisante',
            'category' => 'quality',
        ]);

        $case->update([
            'due_at' => now()->subDay(),
            'status' => ComplaintCase::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);

        $overdue = app(DisputeSlaService::class)->findOverdueForEscalation();

        $this->assertFalse($overdue->contains('id', $case->id));
    }
}
