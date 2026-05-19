<?php

namespace Tests\Feature\Kyc;

use App\Jobs\Kyc\ProcessKycWebhookJob;
use App\Models\KycVerification;
use App\Models\KycWebhookEvent;
use App\Models\ProviderProfile;
use App\Models\User;
use App\Services\Kyc\KycProviderInterface;
use App\Services\Kyc\KycVerificationService;
use App\Services\Kyc\Providers\KycMockProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class KycWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->bind(KycProviderInterface::class, KycMockProvider::class);
    }

    public function test_unknown_provider_returns_404(): void
    {
        $this->postJson('/webhooks/kyc/unknown', [])->assertStatus(404);
    }

    public function test_mock_webhook_stores_event_and_dispatches_job(): void
    {
        Queue::fake();

        $payload = [
            'id' => 'mock_event_001',
            'event_type' => 'check.completed',
            'decision' => 'approved',
            'status' => 'clear',
            'score' => 0.95,
        ];

        $response = $this->postJson('/webhooks/kyc/mock', $payload);

        $response->assertOk();
        $response->assertJson(['ok' => true]);

        $this->assertSame(1, KycWebhookEvent::count());
        $event = KycWebhookEvent::first();
        $this->assertSame('mock', $event->provider);
        $this->assertSame('mock_event_001', $event->external_event_id);

        Queue::assertPushed(ProcessKycWebhookJob::class);
    }

    public function test_duplicate_webhook_event_id_is_not_redispatched(): void
    {
        Queue::fake();

        $payload = [
            'id' => 'dup_event',
            'event_type' => 'check.completed',
            'decision' => 'approved',
        ];

        $this->postJson('/webhooks/kyc/mock', $payload);

        KycWebhookEvent::where('external_event_id', 'dup_event')
            ->update(['status' => KycWebhookEvent::STATUS_PROCESSED]);

        $this->postJson('/webhooks/kyc/mock', $payload);

        $this->assertSame(1, KycWebhookEvent::count());
        Queue::assertPushed(ProcessKycWebhookJob::class, 1);
    }

    public function test_apply_webhook_payload_updates_matching_verification(): void
    {
        $user = User::factory()->create(['role' => 'employe', 'email' => 'good@example.com']);
        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'independent',
            'status' => 'active',
            'verification_status' => 'pending',
        ]);

        $verification = app(KycVerificationService::class)->start($user);
        $applicantId = $verification->external_applicant_id;

        $payload = [
            'event_type' => 'check.completed',
            'payload' => [
                'object' => ['id' => $applicantId],
            ],
            'decision' => 'approved',
            'status' => 'clear',
            'score' => 0.9,
        ];

        $result = app(KycVerificationService::class)->applyWebhookPayload($payload);

        $this->assertNotNull($result);
        $this->assertSame((int) $verification->id, (int) $result->id);
        $this->assertSame(KycVerification::STATUS_CLEAR, $result->status);
        $this->assertSame(KycVerification::DECISION_APPROVED, $result->decision);
    }
}
