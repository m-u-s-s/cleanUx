<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\AuditLogsCenter;
use App\Models\ActivityLog;
use App\Models\ServiceZone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogsCenterCoverageBatch10Test extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $client;

    protected ServiceZone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->client = User::factory()->client()->create([
            'name' => 'Alice Doe',
            'email' => 'alice@example.test',
        ]);
        $this->zone = ServiceZone::factory()->create(['name' => 'Brussels Zone']);
    }

    private function seedLog(array $overrides = []): ActivityLog
    {
        return ActivityLog::create(array_merge([
            'user_id' => $this->client->id,
            'action' => 'booking.created',
            'domain' => 'booking',
            'severity' => 'info',
            'is_critical' => false,
            'target_type' => 'App\\Models\\Booking',
            'target_id' => 42,
            'request_id' => 'req-1234',
            'service_zone_id' => $this->zone->id,
            'meta' => ['k' => 'v'],
        ], $overrides));
    }

    public function test_mount_renders_for_global_admin_without_zone_filter(): void
    {
        $this->seedLog();

        Livewire::actingAs($this->admin)
            ->test(AuditLogsCenter::class)
            ->assertOk()
            ->assertSet('zoneFilter', '')
            ->assertViewHas('logs')
            ->assertViewHas('availableActions')
            ->assertViewHas('domains')
            ->assertViewHas('zones')
            ->assertViewHas('severities');
    }

    public function test_mount_presets_zone_filter_for_zone_scoped_admin(): void
    {
        $zoneAdmin = User::factory()->admin()->create([
            'access_scope' => 'zone',
            'managed_service_zone_id' => $this->zone->id,
        ]);

        Livewire::actingAs($zoneAdmin)
            ->test(AuditLogsCenter::class)
            ->assertOk()
            ->assertSet('zoneFilter', (string) $this->zone->id);
    }

    public function test_zone_scoped_admin_only_sees_own_zone_and_self_logs(): void
    {
        $zoneAdmin = User::factory()->admin()->create([
            'access_scope' => 'zone',
            'managed_service_zone_id' => $this->zone->id,
        ]);

        $otherZone = ServiceZone::factory()->create();

        $inZone = $this->seedLog(['action' => 'in.zone']);
        $selfNullZone = $this->seedLog([
            'action' => 'self.global',
            'user_id' => $zoneAdmin->id,
            'service_zone_id' => null,
        ]);
        $otherZoneLog = $this->seedLog([
            'action' => 'other.zone',
            'service_zone_id' => $otherZone->id,
        ]);

        Livewire::actingAs($zoneAdmin)
            ->test(AuditLogsCenter::class)
            ->set('zoneFilter', '')
            ->assertOk()
            ->assertViewHas('logs', function ($logs) use ($inZone, $selfNullZone, $otherZoneLog) {
                $ids = $logs->pluck('id')->all();

                return in_array($inZone->id, $ids, true)
                    && in_array($selfNullZone->id, $ids, true)
                    && ! in_array($otherZoneLog->id, $ids, true);
            });
    }

    public function test_search_matches_action_target_and_user(): void
    {
        $match = $this->seedLog(['action' => 'unique-search-token']);
        $miss = $this->seedLog(['action' => 'something.else', 'request_id' => 'zzz']);

        Livewire::actingAs($this->admin)
            ->test(AuditLogsCenter::class)
            ->set('search', 'unique-search-token')
            ->assertViewHas('logs', fn ($logs) => $logs->pluck('id')->contains($match->id)
                && ! $logs->pluck('id')->contains($miss->id));
    }

    public function test_search_matches_user_email_via_relation(): void
    {
        $match = $this->seedLog(['user_id' => $this->client->id]);
        $miss = $this->seedLog(['action' => 'no.user', 'user_id' => null]);

        Livewire::actingAs($this->admin)
            ->test(AuditLogsCenter::class)
            ->set('search', 'alice@example.test')
            ->assertViewHas('logs', fn ($logs) => $logs->pluck('id')->contains($match->id)
                && ! $logs->pluck('id')->contains($miss->id));
    }

    public function test_action_and_domain_filters(): void
    {
        $match = $this->seedLog(['action' => 'finance.payout', 'domain' => 'finance']);
        $miss = $this->seedLog(['action' => 'booking.created', 'domain' => 'booking']);

        Livewire::actingAs($this->admin)
            ->test(AuditLogsCenter::class)
            ->set('actionFilter', 'finance.payout')
            ->set('domainFilter', 'finance')
            ->assertViewHas('logs', fn ($logs) => $logs->pluck('id')->contains($match->id)
                && ! $logs->pluck('id')->contains($miss->id));
    }

    public function test_severity_and_zone_filters(): void
    {
        $match = $this->seedLog(['severity' => 'error', 'service_zone_id' => $this->zone->id]);
        $otherZone = ServiceZone::factory()->create();
        $miss = $this->seedLog(['severity' => 'info', 'service_zone_id' => $otherZone->id]);

        Livewire::actingAs($this->admin)
            ->test(AuditLogsCenter::class)
            ->set('severityFilter', 'error')
            ->set('zoneFilter', (string) $this->zone->id)
            ->assertViewHas('logs', fn ($logs) => $logs->pluck('id')->contains($match->id)
                && ! $logs->pluck('id')->contains($miss->id));
    }

    public function test_actor_filter_system_and_human(): void
    {
        $system = $this->seedLog(['action' => 'cron.ran', 'user_id' => null]);
        $human = $this->seedLog(['action' => 'human.action', 'user_id' => $this->client->id]);

        $component = Livewire::actingAs($this->admin)
            ->test(AuditLogsCenter::class);

        $component->set('actorFilter', 'system')
            ->assertViewHas('logs', fn ($logs) => $logs->pluck('id')->contains($system->id)
                && ! $logs->pluck('id')->contains($human->id));

        $component->set('actorFilter', 'human')
            ->assertViewHas('logs', fn ($logs) => $logs->pluck('id')->contains($human->id)
                && ! $logs->pluck('id')->contains($system->id));
    }

    public function test_critical_only_filter_matches_flag_and_keywords(): void
    {
        $flagged = $this->seedLog(['action' => 'plain.thing', 'is_critical' => true]);
        $keyword = $this->seedLog(['action' => 'user.delete', 'is_critical' => false]);
        $miss = $this->seedLog(['action' => 'plain.read', 'is_critical' => false]);

        Livewire::actingAs($this->admin)
            ->test(AuditLogsCenter::class)
            ->set('criticalOnly', true)
            ->assertViewHas('logs', function ($logs) use ($flagged, $keyword, $miss) {
                $ids = $logs->pluck('id')->all();

                return in_array($flagged->id, $ids, true)
                    && in_array($keyword->id, $ids, true)
                    && ! in_array($miss->id, $ids, true);
            });
    }

    public function test_available_actions_and_domains_properties(): void
    {
        $this->seedLog(['action' => 'zeta.action', 'domain' => 'security']);
        $this->seedLog(['action' => 'alpha.action', 'domain' => 'booking']);

        $component = Livewire::actingAs($this->admin)
            ->test(AuditLogsCenter::class);

        $actions = $component->instance()->getAvailableActionsProperty();
        $this->assertTrue($actions->contains('alpha.action'));
        $this->assertTrue($actions->contains('zeta.action'));

        $domains = $component->instance()->getDomainsProperty();
        $this->assertTrue($domains->contains('security'));
        $this->assertTrue($domains->contains('booking'));
    }

    public function test_zones_property_returns_seeded_zone(): void
    {
        $component = Livewire::actingAs($this->admin)
            ->test(AuditLogsCenter::class);

        $zones = $component->instance()->getZonesProperty();
        $this->assertTrue($zones->pluck('id')->contains($this->zone->id));
    }

    public function test_updating_resets_pagination(): void
    {
        foreach (range(1, 20) as $i) {
            $this->seedLog(['action' => "bulk.$i"]);
        }

        $component = Livewire::actingAs($this->admin)
            ->test(AuditLogsCenter::class);

        $component->call('gotoPage', 2);
        $this->assertEquals(2, $component->instance()->logs->currentPage());

        $component->set('search', 'bulk');
        $this->assertEquals(1, $component->instance()->logs->currentPage());
    }
}
