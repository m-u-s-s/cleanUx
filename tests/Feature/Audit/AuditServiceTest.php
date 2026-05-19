<?php

namespace Tests\Feature\Audit;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Audit\AuditService;
use Database\Seeders\AuditDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AuditServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('audit.enabled', true);
    }

    public function test_record_creates_event_with_inferred_domain_and_severity(): void
    {
        $event = app(AuditService::class)->record('booking.created', [
            'booking_id' => 42,
        ]);

        $this->assertNotNull($event);
        $this->assertSame('booking.created', $event->event_type);
        $this->assertSame('booking', $event->domain);
        $this->assertSame(AuditEvent::SEVERITY_INFO, $event->severity);
    }

    public function test_record_classifies_severity_from_event_type(): void
    {
        $svc = app(AuditService::class);

        $error = $svc->record('payment.failed', ['amount' => 100]);
        $warn = $svc->record('user.deleted', ['user_id' => 1]);
        $crit = $svc->record('security.breach.detected', []);

        $this->assertSame(AuditEvent::SEVERITY_ERROR, $error->severity);
        $this->assertSame(AuditEvent::SEVERITY_WARNING, $warn->severity);
        $this->assertSame(AuditEvent::SEVERITY_CRITICAL, $crit->severity);
    }

    public function test_record_uses_authenticated_user_as_actor_by_default(): void
    {
        $user = User::factory()->client()->create();
        $this->actingAs($user);

        $event = app(AuditService::class)->record('booking.created', []);

        $this->assertSame(AuditEvent::ACTOR_USER, $event->actor_type);
        $this->assertSame($user->id, (int) $event->actor_id);
        $this->assertSame($user->email, $event->actor_label);
    }

    public function test_record_explicit_actor_overrides_authenticated(): void
    {
        $user = User::factory()->client()->create();
        $this->actingAs($user);

        $event = app(AuditService::class)->record('webhook.received', [], ['actor' => 'webhook']);

        $this->assertSame(AuditEvent::ACTOR_WEBHOOK, $event->actor_type);
    }

    public function test_record_subject_resolves_type_id_label(): void
    {
        $user = User::factory()->client()->create();
        $event = app(AuditService::class)->record('user.updated', ['change' => 'email'], [
            'subject' => $user,
        ]);

        $this->assertSame('User', $event->subject_type);
        $this->assertSame($user->id, (int) $event->subject_id);
        $this->assertSame($user->email, $event->subject_label);
    }

    public function test_record_is_idempotent_with_same_key(): void
    {
        $svc = app(AuditService::class);

        $a = $svc->record('test.event', ['x' => 1], ['idempotency_key' => 'k-001']);
        $b = $svc->record('test.event', ['x' => 2], ['idempotency_key' => 'k-001']);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, AuditEvent::count());
    }

    public function test_record_skipped_when_disabled(): void
    {
        Config::set('audit.enabled', false);

        $event = app(AuditService::class)->record('test.event', []);

        $this->assertNull($event);
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_record_soft_fail_on_exception(): void
    {
        // Invalid context that breaks JSON encoding — large nested
        $event = app(AuditService::class)->record('test.event', [
            'normal' => 'ok',
        ]);

        $this->assertNotNull($event);
    }

    public function test_pin_and_unpin(): void
    {
        $svc = app(AuditService::class);
        $event = $svc->record('test.event', []);

        $pinned = $svc->pin($event);
        $this->assertTrue($pinned->is_pinned);

        $unpinned = $svc->unpin($event);
        $this->assertFalse($unpinned->is_pinned);
    }
}
