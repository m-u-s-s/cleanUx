<?php

namespace Tests\Feature\Audit;

use App\Models\AuditEvent;
use App\Models\AuditRetentionPolicy;
use App\Services\Audit\AuditRetentionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AuditRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('audit.enabled', true);
        Config::set('audit.retention_days_by_domain', [
            'general' => 30,
            'security' => 90,
        ]);
        Config::set('audit.never_purge_severity', ['critical']);
        Config::set('audit.purge_batch_size', 1000);
        Config::set('audit.purge_max_runtime_seconds', 60);
    }

    protected function makeEvent(string $domain, string $severity, \DateTimeInterface $occurredAt, bool $pinned = false): AuditEvent
    {
        return AuditEvent::create([
            'event_type' => "{$domain}.test",
            'domain' => $domain,
            'severity' => $severity,
            'occurred_at' => $occurredAt,
            'is_pinned' => $pinned,
        ]);
    }

    public function test_purge_removes_old_general_events(): void
    {
        $this->makeEvent('general', 'info', now()->subDays(60));
        $this->makeEvent('general', 'info', now()->subDays(5));

        $count = app(AuditRetentionService::class)->purge();

        $this->assertSame(1, $count);
        $this->assertSame(1, AuditEvent::count());
    }

    public function test_purge_respects_per_domain_retention(): void
    {
        $this->makeEvent('general', 'info', now()->subDays(60));    // > 30 → purged
        $this->makeEvent('security', 'info', now()->subDays(60));   // < 90 → kept

        $count = app(AuditRetentionService::class)->purge();

        $this->assertSame(1, $count);
        $this->assertSame(1, AuditEvent::query()->where('domain', 'security')->count());
    }

    public function test_purge_never_removes_pinned_events(): void
    {
        $this->makeEvent('general', 'info', now()->subDays(60), pinned: true);

        $count = app(AuditRetentionService::class)->purge();

        $this->assertSame(0, $count);
        $this->assertSame(1, AuditEvent::count());
    }

    public function test_purge_never_removes_critical_severity(): void
    {
        $this->makeEvent('general', 'critical', now()->subDays(60));

        $count = app(AuditRetentionService::class)->purge();

        $this->assertSame(0, $count);
        $this->assertSame(1, AuditEvent::count());
    }

    public function test_db_policy_overrides_config_default(): void
    {
        AuditRetentionPolicy::create([
            'code' => 'general_short',
            'name' => 'Short general',
            'domain' => 'general',
            'retention_days' => 5,
            'is_active' => true,
        ]);

        $this->makeEvent('general', 'info', now()->subDays(10));  // would survive 30d default but not 5d policy

        $count = app(AuditRetentionService::class)->purge();

        $this->assertSame(1, $count);
        $this->assertSame(0, AuditEvent::count());
    }
}
