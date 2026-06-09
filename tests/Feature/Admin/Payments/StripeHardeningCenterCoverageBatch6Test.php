<?php

namespace Tests\Feature\Admin\Payments;

use App\Jobs\Payments\ProcessStripeWebhookJob;
use App\Livewire\Admin\Payments\StripeHardeningCenter;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use Database\Factories\StripeReconciliationRunFactory;
use Database\Factories\StripeWebhookEventFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class StripeHardeningCenterCoverageBatch6Test extends TestCase
{
    use RefreshDatabase;

    public function test_mount_sets_default_dates_and_renders_webhooks_tab(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(StripeHardeningCenter::class)
            ->assertOk()
            ->assertSet('tab', 'webhooks')
            ->assertSet('reconcileScope', 'all')
            ->assertSet('reconcileFromDate', now()->subDays(7)->toDateString())
            ->assertSet('reconcileToDate', now()->toDateString());
    }

    public function test_retry_event_requeues_failed_event_and_dispatches_job(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $event = StripeWebhookEventFactory::new()->failed()->create();

        Livewire::actingAs($admin)
            ->test(StripeHardeningCenter::class)
            ->call('retryEvent', $event->id)
            ->assertDispatched('toast');

        $event->refresh();
        $this->assertSame(StripeWebhookEvent::STATUS_RECEIVED, $event->status);
        $this->assertNull($event->next_retry_at);
        $this->assertNull($event->last_error);

        Queue::assertPushed(ProcessStripeWebhookJob::class);
    }

    public function test_retry_event_allows_dead_letter_status(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $event = StripeWebhookEventFactory::new()->create([
            'status' => StripeWebhookEvent::STATUS_DEAD_LETTER,
            'attempts' => 5,
            'max_attempts' => 5,
        ]);

        Livewire::actingAs($admin)
            ->test(StripeHardeningCenter::class)
            ->call('retryEvent', $event->id);

        $event->refresh();
        $this->assertSame(StripeWebhookEvent::STATUS_RECEIVED, $event->status);
        Queue::assertPushed(ProcessStripeWebhookJob::class);
    }

    public function test_retry_event_rejects_non_retryable_event(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $event = StripeWebhookEventFactory::new()->create([
            'status' => StripeWebhookEvent::STATUS_PROCESSED,
        ]);

        Livewire::actingAs($admin)
            ->test(StripeHardeningCenter::class)
            ->call('retryEvent', $event->id)
            ->assertDispatched('toast');

        $event->refresh();
        $this->assertSame(StripeWebhookEvent::STATUS_PROCESSED, $event->status);
        Queue::assertNothingPushed();
    }

    public function test_mark_ignored_sets_status_and_processed_at(): void
    {
        $admin = User::factory()->admin()->create();
        $event = StripeWebhookEventFactory::new()->failed()->create();

        Livewire::actingAs($admin)
            ->test(StripeHardeningCenter::class)
            ->call('markIgnored', $event->id)
            ->assertDispatched('toast');

        $event->refresh();
        $this->assertSame(StripeWebhookEvent::STATUS_IGNORED, $event->status);
        $this->assertNotNull($event->processed_at);
    }

    public function test_run_reconciliation_validates_date_order(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(StripeHardeningCenter::class)
            ->set('reconcileFromDate', now()->toDateString())
            ->set('reconcileToDate', now()->subDays(5)->toDateString())
            ->call('runReconciliation')
            ->assertHasErrors(['reconcileToDate']);
    }

    public function test_run_reconciliation_rejects_invalid_scope(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(StripeHardeningCenter::class)
            ->set('reconcileScope', 'bogus')
            ->call('runReconciliation')
            ->assertHasErrors(['reconcileScope']);
    }

    public function test_run_reconciliation_completes_and_creates_run(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(StripeHardeningCenter::class)
            ->set('reconcileScope', 'all')
            ->set('reconcileFromDate', now()->subDays(3)->toDateString())
            ->set('reconcileToDate', now()->toDateString())
            ->call('runReconciliation')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseCount('stripe_reconciliation_runs', 1);
    }

    public function test_status_filter_renders_webhooks_tab(): void
    {
        $admin = User::factory()->admin()->create();
        StripeWebhookEventFactory::new()->failed()->create();
        StripeWebhookEventFactory::new()->create();

        Livewire::actingAs($admin)
            ->test(StripeHardeningCenter::class)
            ->set('statusFilter', StripeWebhookEvent::STATUS_FAILED)
            ->assertOk();
    }

    public function test_reconciliation_tab_renders(): void
    {
        $admin = User::factory()->admin()->create();
        StripeReconciliationRunFactory::new()->create(['triggered_by_user_id' => $admin->id]);

        Livewire::actingAs($admin)
            ->test(StripeHardeningCenter::class)
            ->set('tab', 'reconciliation')
            ->assertOk();
    }

    public function test_failures_tab_renders(): void
    {
        $admin = User::factory()->admin()->create();
        StripeWebhookEventFactory::new()->failed()->create();
        StripeWebhookEventFactory::new()->create([
            'status' => StripeWebhookEvent::STATUS_DEAD_LETTER,
        ]);

        Livewire::actingAs($admin)
            ->test(StripeHardeningCenter::class)
            ->set('tab', 'failures')
            ->assertOk();
    }
}
