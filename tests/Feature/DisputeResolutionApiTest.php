<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ComplaintCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DisputeResolutionApiTest extends TestCase
{
    use RefreshDatabase;

    // ─── Dispute resolution ───────────────────────────────────────────────────

    public function test_admin_can_resolve_dispute_as_no_action(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create(['role' => 'client']);
        $case = ComplaintCase::create([
            'reference' => 'CASE-001',
            'status' => ComplaintCase::STATUS_OPEN,
            'client_id' => $client->id,
            'category' => ComplaintCase::CATEGORY_OTHER,
            'subject' => 'Test dispute',
            'description' => 'Test',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/disputes/{$case->id}/resolve", [
            'resolution_type' => 'no_action',
            'explanation' => 'No action required.',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertEquals(ComplaintCase::STATUS_RESOLVED, $case->fresh()->status);
    }

    public function test_admin_can_resolve_dispute_as_dismissed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create(['role' => 'client']);
        $case = ComplaintCase::create([
            'reference' => 'CASE-002',
            'status' => ComplaintCase::STATUS_OPEN,
            'client_id' => $client->id,
            'category' => ComplaintCase::CATEGORY_QUALITY,
            'subject' => 'Quality issue',
            'description' => 'Test',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/disputes/{$case->id}/resolve", [
            'resolution_type' => 'dismissed',
            'explanation' => 'Claim not substantiated.',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertEquals(ComplaintCase::STATUS_RESOLVED, $case->fresh()->status);
        $this->assertEquals('dismissed', $case->fresh()->resolution_category);
    }

    public function test_admin_can_escalate_dispute(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create(['role' => 'client']);
        $case = ComplaintCase::create([
            'reference' => 'CASE-003',
            'status' => ComplaintCase::STATUS_OPEN,
            'client_id' => $client->id,
            'category' => ComplaintCase::CATEGORY_SAFETY,
            'subject' => 'Safety concern',
            'description' => 'Test',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/disputes/{$case->id}/escalate", [
            'reason' => 'Requires senior review.',
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertEquals(ComplaintCase::STATUS_ESCALATED, $case->fresh()->status);
    }

    public function test_resolve_rejects_invalid_resolution_type(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $client = User::factory()->create(['role' => 'client']);
        $case = ComplaintCase::create([
            'reference' => 'CASE-004',
            'status' => ComplaintCase::STATUS_OPEN,
            'client_id' => $client->id,
            'category' => ComplaintCase::CATEGORY_OTHER,
            'subject' => 'Test',
            'description' => 'Test',
        ]);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/disputes/{$case->id}/resolve", [
            'resolution_type' => 'invalid_type',
        ])->assertUnprocessable();
    }

    // ─── Auto-dispatch ────────────────────────────────────────────────────────

    public function test_dispatch_returns_422_when_no_planned_mission(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = Booking::factory()->create(['status' => 'en_attente']);

        Sanctum::actingAs($admin);

        $this->postJson("/api/admin/bookings/{$booking->id}/dispatch")
            ->assertUnprocessable()
            ->assertJson(['ok' => false]);
    }

    public function test_dispatch_endpoint_requires_authentication(): void
    {
        $booking = Booking::factory()->create();

        $this->postJson("/api/admin/bookings/{$booking->id}/dispatch")
            ->assertUnauthorized();
    }
}
