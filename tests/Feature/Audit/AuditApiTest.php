<?php

namespace Tests\Feature\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Audit\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_authentication(): void
    {
        $this->getJson('/api/admin/audit/events')->assertStatus(401);
    }

    public function test_index_returns_recent_events(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        app(AuditService::class)->record('booking.created', ['x' => 1]);
        app(AuditService::class)->record('payment.failed', ['y' => 2]);

        $response = $this->getJson('/api/admin/audit/events');
        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_index_filters_by_domain(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        app(AuditService::class)->record('booking.created', []);
        app(AuditService::class)->record('payment.failed', []);

        $response = $this->getJson('/api/admin/audit/events?domain=payment');

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('payment', $data[0]['domain']);
    }

    public function test_show_returns_event_detail(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $event = app(AuditService::class)->record('booking.created', ['booking_id' => 42]);

        $response = $this->getJson("/api/admin/audit/events/{$event->id}");

        $response->assertOk();
        $this->assertSame((int) $event->id, (int) $response->json('data.id'));
    }

    public function test_pin_toggles_is_pinned_flag(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $event = app(AuditService::class)->record('booking.created', []);
        $this->assertFalse($event->is_pinned);

        $this->postJson("/api/admin/audit/events/{$event->id}/pin")->assertOk();
        $this->assertTrue($event->fresh()->is_pinned);

        $this->postJson("/api/admin/audit/events/{$event->id}/unpin")->assertOk();
        $this->assertFalse($event->fresh()->is_pinned);
    }

    public function test_export_csv_returns_streamed_response(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        app(AuditService::class)->record('booking.created', ['x' => 1]);

        $response = $this->get('/api/admin/audit/events/export?format=csv');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    }
}
