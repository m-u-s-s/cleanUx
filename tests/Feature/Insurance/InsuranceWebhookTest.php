<?php

namespace Tests\Feature\Insurance;

use App\Jobs\Insurance\ProcessInsuranceWebhookJob;
use App\Models\Booking;
use App\Models\BookingInsurance;
use App\Models\InsuranceWebhookEvent;
use App\Models\User;
use App\Services\Insurance\InsuranceProviderInterface;
use App\Services\Insurance\InsuranceService;
use App\Services\Insurance\Providers\InsuranceMockProvider;
use Database\Seeders\InsurancePlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class InsuranceWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(InsuranceProviderInterface::class, InsuranceMockProvider::class);
        $this->seed(InsurancePlansSeeder::class);
    }

    public function test_unknown_provider_returns_404(): void
    {
        $this->postJson('/webhooks/insurance/unknown', [])->assertStatus(404);
    }

    public function test_mock_webhook_stores_event_and_dispatches_job(): void
    {
        Queue::fake();

        $payload = [
            'event_id' => 'mock_evt_001',
            'event_type' => 'policy.cancelled',
            'target' => 'policy',
            'external_id' => 'mock_pol_xyz',
            'status' => 'cancelled',
        ];

        $response = $this->postJson('/webhooks/insurance/mock', $payload);

        $response->assertOk();
        $this->assertSame(1, InsuranceWebhookEvent::count());
        Queue::assertPushed(ProcessInsuranceWebhookJob::class);
    }

    public function test_duplicate_webhook_event_not_redispatched(): void
    {
        Queue::fake();

        $payload = [
            'event_id' => 'dup_evt',
            'target' => 'policy',
            'external_id' => 'mock_pol_dup',
            'status' => 'active',
        ];

        $this->postJson('/webhooks/insurance/mock', $payload);

        InsuranceWebhookEvent::where('external_event_id', 'dup_evt')
            ->update(['status' => InsuranceWebhookEvent::STATUS_PROCESSED]);

        $this->postJson('/webhooks/insurance/mock', $payload);

        $this->assertSame(1, InsuranceWebhookEvent::count());
        Queue::assertPushed(ProcessInsuranceWebhookJob::class, 1);
    }

    public function test_processing_webhook_applies_status_to_existing_policy(): void
    {
        $user = User::factory()->client()->create();
        $booking = Booking::create([
            'client_id' => $user->id,
            'date' => now()->addDay(),
            'heure' => '10:00',
            'status' => 'en_attente',
            'devis_estime' => 100,
        ]);

        $insurance = app(InsuranceService::class)->purchase($booking->id, 'basic', $user);

        $event = InsuranceWebhookEvent::create([
            'provider' => 'mock',
            'external_event_id' => 'apply_test',
            'event_type' => 'policy.cancelled',
            'payload' => [
                'target' => 'policy',
                'external_id' => $insurance->external_id,
                'status' => 'cancelled',
            ],
            'status' => InsuranceWebhookEvent::STATUS_RECEIVED,
            'received_at' => now(),
        ]);

        (new ProcessInsuranceWebhookJob($event->id))
            ->handle(app(InsuranceProviderInterface::class), app(InsuranceService::class));

        $event->refresh();
        $this->assertSame(InsuranceWebhookEvent::STATUS_PROCESSED, $event->status);

        $insurance->refresh();
        $this->assertSame(BookingInsurance::STATUS_CANCELLED, $insurance->status);
    }

    public function test_processing_webhook_unknown_target_marks_ignored(): void
    {
        $event = InsuranceWebhookEvent::create([
            'provider' => 'mock',
            'external_event_id' => 'orphan',
            'event_type' => 'policy.cancelled',
            'payload' => [
                'target' => 'policy',
                'external_id' => 'nonexistent',
                'status' => 'cancelled',
            ],
            'status' => InsuranceWebhookEvent::STATUS_RECEIVED,
            'received_at' => now(),
        ]);

        (new ProcessInsuranceWebhookJob($event->id))
            ->handle(app(InsuranceProviderInterface::class), app(InsuranceService::class));

        $event->refresh();
        $this->assertSame(InsuranceWebhookEvent::STATUS_IGNORED, $event->status);
    }
}
