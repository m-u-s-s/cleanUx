<?php

namespace Tests\Feature\Admin\Gdpr;

use App\Livewire\Admin\Gdpr\GdprCenter;
use App\Models\ActivityLog;
use App\Models\GdprDataRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Coverage for the admin GDPR compliance centre Livewire component.
 *
 * Exercises mount/render across all three tabs (requests, audit, retention),
 * the KPI aggregation block, the requests filters (search / type / status) and
 * every wire action — cancelRequest, executeNow (erasure happy path + the
 * non-erasure guard) and runRetention.
 */
class GdprCenterCoverageBatch9Test extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->adminComplet()->create();
    }

    #[Test]
    public function it_renders_the_requests_tab_with_kpis_for_an_admin(): void
    {
        $admin = $this->admin();

        $anonymized = User::factory()->client()->create();
        $anonymized->forceFill(['anonymized_at' => now()])->save();

        GdprDataRequest::create([
            'user_id' => $anonymized->id,
            'type' => GdprDataRequest::TYPE_EXPORT,
            'status' => GdprDataRequest::STATUS_PENDING,
            'reference' => 'GDPR-EXP0001',
            'requested_at' => now(),
        ]);

        GdprDataRequest::create([
            'user_id' => $anonymized->id,
            'type' => GdprDataRequest::TYPE_ERASURE,
            'status' => GdprDataRequest::STATUS_AWAITING_GRACE_PERIOD,
            'reference' => 'GDPR-ERA0001',
            'requested_at' => now(),
            'grace_period_ends_at' => now()->subMinute(),
        ]);

        Livewire::actingAs($admin)
            ->test(GdprCenter::class)
            ->assertOk()
            ->assertViewHas('currentView', 'requests')
            ->assertViewHas('items')
            ->assertViewHas('auditItems')
            ->assertViewHas('kpis', function (array $kpis) {
                return $kpis['pending_export'] === 1
                    && $kpis['awaiting_erasure'] === 1
                    && $kpis['ready_for_execution'] === 1
                    && $kpis['anonymized_total'] === 1;
            });
    }

    #[Test]
    public function it_applies_the_requests_filters(): void
    {
        $admin = $this->admin();
        $alice = User::factory()->client()->create(['name' => 'Alice Searchable', 'email' => 'alice@example.test']);

        GdprDataRequest::create([
            'user_id' => $alice->id,
            'type' => GdprDataRequest::TYPE_ERASURE,
            'status' => GdprDataRequest::STATUS_AWAITING_GRACE_PERIOD,
            'reference' => 'GDPR-FILTER01',
            'requested_at' => now(),
            'grace_period_ends_at' => now()->addDays(30),
        ]);

        GdprDataRequest::create([
            'user_id' => $alice->id,
            'type' => GdprDataRequest::TYPE_EXPORT,
            'status' => GdprDataRequest::STATUS_FULFILLED,
            'reference' => 'GDPR-FILTER02',
            'requested_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(GdprCenter::class)
            ->set('filterType', GdprDataRequest::TYPE_ERASURE)
            ->set('filterStatus', GdprDataRequest::STATUS_AWAITING_GRACE_PERIOD)
            ->set('search', 'Alice')
            ->assertOk()
            ->assertViewHas('items', fn ($items) => $items->count() === 1
                && $items->first()->reference === 'GDPR-FILTER01');
    }

    #[Test]
    public function it_renders_the_audit_tab_with_filters(): void
    {
        $admin = $this->admin();

        ActivityLog::create([
            'user_id' => $admin->id,
            'action' => 'gdpr.erasure_executed',
            'domain' => 'security',
            'severity' => 'warning',
            'target_type' => User::class,
            'target_id' => $admin->id,
        ]);

        ActivityLog::create([
            'user_id' => null,
            'action' => 'finance.payout',
            'domain' => 'finance',
            'severity' => 'info',
        ]);

        Livewire::actingAs($admin)
            ->test(GdprCenter::class)
            ->set('tab', 'audit')
            ->set('auditDomain', 'security')
            ->set('auditSearch', 'erasure')
            ->assertOk()
            ->assertViewHas('currentView', 'audit')
            ->assertViewHas('auditItems', fn ($items) => $items->count() === 1
                && $items->first()->action === 'gdpr.erasure_executed');
    }

    #[Test]
    public function it_renders_the_retention_tab_and_runs_retention(): void
    {
        $admin = $this->admin();

        Livewire::actingAs($admin)
            ->test(GdprCenter::class)
            ->set('tab', 'retention')
            ->assertOk()
            ->assertViewHas('currentView', 'retention')
            ->call('runRetention')
            ->assertDispatched('toast');
    }

    #[Test]
    public function cancel_request_cancels_a_pending_erasure(): void
    {
        $admin = $this->admin();
        $client = User::factory()->client()->create();

        $request = GdprDataRequest::create([
            'user_id' => $client->id,
            'type' => GdprDataRequest::TYPE_ERASURE,
            'status' => GdprDataRequest::STATUS_AWAITING_GRACE_PERIOD,
            'reference' => 'GDPR-CANCEL01',
            'requested_at' => now(),
            'grace_period_ends_at' => now()->addDays(30),
        ]);

        Livewire::actingAs($admin)
            ->test(GdprCenter::class)
            ->call('cancelRequest', $request->id)
            ->assertDispatched('toast');

        $this->assertSame(GdprDataRequest::STATUS_CANCELLED, $request->fresh()->status);
    }

    #[Test]
    public function execute_now_runs_the_erasure_and_anonymizes_the_user(): void
    {
        $admin = $this->admin();
        $client = User::factory()->client()->create();

        $request = GdprDataRequest::create([
            'user_id' => $client->id,
            'type' => GdprDataRequest::TYPE_ERASURE,
            'status' => GdprDataRequest::STATUS_AWAITING_GRACE_PERIOD,
            'reference' => 'GDPR-EXEC01',
            'requested_at' => now(),
            'grace_period_ends_at' => now()->addDays(30),
        ]);

        Livewire::actingAs($admin)
            ->test(GdprCenter::class)
            ->call('executeNow', $request->id)
            ->assertDispatched('toast');

        $this->assertSame(GdprDataRequest::STATUS_FULFILLED, $request->fresh()->status);
        $this->assertNotNull($client->fresh()->anonymized_at);
    }

    #[Test]
    public function execute_now_rejects_a_non_erasure_request(): void
    {
        $admin = $this->admin();
        $client = User::factory()->client()->create();

        $request = GdprDataRequest::create([
            'user_id' => $client->id,
            'type' => GdprDataRequest::TYPE_EXPORT,
            'status' => GdprDataRequest::STATUS_PENDING,
            'reference' => 'GDPR-NOTERA01',
            'requested_at' => now(),
        ]);

        Livewire::actingAs($admin)
            ->test(GdprCenter::class)
            ->call('executeNow', $request->id)
            ->assertDispatched('toast');

        $this->assertSame(GdprDataRequest::STATUS_PENDING, $request->fresh()->status);
    }
}
